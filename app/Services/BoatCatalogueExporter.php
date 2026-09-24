<?php

namespace App\Services;

use App\Models\CompanyBoatModel;
use App\Models\CompanyBoatVariant;
use App\Models\CompanyBrand;
use App\Models\CompanyOption;
use Illuminate\Support\Str;

/**
 * Writes a dealership's catalogue — boats, their versions, their included
 * equipment and their options — into one workbook that can be edited in Excel
 * and imported straight back.
 *
 * Two sheets, because versions and options are different grains. An option
 * belongs to the boat, not to one version of it, so putting both on a single
 * grid would mean repeating every option against every version and then
 * guessing which copy the dealer meant when they disagree.
 *
 *   BATEAUX  one row per version. The boat's own fields repeat down its rows.
 *            A boat with no versions still gets a row, with VERSION empty, so
 *            that it survives the round trip.
 *   OPTIONS  one row per option, tied to its boat by REF MODELE.
 *
 * Every row carries the reference of the record it came from, so a re-import
 * matches exactly even after the dealer renames something. Hand-written files
 * leave those columns blank and are matched by name instead.
 */
class BoatCatalogueExporter
{
    public const SHEET_BOATS   = 'BATEAUX';
    public const SHEET_OPTIONS = 'OPTIONS';

    public const BOAT_HEADERS = [
        'REF MODELE', 'MARQUE', 'MODELE', 'CODE MODELE', 'COMPLEMENT', 'ANNEE',
        'TYPE', 'PROPULSION', 'LONGUEUR', 'LARGEUR', 'TIRANT D\'EAU', 'POIDS',
        'FOURNISSEUR', 'REF VERSION', 'VERSION', 'PRIX HT', 'COUT HT', 'DEVISE',
        'EQUIPEMENTS INCLUS', 'ACTIF',
    ];

    public const OPTION_HEADERS = [
        'REF MODELE', 'MARQUE', 'MODELE', 'CODE', 'FAMILLE', 'DESIGNATION',
        'DESCRIPTION', 'PA HT', 'PV HT', 'DEVISE', 'TVA', 'ORDRE',
    ];

    private const BOAT_WIDTHS   = [26, 18, 28, 16, 18, 8, 14, 14, 11, 11, 13, 10, 20, 26, 22, 12, 12, 9, 60, 8];
    private const OPTION_WIDTHS = [26, 18, 28, 20, 18, 42, 50, 12, 12, 9, 7, 8];

    /**
     * @param  string|null  $brandId  limit to one brand
     * @param  string|null  $modelId  limit to one boat
     * @return string  path to a temp xlsx the caller owns
     */
    public function export(string $companyId, ?string $brandId = null, ?string $modelId = null): string
    {
        $brands = CompanyBrand::withoutGlobalScopes()->where('company_id', $companyId)
            ->get()->keyBy(fn ($b) => (string) $b->_id);

        $models = CompanyBoatModel::withoutGlobalScopes()->where('company_id', $companyId)
            ->where('is_archived', false);
        if ($brandId) $models->where('company_brand_id', $brandId);
        if ($modelId) $models->where('_id', $modelId);
        $models = $models->get()->sortBy(fn ($m) => [
            (string) ($brands[(string) $m->company_brand_id]->name ?? ''), (string) $m->name,
        ])->values();

        $modelIds = $models->pluck('_id')->map(fn ($i) => (string) $i)->all();

        $variants = CompanyBoatVariant::withoutGlobalScopes()->where('company_id', $companyId)
            ->whereIn('company_model_id', $modelIds)
            ->where('is_archived', false)
            ->get()->groupBy(fn ($v) => (string) $v->company_model_id);

        $options = CompanyOption::withoutGlobalScopes()->where('company_id', $companyId)
            ->whereIn('company_model_id', $modelIds)
            ->where('is_archived', false)
            ->get()->groupBy(fn ($o) => (string) $o->company_model_id);

        $boatRows = []; $optionRows = [];

        foreach ($models as $m) {
            $mid   = (string) $m->_id;
            $brand = (string) ($brands[(string) $m->company_brand_id]->name ?? '');

            // Columns that describe the boat rather than the version. They
            // repeat down every version row; on import the first one wins.
            $boat = [
                $mid,
                $brand,
                (string) $m->name,
                (string) ($m->code ?? ''),
                (string) ($m->complement ?? ''),
                $m->year !== null && $m->year !== '' ? (int) $m->year : '',
                (string) ($m->type ?? ''),
                (string) ($m->propulsion ?? ''),
                self::num($m->length_total),
                self::num($m->beam),
                self::num($m->draft_max),
                self::num($m->weight),
                (string) ($m->supplier ?? ''),
            ];

            $rows = ($variants[$mid] ?? collect())->sortBy(fn ($v) => (string) $v->name)->values();

            if ($rows->isEmpty()) {
                // No version yet. Still emit the boat so the file is a complete
                // picture and re-importing it does not quietly drop the boat.
                $boatRows[] = array_merge($boat, ['', '', '', '', '', '', 'oui']);
            }

            foreach ($rows as $v) {
                $equipment = collect($v->included_equipment ?? [])
                    ->map(fn ($e) => is_array($e) ? (string) ($e['label'] ?? '') : (string) $e)
                    ->filter(fn ($l) => trim($l) !== '')
                    ->implode(' | ');

                $boatRows[] = array_merge($boat, [
                    (string) $v->_id,
                    (string) $v->name,
                    round((float) $v->base_price, 2),
                    $v->cost !== null ? round((float) $v->cost, 2) : '',
                    (string) ($v->currency ?: 'EUR'),
                    $equipment,
                    $v->is_active === false ? 'non' : 'oui',
                ]);
            }

            foreach (($options[$mid] ?? collect())->sortBy(fn ($o) => (int) ($o->position ?? 0)) as $o) {
                // Options created in the editor may have no code. Give them one
                // now, or the round trip would create a duplicate rather than
                // update the row the dealer just edited.
                $code = $o->code;
                if (empty($code)) {
                    $code = Str::slug((string) $o->category) . '__' . Str::slug((string) $o->label);
                    $o->update(['code' => $code]);
                }

                $optionRows[] = [
                    $mid,
                    $brand,
                    (string) $m->name,
                    (string) $code,
                    (string) ($o->category ?? ''),
                    (string) $o->label,
                    (string) ($o->description ?? ''),
                    $o->cost !== null ? round((float) $o->cost, 2) : '',
                    round((float) $o->price, 2),
                    'EUR',
                    $o->vat_rate !== null ? (float) $o->vat_rate : 20,
                    (int) ($o->position ?? 0),
                ];
            }
        }

        return Xlsx::write([
            self::SHEET_BOATS   => ['headers' => self::BOAT_HEADERS,   'rows' => $boatRows,   'widths' => self::BOAT_WIDTHS],
            self::SHEET_OPTIONS => ['headers' => self::OPTION_HEADERS, 'rows' => $optionRows, 'widths' => self::OPTION_WIDTHS],
        ], 'nautiqs-catalogue-');
    }

    /**
     * A blank workbook with the headers and a couple of filled rows, so the
     * dealer can see what each column wants without having to own a boat yet.
     */
    public function template(): string
    {
        return Xlsx::write([
            self::SHEET_BOATS => [
                'headers' => self::BOAT_HEADERS,
                'widths'  => self::BOAT_WIDTHS,
                'rows'    => [
                    ['', 'Brig', 'Eagle 6.7', 'EAGLE67', 'Open', 2026, 'semi-rigide', 'hors-bord',
                     6.7, 2.6, 0.4, 980, 'Brig France', '', 'Standard', 45000, 32000, 'EUR',
                     'Taud de soleil | Échelle de bain | Douchette', 'oui'],
                    ['', 'Brig', 'Eagle 6.7', 'EAGLE67', 'Open', 2026, 'semi-rigide', 'hors-bord',
                     6.7, 2.6, 0.4, 980, 'Brig France', '', 'Confort', 52000, 37500, 'EUR',
                     'Taud de soleil | Échelle de bain | Douchette | Table cockpit', 'oui'],
                    ['', 'Salpa', 'Soleil 21.5', '', '', 2026, 'open', 'hors-bord',
                     6.5, 2.5, '', '', '', '', 'Standard', 63621, '', 'EUR', '', 'oui'],
                ],
            ],
            self::SHEET_OPTIONS => [
                'headers' => self::OPTION_HEADERS,
                'widths'  => self::OPTION_WIDTHS,
                'rows'    => [
                    ['', 'Brig', 'Eagle 6.7', 'OPT-CHAP', 'Confort', 'Chapeau électrique', '', 1400, 1950, 'EUR', 20, 1],
                    ['', 'Brig', 'Eagle 6.7', 'OPT-DIR',  'Pilotage', 'Direction hydraulique', 'Pompe + vérin', 1180, 1650, 'EUR', 20, 2],
                    ['', 'Salpa', 'Soleil 21.5', 'OPT-WC', 'Confort', 'WC chimique', '', '', 385, 'EUR', 20, 1],
                ],
            ],
        ], 'nautiqs-modele-catalogue-');
    }

    /** Blank rather than 0 for an unset measurement — 0 metres is a claim, empty is not. */
    private static function num($v): float|string
    {
        return ($v === null || $v === '' || (float) $v == 0.0) ? '' : round((float) $v, 3);
    }
}
