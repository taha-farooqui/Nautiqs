<?php

namespace App\Services\Brand;

use App\Models\BrandAccount;
use App\Models\BrandEngine;
use App\Models\BrandEngineAccessory;
use App\Models\BrandModel;
use App\Models\BrandOption;
use App\Models\BrandVariant;
use App\Models\ManagedBrand;
use App\Services\Xlsx;
use Illuminate\Support\Str;

/**
 * Excel export/import of a brand account's catalogue.
 *
 *   boat account    BATEAUX (one row per version) + OPTIONS (one row per option)
 *   engine account  MOTEURS + KITS (kits and propellers, TYPE column)
 *
 * Prices: PRIX PUBLIC HT, then NIVEAU 1..3 HT. A level cell left empty means
 * "use the level %", exactly like an empty box in the editor.
 *
 * Import only creates and updates, matching on names (brand → model →
 * version / famille + désignation; brand → engine code; type + désignation).
 * It never archives or deletes, so a file with a single price list in it
 * cannot wipe the rest of the catalogue. MARQUE must be one of the account's
 * brands — the admin creates brands, an import never does.
 */
class BrandTransfer
{
    public const L = ['NIVEAU 1 HT', 'NIVEAU 2 HT', 'NIVEAU 3 HT'];

    public function export(BrandAccount $account, bool $template = false): string
    {
        $brands = ManagedBrand::orderBy('position')->orderBy('name')->get();
        $name = fn ($id) => optional($brands->first(fn ($b) => (string) $b->_id === (string) $id))->name ?? '';
        $lv = fn ($item) => array_map(fn ($n) => $item->exactLevelPrice($n), [1, 2, 3]);

        if ($account->isEngine()) {
            $engines = $template ? collect() : BrandEngine::where('is_archived', '!=', true)->orderBy('horsepower')->get();
            $accs = $template ? collect() : BrandEngineAccessory::orderBy('type')->orderBy('label')->get();
            $codes = $engines->mapWithKeys(fn ($e) => [(string) $e->_id => $e->code]);
            $first = $brands->first()->name ?? 'MARQUE';

            $eRows = $engines->map(fn ($e) => [$name($e->managed_brand_id), $e->code, $e->horsepower, $e->fuel, (float) $e->price, (float) ($e->vat_rate ?? 20), ...$lv($e), $e->description])->all();
            $linked = [];
            foreach ($engines as $e) foreach ($e->accessoryIds() as $aid) $linked[$aid][] = $e->code;
            $aRows = $accs->map(fn ($a) => [$a->type === 'propeller' ? 'HELICE' : 'KIT', $name($a->managed_brand_id), $a->label, $a->reference, (float) $a->price, (float) ($a->vat_rate ?? 20), ...$lv($a), implode('; ', $linked[(string) $a->_id] ?? [])])->all();
            if ($template) {
                $eRows = [[$first, 'DF150APX', 150, 'Essence', 18990, 20, 15200, null, null, '4 temps, injection']];
                $aRows = [['KIT', $first, 'Kit de commande DF150', 'KIT-150', 1250, 20, null, null, null, 'DF150APX'],
                          ['HELICE', $first, 'Hélice inox 15 1/4 x 20', 'H-1520', 690, 20, null, null, null, 'DF150APX']];
            }

            return Xlsx::write([
                'MOTEURS' => ['headers' => ['MARQUE', 'MODELE', 'PUISSANCE', 'CARBURANT', 'PRIX PUBLIC HT', 'TVA', ...self::L, 'DESCRIPTION'], 'rows' => $eRows, 'widths' => [18, 22, 11, 12, 15, 7, 14, 14, 14, 50]],
                'KITS'    => ['headers' => ['TYPE', 'MARQUE', 'DESIGNATION', 'REFERENCE', 'PRIX PUBLIC HT', 'TVA', ...self::L, 'MOTEURS'], 'rows' => $aRows, 'widths' => [9, 18, 44, 16, 15, 7, 14, 14, 14, 40]],
            ], 'nautiqs-brand-');
        }

        $models = $template ? collect() : BrandModel::where('is_archived', '!=', true)->orderBy('position')->orderBy('name')->get();
        $bRows = []; $oRows = [];
        foreach ($models as $m) {
            $vs = BrandVariant::where('brand_model_id', (string) $m->_id)->where('is_archived', '!=', true)->orderBy('position')->get();
            foreach ($vs as $v) {
                $eq = collect($v->included_equipment ?? [])->map(fn ($e) => is_array($e) ? ($e['label'] ?? '') : (string) $e)->filter()->implode('; ');
                $bRows[] = [$name($m->managed_brand_id), $m->name, $v->name, (float) $v->price, ...$lv($v), $eq];
            }
            if ($vs->isEmpty()) $bRows[] = [$name($m->managed_brand_id), $m->name, null, null, null, null, null, null];
            foreach (BrandOption::where('brand_model_id', (string) $m->_id)->where('is_archived', '!=', true)->orderBy('position')->get() as $o) {
                $oRows[] = [$name($m->managed_brand_id), $m->name, $o->category, $o->label, $o->description, (float) $o->price, (float) ($o->vat_rate ?? 20), ...$lv($o)];
            }
        }
        if ($template) {
            $first = $brands->first()->name ?? 'MARQUE';
            $bRows = [[$first, 'Nordvik 690', 'Open', 52900, 44900, null, null, 'Taud de mouillage; Échelle de bain'],
                      [$first, 'Nordvik 690', 'Cabin', 58900, null, null, null, 'Taud de mouillage; Couchette double']];
            $oRows = [[$first, 'Nordvik 690', 'Confort', 'Bimini', 'Bimini inox, toile grise', 1450, 20, null, null, null]];
        }

        return Xlsx::write([
            'BATEAUX' => ['headers' => ['MARQUE', 'MODELE', 'VERSION', 'PRIX PUBLIC HT', ...self::L, 'EQUIPEMENTS INCLUS'], 'rows' => $bRows, 'widths' => [18, 28, 22, 15, 14, 14, 14, 70]],
            'OPTIONS' => ['headers' => ['MARQUE', 'MODELE', 'FAMILLE', 'DESIGNATION', 'DESCRIPTION', 'PRIX PUBLIC HT', 'TVA', ...self::L], 'rows' => $oRows, 'widths' => [18, 28, 18, 40, 50, 15, 7, 14, 14, 14]],
        ], 'nautiqs-brand-');
    }

    /**
     * @return array{created: int, updated: int, errors: array<int, string>}
     */
    public function import(BrandAccount $account, string $path): array
    {
        $book = Xlsx::read($path);
        $this->brands = ManagedBrand::all()->mapWithKeys(fn ($b) => [$this->key($b->name) => $b]);
        $this->out = ['created' => 0, 'updated' => 0, 'errors' => []];

        if ($account->isEngine()) {
            $this->importEngines($this->rows(Xlsx::sheet($book, ['MOTEURS', 'ENGINES'])));
            // Xlsx::sheet falls back to the first sheet, so only read KITS when it is really there.
            $kits = ['KITS', 'KITS HELICES', 'ACCESSOIRES'];
            if ($this->find($book, $kits)) $this->importAccessories($this->rows(Xlsx::sheet($book, $kits)));
        } else {
            $this->importBoats($this->rows(Xlsx::sheet($book, ['BATEAUX', 'BOATS'])));
            if ($this->find($book, ['OPTIONS'])) $this->importOptions($this->rows(Xlsx::sheet($book, ['OPTIONS'])));
        }

        return $this->out;
    }

    /* ------------------------------------------------------------- import */

    private $brands;
    private array $out;

    private function importBoats(array $rows): void
    {
        $pos = [];
        foreach ($rows as $line => $r) {
            if (! ($brand = $this->brandOf($r, $line, 'BATEAUX'))) continue;
            $modelName = trim($r['MODELE'] ?? '');
            if ($modelName === '') { $this->err('BATEAUX', $line, __('MODELE is empty.')); continue; }
            $model = $this->modelFor($brand, $modelName);

            $vName = trim($r['VERSION'] ?? '');
            if ($vName === '') continue;
            $price = $this->num($r['PRIX PUBLIC HT'] ?? $r['PRIX HT'] ?? null);
            if ($price === null) { $this->err('BATEAUX', $line, __('PRIX PUBLIC HT is missing for :v.', ['v' => $vName])); continue; }

            $mid = (string) $model->_id;
            $pos[$mid] = ($pos[$mid] ?? -1) + 1;
            $fields = ['name' => $vName, 'price' => round($price, 2), 'level_prices' => $this->levels($r), 'is_archived' => false];
            if (array_key_exists('EQUIPEMENTS INCLUS', $r)) {
                $fields['included_equipment'] = collect(preg_split('/[;|\r\n]+/', (string) $r['EQUIPEMENTS INCLUS']))->map(fn ($l) => trim($l))->filter()->values()
                    ->map(fn ($l) => ['label' => $l, 'type' => 'standard'])->all();
            }
            $v = BrandVariant::where('brand_model_id', $mid)->get()->first(fn ($x) => $this->key($x->name) === $this->key($vName));
            if ($v) {
                $v->update($fields); $this->out['updated']++;
            } else {
                BrandVariant::create($fields + ['brand_model_id' => $mid, 'managed_brand_id' => (string) $brand->_id, 'currency' => 'EUR', 'position' => $pos[$mid], 'included_equipment' => $fields['included_equipment'] ?? []]);
                $this->out['created']++;
            }
        }
    }

    private function importOptions(array $rows): void
    {
        foreach ($rows as $line => $r) {
            if (! ($brand = $this->brandOf($r, $line, 'OPTIONS'))) continue;
            $modelName = trim($r['MODELE'] ?? '');
            $label = trim($r['DESIGNATION'] ?? '');
            if ($modelName === '' || $label === '') { $this->err('OPTIONS', $line, __('MODELE and DESIGNATION are required.')); continue; }
            $price = $this->num($r['PRIX PUBLIC HT'] ?? $r['PV HT'] ?? null);
            if ($price === null) { $this->err('OPTIONS', $line, __('PRIX PUBLIC HT is missing for :v.', ['v' => $label])); continue; }
            $model = $this->modelFor($brand, $modelName);
            $mid = (string) $model->_id;
            $cat = trim($r['FAMILLE'] ?? '') ?: 'Options';

            $fields = ['category' => $cat, 'label' => $label, 'price' => round($price, 2), 'level_prices' => $this->levels($r), 'is_archived' => false];
            if (array_key_exists('DESCRIPTION', $r)) $fields['description'] = trim($r['DESCRIPTION']) ?: null;
            if (($vat = $this->num($r['TVA'] ?? null)) !== null) $fields['vat_rate'] = $vat;

            $o = BrandOption::where('brand_model_id', $mid)->get()
                ->first(fn ($x) => $this->key($x->label) === $this->key($label) && $this->key($x->category) === $this->key($cat));
            if ($o) {
                $o->update($fields); $this->out['updated']++;
            } else {
                BrandOption::create($fields + ['brand_model_id' => $mid, 'managed_brand_id' => (string) $brand->_id, 'currency' => 'EUR', 'vat_rate' => 20,
                    'position' => BrandOption::where('brand_model_id', $mid)->count()]);
                $this->out['created']++;
            }
        }
    }

    private function importEngines(array $rows): void
    {
        foreach ($rows as $line => $r) {
            if (! ($brand = $this->brandOf($r, $line, 'MOTEURS'))) continue;
            $code = trim($r['MODELE'] ?? '');
            if ($code === '') { $this->err('MOTEURS', $line, __('MODELE is empty.')); continue; }
            $price = $this->num($r['PRIX PUBLIC HT'] ?? null);
            if ($price === null) { $this->err('MOTEURS', $line, __('PRIX PUBLIC HT is missing for :v.', ['v' => $code])); continue; }

            $fields = ['managed_brand_id' => (string) $brand->_id, 'code' => $code, 'price' => round($price, 2), 'level_prices' => $this->levels($r), 'is_archived' => false];
            if (array_key_exists('PUISSANCE', $r)) $fields['horsepower'] = $this->num($r['PUISSANCE']);
            if (array_key_exists('CARBURANT', $r)) $fields['fuel'] = trim($r['CARBURANT']) ?: null;
            if (array_key_exists('DESCRIPTION', $r)) $fields['description'] = trim($r['DESCRIPTION']) ?: null;
            if (($vat = $this->num($r['TVA'] ?? null)) !== null) $fields['vat_rate'] = $vat;

            $e = BrandEngine::where('managed_brand_id', (string) $brand->_id)->get()->first(fn ($x) => $this->key($x->code) === $this->key($code));
            if ($e) {
                $e->update($fields); $this->out['updated']++;
            } else {
                BrandEngine::create($fields + ['currency' => 'EUR', 'vat_rate' => 20, 'accessory_ids' => []]);
                $this->out['created']++;
            }
        }
    }

    private function importAccessories(array $rows): void
    {
        $engines = BrandEngine::all();
        foreach ($rows as $line => $r) {
            if (! ($brand = $this->brandOf($r, $line, 'KITS'))) continue;
            $label = trim($r['DESIGNATION'] ?? '');
            if ($label === '') { $this->err('KITS', $line, __('DESIGNATION is empty.')); continue; }
            $price = $this->num($r['PRIX PUBLIC HT'] ?? null);
            if ($price === null) { $this->err('KITS', $line, __('PRIX PUBLIC HT is missing for :v.', ['v' => $label])); continue; }
            $type = in_array($this->key($r['TYPE'] ?? ''), ['helice', 'propeller', 'prop'], true) ? 'propeller' : 'kit';

            $fields = ['managed_brand_id' => (string) $brand->_id, 'type' => $type, 'label' => $label, 'price' => round($price, 2), 'level_prices' => $this->levels($r)];
            if (array_key_exists('REFERENCE', $r)) $fields['reference'] = trim($r['REFERENCE']) ?: null;
            if (($vat = $this->num($r['TVA'] ?? null)) !== null) $fields['vat_rate'] = $vat;

            $a = BrandEngineAccessory::where('type', $type)->get()->first(fn ($x) => $this->key($x->label) === $this->key($label));
            if ($a) {
                $a->update($fields); $this->out['updated']++;
            } else {
                $a = BrandEngineAccessory::create($fields + ['currency' => 'EUR', 'vat_rate' => 20]);
                $this->out['created']++;
            }

            // MOTEURS lists the engine codes this item fits; only added, never unlinked.
            if (! empty($r['MOTEURS'])) {
                $aid = (string) $a->_id;
                foreach (preg_split('/[;,|]+/', $r['MOTEURS']) as $c) {
                    $c = $this->key($c);
                    if ($c === '') continue;
                    $e = $engines->first(fn ($x) => $this->key($x->code) === $c);
                    if (! $e) { $this->err('KITS', $line, __('Unknown engine :c.', ['c' => trim($c)])); continue; }
                    if (! in_array($aid, $e->accessoryIds(), true)) {
                        $e->accessory_ids = array_values(array_merge($e->accessoryIds(), [$aid]));
                        $e->save();
                    }
                }
            }
        }
    }

    /* ------------------------------------------------------------ helpers */

    private function modelFor(ManagedBrand $brand, string $name): BrandModel
    {
        $m = BrandModel::where('managed_brand_id', (string) $brand->_id)->get()->first(fn ($x) => $this->key($x->name) === $this->key($name));
        if (! $m) {
            $m = BrandModel::create(['managed_brand_id' => (string) $brand->_id, 'name' => $name, 'is_archived' => false,
                'position' => BrandModel::where('managed_brand_id', (string) $brand->_id)->count()]);
            $this->out['created']++;
        } elseif ($m->is_archived) {
            $m->update(['is_archived' => false]);
        }
        return $m;
    }

    private function brandOf(array $r, int $line, string $sheet): ?ManagedBrand
    {
        $b = $this->brands[$this->key($r['MARQUE'] ?? '')] ?? null;
        if (! $b && $this->brands->count() === 1 && trim($r['MARQUE'] ?? '') === '') $b = $this->brands->first();
        if (! $b) $this->err($sheet, $line, __('Unknown brand ":b".', ['b' => trim($r['MARQUE'] ?? '')]));
        return $b;
    }

    /** Sheet rows keyed by header; line numbers are the spreadsheet's (header = 1). */
    private function rows(array $grid): array
    {
        if (! $grid) return [];
        $headers = array_map(fn ($h) => mb_strtoupper(trim(Str::ascii((string) $h))), array_shift($grid));
        $out = [];
        foreach ($grid as $i => $cells) {
            $row = [];
            foreach ($headers as $c => $h) if ($h !== '') $row[$h] = trim((string) ($cells[$c] ?? ''));
            if (implode('', $row) !== '') $out[$i + 2] = $row;
        }
        return $out;
    }

    private function find(array $book, array $names): array
    {
        $k = fn ($s) => preg_replace('/[^a-z0-9]/', '', mb_strtolower(Str::ascii($s)));
        $want = array_map($k, $names);
        return array_values(array_filter(array_keys($book), fn ($n) => in_array($k($n), $want, true)));
    }

    private function levels(array $r): array
    {
        return BrandVariant::cleanLevelPrices(array_map(fn ($h) => $r[$h] ?? null, self::L));
    }

    private function num($v): ?float
    {
        if ($v === null) return null;
        $v = str_replace([' ', "\u{00A0}", '€'], '', (string) $v);
        if ($v === '') return null;
        if (str_contains($v, ',') && ! str_contains($v, '.')) $v = str_replace(',', '.', $v);
        return is_numeric($v) ? (float) $v : null;
    }

    private function key(?string $s): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/', ' ', Str::ascii((string) $s))));
    }

    private function err(string $sheet, int $line, string $msg): void
    {
        if (count($this->out['errors']) < 50) $this->out['errors'][] = "$sheet " . __('line') . " $line: $msg";
    }
}
