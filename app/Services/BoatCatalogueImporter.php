<?php

namespace App\Services;

use App\Models\CompanyBoatModel;
use App\Models\CompanyBoatVariant;
use App\Models\CompanyBrand;
use App\Models\CompanyOption;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Bulk create and update of a dealership's boats, versions, included equipment
 * and options, from the workbook BoatCatalogueExporter writes.
 *
 * Two rules shape everything here.
 *
 * **Nothing is ever deleted.** A boat, version or option missing from the file
 * is left exactly as it was. A price list from a manufacturer covers what that
 * manufacturer sells this year; it is not a statement about the rest of the
 * dealer's catalogue, and treating it as one would quietly wipe boats.
 *
 * **A column that is not in the file is not a value.** Only the columns present
 * in the header row are written, so the yearly update — a sheet of MARQUE,
 * MODELE, VERSION, PRIX HT and nothing else — changes prices and leaves every
 * equipment list, cost and code untouched.
 *
 * Import runs in two passes: parse() reads and plans without writing anything,
 * and the plan is shown to the dealer; commit() applies a plan. A file can
 * touch the entire catalogue, so seeing the damage first is the point.
 */
class BoatCatalogueImporter
{
    public const MAX_ROWS = 5000;

    public function __construct(private ?FxRateService $fx = null)
    {
        $this->fx = $fx ?? new FxRateService();
    }

    /* ============================================================ HEADERS */

    /** Header text (ascii, lowercased, trimmed) → field. FR first, EN aliases after. */
    private const BOAT_ALIASES = [
        'ref modele' => 'model_ref',   'ref model' => 'model_ref',    'model ref' => 'model_ref',
        'marque' => 'brand',           'brand' => 'brand',
        'modele' => 'model',           'model' => 'model',            'bateau' => 'model',       'boat' => 'model',
        'code modele' => 'code',       'code model' => 'code',        'code' => 'code',
        'complement' => 'complement',  'sous-nom' => 'complement',
        'annee' => 'year',             'year' => 'year',
        'type' => 'type',              'categorie' => 'type',
        'propulsion' => 'propulsion',  'motorisation' => 'propulsion',
        'longueur' => 'length_total',  'longueur totale' => 'length_total', 'length' => 'length_total',
        'largeur' => 'beam',           'beam' => 'beam',
        "tirant d'eau" => 'draft_max', 'tirant deau' => 'draft_max',  'draft' => 'draft_max',
        'poids' => 'weight',           'weight' => 'weight',
        'fournisseur' => 'supplier',   'supplier' => 'supplier',
        'ref version' => 'variant_ref','variant ref' => 'variant_ref',
        'version' => 'variant',        'variant' => 'variant',        'declinaison' => 'variant',
        'prix ht' => 'base_price',     'prix' => 'base_price',        'price ht' => 'base_price',  'price' => 'base_price',
        'pv ht' => 'base_price',
        'cout ht' => 'cost',           'cout' => 'cost',              'cost' => 'cost',            'pa ht' => 'cost',
        'prix achat' => 'cost',
        'devise' => 'currency',        'currency' => 'currency',
        'equipements inclus' => 'equipment', 'equipement inclus' => 'equipment',
        'equipements' => 'equipment',  'equipment' => 'equipment',    'included equipment' => 'equipment',
        'actif' => 'is_active',        'active' => 'is_active',       'statut' => 'is_active',
    ];

    private const OPTION_ALIASES = [
        'ref modele' => 'model_ref',   'ref model' => 'model_ref',
        'marque' => 'brand',           'brand' => 'brand',
        'modele' => 'model',           'model' => 'model',            'bateau' => 'model',
        'code' => 'code',              'reference' => 'code',         'sku' => 'code',
        'famille' => 'category',       'category' => 'category',      'categorie' => 'category',
        'designation' => 'label',      'libelle' => 'label',          'label' => 'label',      'option' => 'label',
        'nom' => 'label',
        'description' => 'description','detail' => 'description',     'details' => 'description',
        'commentaire' => 'description','note' => 'description',
        'pa ht' => 'cost',             'cout ht' => 'cost',           'cost' => 'cost',        'prix achat' => 'cost',
        'pv ht' => 'price',            'prix ht' => 'price',          'price' => 'price',      'prix' => 'price',
        'devise' => 'currency',        'currency' => 'currency',
        'tva' => 'vat_rate',           'vat' => 'vat_rate',
        'ordre' => 'position',         'position' => 'position',
    ];

    /** FR and EN words the dealer may type, mapped onto the stored value. */
    private const TYPES = [
        'open' => 'open', 'cabin' => 'cabin', 'cabine' => 'cabin',
        'semi-rigide' => 'semi-rigid', 'semi rigide' => 'semi-rigid', 'semirigide' => 'semi-rigid',
        'semi-rigid' => 'semi-rigid', 'pneumatique' => 'semi-rigid',
        'day-cruiser' => 'day-cruiser', 'day cruiser' => 'day-cruiser', 'daycruiser' => 'day-cruiser',
        'peche' => 'fishing', 'fishing' => 'fishing',
        'voilier' => 'sail', 'voile' => 'sail', 'sail' => 'sail',
    ];

    private const PROPULSIONS = [
        'hors-bord' => 'outboard', 'hors bord' => 'outboard', 'horsbord' => 'outboard', 'outboard' => 'outboard',
        'in-board' => 'inboard', 'in board' => 'inboard', 'inboard' => 'inboard', 'embase' => 'inboard',
        'z-drive' => 'inboard', 'voile' => 'sail', 'sail' => 'sail', 'voilier' => 'sail',
    ];

    /* ============================================================== PARSE */

    /**
     * Read the file and work out what importing it would do. Writes nothing.
     *
     * @return array{plan: array, summary: array, errors: array, warnings: array}
     */
    public function parse(UploadedFile $file, string $companyId): array
    {
        $sheets = $this->readSheets($file);

        $boatRows   = Xlsx::sheet($sheets, [BoatCatalogueExporter::SHEET_BOATS, 'Boats', 'Sheet1']);
        $optionRows = Xlsx::sheet($sheets, [BoatCatalogueExporter::SHEET_OPTIONS, 'Options']);

        // A single-sheet file is a boat sheet: that is the one people hand-type.
        if (! $boatRows && ! $optionRows && $sheets) {
            $boatRows = reset($sheets);
        }

        $errors = []; $warnings = [];

        if (count($boatRows) + count($optionRows) > self::MAX_ROWS + 2) {
            $errors[] = ['sheet' => '', 'row' => 0, 'message' => __('File is too large — :max rows maximum.', ['max' => self::MAX_ROWS])];
            return $this->empty($errors);
        }

        $boats = $this->parseBoats($boatRows, $errors, $warnings);
        $this->parseOptions($optionRows, $boats, $errors, $warnings);

        if (! $boats && ! $errors) {
            $errors[] = ['sheet' => BoatCatalogueExporter::SHEET_BOATS, 'row' => 0, 'message' => __('No usable rows found. Check the column headers.')];
        }

        return $this->plan($boats, $companyId, $errors, $warnings);
    }

    /** @return array<string, array> keyed by a stable within-file model key */
    private function parseBoats(array $rows, array &$errors, array &$warnings): array
    {
        if (! $rows) return [];

        $map = $this->mapHeaders(array_shift($rows), self::BOAT_ALIASES);
        $has = array_flip($map);

        foreach (['brand', 'model'] as $required) {
            if (! isset($has[$required])) {
                $errors[] = ['sheet' => BoatCatalogueExporter::SHEET_BOATS, 'row' => 1,
                    'message' => __('Missing required column: :col', ['col' => $required === 'brand' ? 'MARQUE' : 'MODELE'])];
                return [];
            }
        }

        $boats = [];

        foreach ($rows as $i => $raw) {
            $line = $i + 2;
            $r    = $this->row($raw, $map);

            if ($this->blank($r)) continue;

            $brand = trim((string) ($r['brand'] ?? ''));
            $name  = trim((string) ($r['model'] ?? ''));
            $ref   = trim((string) ($r['model_ref'] ?? ''));

            if ($brand === '' || $name === '') {
                $errors[] = ['sheet' => BoatCatalogueExporter::SHEET_BOATS, 'row' => $line,
                    'message' => __('MARQUE and MODELE are both required.')];
                continue;
            }

            $key = $ref !== '' ? 'ref:' . $ref : 'name:' . mb_strtolower($brand) . '|' . mb_strtolower($name);

            // Validate the whole row before recording any of it. A row that is
            // reported as rejected must not also half-import: telling the
            // dealer a row was skipped and then creating its version anyway is
            // worse than either outcome on its own.
            $fields = $this->boatFields($r, $has, $line, $errors);
            if ($fields === null) continue;

            // No VERSION column, or an empty one: the row describes the boat only.
            $vName = isset($has['variant']) ? trim((string) ($r['variant'] ?? '')) : '';
            $vRef  = isset($has['variant_ref']) ? trim((string) ($r['variant_ref'] ?? '')) : '';
            $version = null;
            if ($vName !== '' || $vRef !== '') {
                $version = $this->versionFields($r, $has, $line, $errors);
                if ($version === null) continue;
            }

            if (! isset($boats[$key])) {
                $boats[$key] = ['ref' => $ref, 'brand' => $brand, 'name' => $name,
                                'fields' => [], 'versions' => [], 'options' => [], 'row' => $line];
            }

            // Boat-level columns repeat down the version rows. The first row
            // wins; a later row that disagrees is reported rather than silently
            // overwriting, because that is nearly always a copy-paste slip.
            foreach ($fields as $f => $v) {
                if (! array_key_exists($f, $boats[$key]['fields'])) {
                    $boats[$key]['fields'][$f] = $v;
                } elseif ($boats[$key]['fields'][$f] !== $v) {
                    $warnings[] = ['sheet' => BoatCatalogueExporter::SHEET_BOATS, 'row' => $line,
                        'message' => __(':boat — :col differs from the first row for this boat; the first one is used.', [
                            'boat' => $brand . ' ' . $name, 'col' => strtoupper($f)])];
                }
            }

            if ($version !== null) {
                $boats[$key]['versions'][] = ['ref' => $vRef, 'name' => $vName, 'fields' => $version, 'row' => $line];
            }
        }

        return $boats;
    }

    /** @return array<string, mixed>|null null when the row is invalid */
    private function versionFields(array $r, array $has, int $line, array &$errors): ?array
    {
        $out = [];

        if (isset($has['base_price'])) {
            $p = $this->money($r['base_price'] ?? '');
            if ($p === null || $p < 0) {
                $errors[] = ['sheet' => BoatCatalogueExporter::SHEET_BOATS, 'row' => $line,
                    'message' => __('PRIX HT must be a number of 0 or more.')];
                return null;
            }
            $out['base_price'] = $p;
        }
        if (isset($has['cost'])) {
            $c = $this->money($r['cost'] ?? '');
            if ($c !== null && $c < 0) {
                $errors[] = ['sheet' => BoatCatalogueExporter::SHEET_BOATS, 'row' => $line,
                    'message' => __('COUT HT cannot be negative.')];
                return null;
            }
            $out['cost'] = $c ?? 0.0;
        }
        if (isset($has['currency'])) {
            $cur = strtoupper(trim((string) ($r['currency'] ?? ''))) ?: 'EUR';
            if (! in_array($cur, ['EUR', 'USD'], true)) {
                $errors[] = ['sheet' => BoatCatalogueExporter::SHEET_BOATS, 'row' => $line,
                    'message' => __('DEVISE must be EUR or USD.')];
                return null;
            }
            $out['currency'] = $cur;
        }
        if (isset($has['equipment'])) {
            $out['included_equipment'] = $this->equipment((string) ($r['equipment'] ?? ''));
        }
        if (isset($has['is_active'])) {
            $out['is_active'] = $this->bool($r['is_active'] ?? '', true);
        }

        return $out;
    }

    /** @return array<string, mixed>|null null when the row is invalid */
    private function boatFields(array $r, array $has, int $line, array &$errors): ?array
    {
        $out = [];
        $str = ['code', 'complement', 'supplier'];
        foreach ($str as $f) {
            if (isset($has[$f])) $out[$f] = trim((string) ($r[$f] ?? ''));
        }
        foreach (['length_total', 'beam', 'draft_max', 'weight'] as $f) {
            if (isset($has[$f])) {
                $n = $this->money($r[$f] ?? '');
                $out[$f] = $n === null ? null : round($n, 3);
            }
        }
        if (isset($has['year'])) {
            $y = trim((string) ($r['year'] ?? ''));
            if ($y === '') {
                $out['year'] = null;
            } elseif (! ctype_digit($y) || (int) $y < 1900 || (int) $y > 2100) {
                $errors[] = ['sheet' => BoatCatalogueExporter::SHEET_BOATS, 'row' => $line,
                    'message' => __('ANNEE must be a year between 1900 and 2100.')];
                return null;
            } else {
                $out['year'] = (int) $y;
            }
        }
        if (isset($has['type'])) {
            $out['type'] = $this->lookup($r['type'] ?? '', self::TYPES, 'unknown');
        }
        if (isset($has['propulsion'])) {
            $out['propulsion'] = $this->lookup($r['propulsion'] ?? '', self::PROPULSIONS, 'unknown');
        }
        return $out;
    }

    private function parseOptions(array $rows, array &$boats, array &$errors, array &$warnings): void
    {
        if (! $rows) return;

        $map = $this->mapHeaders(array_shift($rows), self::OPTION_ALIASES);
        $has = array_flip($map);

        if (! isset($has['label'])) {
            $errors[] = ['sheet' => BoatCatalogueExporter::SHEET_OPTIONS, 'row' => 1,
                'message' => __('Missing required column: :col', ['col' => 'DESIGNATION'])];
            return;
        }

        // The rate is fetched once for the whole file, not once per row.
        $rate = null;

        foreach ($rows as $i => $raw) {
            $line = $i + 2;
            $r    = $this->row($raw, $map);
            if ($this->blank($r)) continue;

            $label = trim((string) ($r['label'] ?? ''));
            if ($label === '') {
                $errors[] = ['sheet' => BoatCatalogueExporter::SHEET_OPTIONS, 'row' => $line,
                    'message' => __('DESIGNATION is required.')];
                continue;
            }

            $ref   = trim((string) ($r['model_ref'] ?? ''));
            $brand = trim((string) ($r['brand'] ?? ''));
            $model = trim((string) ($r['model'] ?? ''));
            $key   = $ref !== '' ? 'ref:' . $ref : 'name:' . mb_strtolower($brand) . '|' . mb_strtolower($model);

            if ($ref === '' && ($brand === '' || $model === '')) {
                $errors[] = ['sheet' => BoatCatalogueExporter::SHEET_OPTIONS, 'row' => $line,
                    'message' => __('An option needs MARQUE and MODELE (or REF MODELE) to say which boat it belongs to.')];
                continue;
            }

            $fields = [];
            if (isset($has['category']))    $fields['category']    = trim((string) ($r['category'] ?? ''));
            if (isset($has['description'])) $fields['description'] = trim((string) ($r['description'] ?? ''));
            if (isset($has['position']))    $fields['position']    = (int) ($this->money($r['position'] ?? '') ?? 0);
            if (isset($has['vat_rate'])) {
                $v = $this->money($r['vat_rate'] ?? '');
                // "0.2" and "20" both mean twenty percent.
                $fields['vat_rate'] = $v === null ? 20.0 : ($v > 0 && $v <= 1 ? round($v * 100, 2) : round($v, 2));
            }

            $currency = isset($has['currency'])
                ? (strtoupper(trim((string) ($r['currency'] ?? ''))) ?: 'EUR') : 'EUR';
            if (! in_array($currency, ['EUR', 'USD'], true)) {
                $errors[] = ['sheet' => BoatCatalogueExporter::SHEET_OPTIONS, 'row' => $line,
                    'message' => __('DEVISE must be EUR or USD.')];
                continue;
            }

            if ($currency !== 'EUR' && $rate === null) {
                $rate = $this->fx->rate($currency, 'EUR');
                if ($rate === null) {
                    $errors[] = ['sheet' => BoatCatalogueExporter::SHEET_OPTIONS, 'row' => $line,
                        'message' => __('Could not fetch the :cur → EUR rate; leave prices in EUR or try again.', ['cur' => $currency])];
                    continue;
                }
            }
            $toEur = fn (?float $n) => $n === null ? null : round($currency === 'EUR' ? $n : $n * $rate, 2);

            foreach (['price' => 'price', 'cost' => 'cost'] as $col => $field) {
                if (! isset($has[$col])) continue;
                $n = $this->money($r[$col] ?? '');
                if ($n !== null && $n < 0) {
                    $errors[] = ['sheet' => BoatCatalogueExporter::SHEET_OPTIONS, 'row' => $line,
                        'message' => __(':col cannot be negative.', ['col' => strtoupper($col)])];
                    continue 2;
                }
                $fields[$field] = $toEur($n) ?? 0.0;
            }
            if ($currency !== 'EUR') {
                $fields['currency']               = 'EUR';
                $fields['original_price']         = isset($has['price']) ? $this->money($r['price'] ?? '') : null;
                $fields['original_price_currency'] = $currency;
                $fields['original_cost']          = isset($has['cost']) ? $this->money($r['cost'] ?? '') : null;
                $fields['original_cost_currency'] = $currency;
                $fields['fx_rate_used']           = $rate;
                $fields['fx_rate_date']           = now()->toDateString();
            }

            if (! isset($boats[$key])) {
                // The boat is not in this file. It may still exist in the
                // catalogue — resolved at plan time — so keep the row and let
                // the planner decide.
                $boats[$key] = ['ref' => $ref, 'brand' => $brand, 'name' => $model,
                                'fields' => [], 'versions' => [], 'options' => [], 'row' => $line,
                                'options_only' => true];
            }

            $boats[$key]['options'][] = [
                'code'   => trim((string) ($r['code'] ?? '')),
                'label'  => $label,
                'fields' => $fields,
                'row'    => $line,
            ];
        }
    }

    /* =============================================================== PLAN */

    /**
     * Resolve every parsed row against what is already in the catalogue and
     * describe the outcome. Still writes nothing.
     */
    private function plan(array $boats, string $companyId, array $errors, array $warnings): array
    {
        $brands = CompanyBrand::withoutGlobalScopes()->where('company_id', $companyId)->get();
        $byName = $brands->keyBy(fn ($b) => mb_strtolower(trim((string) $b->name)));

        $plan = []; $newBrands = [];
        $c = ['brands' => 0, 'models' => 0, 'versions' => 0, 'options' => 0];
        $u = ['models' => 0, 'versions' => 0, 'options' => 0, 'unchanged' => 0];

        foreach ($boats as $key => $boat) {
            $entry = ['brand' => $boat['brand'], 'name' => $boat['name'], 'row' => $boat['row'],
                      'fields' => $boat['fields'], 'versions' => [], 'options' => []];

            /* -- brand ------------------------------------------------- */
            $brandKey = mb_strtolower(trim($boat['brand']));
            $existingBrand = $byName[$brandKey] ?? null;
            if ($existingBrand) {
                $entry['brand_id'] = (string) $existingBrand->_id;
                $entry['brand_action'] = 'existing';
            } elseif ($brandKey === '') {
                $entry['brand_id'] = null;
                $entry['brand_action'] = 'existing';
            } else {
                $entry['brand_id'] = null;
                $entry['brand_action'] = 'create';
                if (! isset($newBrands[$brandKey])) { $newBrands[$brandKey] = true; $c['brands']++; }
            }

            /* -- model ------------------------------------------------- */
            $model = null;
            if ($boat['ref'] !== '') {
                $model = CompanyBoatModel::withoutGlobalScopes()
                    ->where('company_id', $companyId)->where('_id', $boat['ref'])->first();
            }
            if (! $model && $entry['brand_id']) {
                $model = CompanyBoatModel::withoutGlobalScopes()
                    ->where('company_id', $companyId)
                    ->where('company_brand_id', $entry['brand_id'])
                    ->where('is_archived', false)
                    ->get()
                    ->first(fn ($m) => mb_strtolower(trim((string) $m->name)) === mb_strtolower(trim($boat['name'])));
            }

            if ($model) {
                $entry['model_id'] = (string) $model->_id;
                $changed = $this->diff($model, $boat['fields']);
                $entry['model_action']  = $changed ? 'update' : 'unchanged';
                $entry['model_changes'] = $changed;
                $changed ? $u['models']++ : $u['unchanged']++;
            } elseif (! empty($boat['options_only'])) {
                // Options pointing at a boat that exists in neither the file
                // nor the catalogue. Report and drop them; inventing a boat
                // from an option row would be a guess.
                foreach ($boat['options'] as $o) {
                    $errors[] = ['sheet' => BoatCatalogueExporter::SHEET_OPTIONS, 'row' => $o['row'],
                        'message' => __('No boat ":boat" in the catalogue or in this file.', [
                            'boat' => trim($boat['brand'] . ' ' . $boat['name'])])];
                }
                continue;
            } else {
                $entry['model_id'] = null;
                $entry['model_action'] = 'create';
                $entry['model_changes'] = [];
                $c['models']++;
            }

            /* -- versions ---------------------------------------------- */
            $existingVariants = $entry['model_id']
                ? CompanyBoatVariant::withoutGlobalScopes()->where('company_id', $companyId)
                    ->where('company_model_id', $entry['model_id'])->get()
                : collect();

            foreach ($boat['versions'] as $v) {
                $match = null;
                if ($v['ref'] !== '') {
                    $match = $existingVariants->first(fn ($x) => (string) $x->_id === $v['ref']);
                }
                if (! $match && $v['name'] !== '') {
                    $match = $existingVariants->first(
                        fn ($x) => mb_strtolower(trim((string) $x->name)) === mb_strtolower($v['name']));
                }

                if ($match) {
                    $changed = $this->diff($match, $v['fields']);
                    $entry['versions'][] = ['id' => (string) $match->_id, 'name' => $v['name'] ?: (string) $match->name,
                        'action' => $changed ? 'update' : 'unchanged', 'changes' => $changed,
                        'fields' => $v['fields'], 'row' => $v['row']];
                    $changed ? $u['versions']++ : $u['unchanged']++;
                } else {
                    if ($v['name'] === '') {
                        $errors[] = ['sheet' => BoatCatalogueExporter::SHEET_BOATS, 'row' => $v['row'],
                            'message' => __('REF VERSION does not match any version, and VERSION is empty.')];
                        continue;
                    }
                    if (! isset($v['fields']['base_price'])) {
                        $errors[] = ['sheet' => BoatCatalogueExporter::SHEET_BOATS, 'row' => $v['row'],
                            'message' => __('A new version needs PRIX HT.')];
                        continue;
                    }
                    $entry['versions'][] = ['id' => null, 'name' => $v['name'], 'action' => 'create',
                        'changes' => [], 'fields' => $v['fields'], 'row' => $v['row']];
                    $c['versions']++;
                }
            }

            /* -- options ----------------------------------------------- */
            $existingOptions = $entry['model_id']
                ? CompanyOption::withoutGlobalScopes()->where('company_id', $companyId)
                    ->where('company_model_id', $entry['model_id'])->get()
                : collect();

            foreach ($boat['options'] as $o) {
                $match = null;
                if ($o['code'] !== '') {
                    $match = $existingOptions->first(
                        fn ($x) => mb_strtolower(trim((string) $x->code)) === mb_strtolower($o['code']));
                }
                if (! $match) {
                    $cat = mb_strtolower(trim((string) ($o['fields']['category'] ?? '')));
                    $match = $existingOptions->first(fn ($x) =>
                        mb_strtolower(trim((string) $x->label)) === mb_strtolower($o['label'])
                        && ($cat === '' || mb_strtolower(trim((string) $x->category)) === $cat));
                }

                if ($match) {
                    $changed = $this->diff($match, $o['fields']);
                    $entry['options'][] = ['id' => (string) $match->_id, 'code' => $o['code'], 'label' => $o['label'],
                        'action' => $changed ? 'update' : 'unchanged', 'changes' => $changed,
                        'fields' => $o['fields'], 'row' => $o['row']];
                    $changed ? $u['options']++ : $u['unchanged']++;
                } else {
                    if (! isset($o['fields']['price'])) {
                        $errors[] = ['sheet' => BoatCatalogueExporter::SHEET_OPTIONS, 'row' => $o['row'],
                            'message' => __('A new option needs PV HT.')];
                        continue;
                    }
                    $entry['options'][] = ['id' => null, 'code' => $o['code'], 'label' => $o['label'],
                        'action' => 'create', 'changes' => [], 'fields' => $o['fields'], 'row' => $o['row']];
                    $c['options']++;
                }
            }

            $plan[] = $entry;
        }

        return [
            'plan'     => $plan,
            'errors'   => $errors,
            'warnings' => $warnings,
            'summary'  => ['create' => $c, 'update' => $u, 'errors' => count($errors), 'warnings' => count($warnings)],
        ];
    }

    /** Fields whose new value differs from what is stored. @return array<string, array{0: mixed, 1: mixed}> */
    private function diff($record, array $fields): array
    {
        $out = [];
        foreach ($fields as $f => $new) {
            $old = $record->{$f} ?? null;

            if ($f === 'included_equipment') {
                $flat = fn ($l) => collect($l ?? [])
                    ->map(fn ($e) => is_array($e) ? (string) ($e['label'] ?? '') : (string) $e)->all();
                if ($flat($old) !== $flat($new)) $out[$f] = [implode(' | ', $flat($old)), implode(' | ', $flat($new))];
                continue;
            }
            if (is_float($new) || is_int($new)) {
                if (round((float) $old, 3) !== round((float) $new, 3)) $out[$f] = [$old, $new];
                continue;
            }
            if (is_bool($new)) {
                if ((bool) $old !== $new) $out[$f] = [$old ? 'oui' : 'non', $new ? 'oui' : 'non'];
                continue;
            }
            if ((string) $old !== (string) $new) $out[$f] = [$old, $new];
        }
        return $out;
    }

    /* ============================================================= COMMIT */

    /**
     * Apply a plan produced by parse(). The plan carries ids, so a record
     * deleted between preview and confirm is re-resolved rather than
     * resurrected: a missing id falls through to create.
     */
    public function commit(array $parsed, string $companyId): array
    {
        $c = ['brands' => 0, 'models' => 0, 'versions' => 0, 'options' => 0];
        $u = ['models' => 0, 'versions' => 0, 'options' => 0];

        $brandCache = [];

        foreach ($parsed['plan'] as $entry) {
            /* -- brand -------------------------------------------------- */
            $brandId = $entry['brand_id'] ?? null;
            if (! $brandId && ($entry['brand_action'] ?? '') === 'create') {
                $key = mb_strtolower(trim($entry['brand']));
                if (isset($brandCache[$key])) {
                    $brandId = $brandCache[$key];
                } else {
                    $brand = CompanyBrand::create([
                        'company_id' => $companyId, 'global_brand_id' => null,
                        'source' => CompanyBrand::SOURCE_PRIVATE, 'name' => trim($entry['brand']),
                        'is_active' => true, 'activated_at' => now(),
                    ]);
                    $brandId = $brandCache[$key] = (string) $brand->_id;
                    $c['brands']++;
                }
            }

            /* -- model -------------------------------------------------- */
            $model = $entry['model_id']
                ? CompanyBoatModel::withoutGlobalScopes()->where('company_id', $companyId)
                    ->where('_id', $entry['model_id'])->first()
                : null;

            if ($model) {
                // source and global_model_id are deliberately untouched: a boat
                // copied from the platform catalogue stays linked to it, the
                // dealer just owns their copy of the numbers.
                $changes = $this->writable($entry['model_changes']);
                if ($brandId && (string) $model->company_brand_id !== $brandId) {
                    $changes['company_brand_id'] = $brandId;
                }
                if ($changes) { $model->update($changes); $u['models']++; }
            } else {
                $model = CompanyBoatModel::create(array_merge(
                    $entry['fields'] ?? [],
                    ['company_id' => $companyId, 'company_brand_id' => $brandId, 'global_model_id' => null,
                     'source' => CompanyBoatModel::SOURCE_PRIVATE, 'name' => $entry['name'],
                     'is_active' => true, 'is_archived' => false]
                ));
                $c['models']++;
            }
            $modelId = (string) $model->_id;

            /* -- versions ----------------------------------------------- */
            foreach ($entry['versions'] as $v) {
                $variant = $v['id']
                    ? CompanyBoatVariant::withoutGlobalScopes()->where('company_id', $companyId)
                        ->where('_id', $v['id'])->first()
                    : null;

                if ($variant) {
                    if ($v['changes']) { $variant->update($v['fields']); $u['versions']++; }
                } else {
                    CompanyBoatVariant::create(array_merge([
                        'company_id' => $companyId, 'company_model_id' => $modelId,
                        'global_variant_id' => null, 'source' => 'private',
                        'name' => $v['name'], 'base_price' => 0.0, 'cost' => 0.0, 'currency' => 'EUR',
                        'included_equipment' => [], 'is_active' => true, 'is_archived' => false,
                    ], $v['fields']));
                    $c['versions']++;
                }
            }

            /* -- options ------------------------------------------------ */
            foreach ($entry['options'] as $o) {
                $option = $o['id']
                    ? CompanyOption::withoutGlobalScopes()->where('company_id', $companyId)
                        ->where('_id', $o['id'])->first()
                    : null;

                if ($option) {
                    if ($o['changes']) { $option->update($o['fields']); $u['options']++; }
                } else {
                    $code = $o['code'] !== '' ? $o['code']
                        : Str::slug((string) ($o['fields']['category'] ?? '')) . '__' . Str::slug($o['label']);
                    CompanyOption::create(array_merge([
                        'company_id' => $companyId, 'company_model_id' => $modelId,
                        'global_option_id' => null, 'source' => 'private',
                        'category' => '', 'label' => $o['label'], 'code' => $code,
                        'price' => 0.0, 'cost' => 0.0, 'vat_rate' => 20, 'currency' => 'EUR',
                        'position' => 0, 'is_archived' => false,
                    ], $o['fields']));
                    $c['options']++;
                }
            }
        }

        return ['created' => $c, 'updated' => $u];
    }

    /** Changes are stored as [old, new] pairs for the preview; commit wants the new values. */
    private function writable(array $changes): array
    {
        return array_map(fn ($pair) => $pair[1], $changes);
    }

    /* ============================================================ HELPERS */

    private function readSheets(UploadedFile $file): array
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension());
        if (in_array($ext, ['csv', 'txt'], true)) {
            return [BoatCatalogueExporter::SHEET_BOATS => $this->readCsv($file->getRealPath())];
        }
        return Xlsx::read($file->getRealPath());
    }

    private function readCsv(string $path): array
    {
        $rows = [];
        if (($h = fopen($path, 'r')) === false) return $rows;
        $first = true;
        while (($line = fgets($h)) !== false) {
            if ($first) { $line = preg_replace('/^\xEF\xBB\xBF/', '', $line); $first = false; }
            // French exports use ; far more often than , — pick whichever the
            // header line actually contains more of.
            $sep = substr_count($line, ';') > substr_count($line, ',') ? ';' : ',';
            $rows[] = str_getcsv(rtrim($line, "\r\n"), $sep);
        }
        fclose($h);
        return $rows;
    }

    /** @return array<int, string> column index → field */
    private function mapHeaders(array $headerRow, array $aliases): array
    {
        $map = [];
        foreach ($headerRow as $i => $h) {
            $k = trim(mb_strtolower(Str::ascii((string) $h)));
            $k = preg_replace('/\s+/', ' ', $k);
            if (isset($aliases[$k])) $map[$i] = $aliases[$k];
        }
        return $map;
    }

    /** @return array<string, string> field => raw cell */
    private function row(array $raw, array $map): array
    {
        $out = [];
        foreach ($map as $i => $field) {
            // A row can be short: the writer omits trailing empty cells.
            $out[$field] = isset($raw[$i]) ? trim((string) $raw[$i]) : '';
        }
        return $out;
    }

    private function blank(array $row): bool
    {
        foreach ($row as $v) if (trim((string) $v) !== '') return false;
        return true;
    }

    /** "12 500,50 €" → 12500.5. Null when the cell is empty or not a number. */
    private function money($raw): ?float
    {
        $s = trim((string) $raw);
        if ($s === '') return null;
        $s = str_replace(["\xc2\xa0", ' ', '€', '$', 'EUR', 'USD'], '', $s);
        // 1.234,56 (fr) vs 1,234.56 (en): the last separator is the decimal one.
        if (str_contains($s, ',') && str_contains($s, '.')) {
            $s = strrpos($s, ',') > strrpos($s, '.')
                ? str_replace(['.', ','], ['', '.'], $s)
                : str_replace(',', '', $s);
        } else {
            $s = str_replace(',', '.', $s);
        }
        return is_numeric($s) ? round((float) $s, 2) : null;
    }

    private function bool($raw, bool $default): bool
    {
        $s = mb_strtolower(trim((string) $raw));
        if ($s === '') return $default;
        return in_array($s, ['oui', 'yes', 'y', 'o', '1', 'true', 'vrai', 'actif', 'active'], true);
    }

    /** @return array<int, array{label: string, type: string}> */
    private function equipment(string $raw): array
    {
        if (trim($raw) === '') return [];
        return collect(preg_split('/\s*[|;\r\n]\s*/u', $raw))
            ->map(fn ($l) => trim((string) $l))
            ->filter(fn ($l) => $l !== '')
            ->map(fn ($l) => ['label' => $l, 'type' => 'standard'])
            ->values()->all();
    }

    private function lookup($raw, array $table, string $fallback): string
    {
        $k = trim(mb_strtolower(Str::ascii((string) $raw)));
        if ($k === '') return $fallback;
        return $table[$k] ?? $fallback;
    }

    private function empty(array $errors): array
    {
        return ['plan' => [], 'errors' => $errors, 'warnings' => [],
                'summary' => ['create' => ['brands' => 0, 'models' => 0, 'versions' => 0, 'options' => 0],
                              'update' => ['models' => 0, 'versions' => 0, 'options' => 0, 'unchanged' => 0],
                              'errors' => count($errors), 'warnings' => 0]];
    }
}
