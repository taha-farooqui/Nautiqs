<?php

namespace App\Services;

use App\Models\CompanyBoatModel;
use App\Models\CompanyBoatVariant;
use App\Models\CompanyBrand;
use App\Models\CompanyOption;

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
 *   BATEAUX  one row per version. MARQUE and MODELE repeat down the rows of a
 *            boat. A boat with no versions still gets a row, with VERSION
 *            empty, so that it survives the round trip. Included equipment is
 *            one cell, items separated by a semicolon — not a comma, because
 *            160 of the 4 107 equipment lines in the live catalogues contain
 *            one ("LUMIERE DE NAVIGATION VERTE, ROUGE, 360°" is a single
 *            item), while none contains a semicolon or a pipe.
 *   OPTIONS  one row per option, tied to its boat by MARQUE and MODELE.
 *
 * The columns are the ones dealers fill. Measured across the four live
 * catalogues — 80 boats, 242 versions, 3 227 options — COMPLEMENT, ANNEE,
 * TYPE, PROPULSION, the four dimensions and FOURNISSEUR were empty on every
 * single boat; ACTIF was true on every version; TVA was 20 on all but twelve
 * options, which had none. CODE MODELE and the option CODE looked filled, but
 * every value matched the pattern the app generates for them, so nobody had
 * ever typed one. Columns like those are not information, they are furniture,
 * and a dealer opening a twenty-column sheet to change a price has to work out
 * which two columns matter.
 *
 * Rows match on names: brand, then boat within the brand, then version within
 * the boat, then famille + désignation within the boat. There are no duplicate
 * names anywhere in the live data, and a name is something a dealer can read.
 * The importer still accepts every dropped column, including the old REF ones,
 * so a file exported before this change still imports exactly.
 */
class BoatCatalogueExporter
{
    public const SHEET_BOATS   = 'BATEAUX';
    public const SHEET_OPTIONS = 'OPTIONS';

    public const BOAT_HEADERS = [
        'MARQUE', 'MODELE', 'VERSION', 'PRIX HT', 'COUT HT', 'DEVISE', 'EQUIPEMENTS INCLUS',
    ];

    public const OPTION_HEADERS = [
        'MARQUE', 'MODELE', 'FAMILLE', 'DESIGNATION', 'DESCRIPTION', 'PA HT', 'PV HT',
    ];

    private const BOAT_WIDTHS   = [20, 30, 24, 13, 13, 9, 70];
    private const OPTION_WIDTHS = [20, 30, 20, 44, 52, 13, 13];

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

            // Identifies the boat, and repeats down each of its version rows.
            $boat = [$brand, (string) $m->name];

            $rows = ($variants[$mid] ?? collect())->sortBy(fn ($v) => (string) $v->name)->values();

            if ($rows->isEmpty()) {
                // No version yet. Still emit the boat so the file is a complete
                // picture and re-importing it does not quietly drop the boat.
                $boatRows[] = array_merge($boat, ['', '', '', '', '']);
            }

            foreach ($rows as $v) {
                $equipment = collect($v->included_equipment ?? [])
                    ->map(fn ($e) => is_array($e) ? (string) ($e['label'] ?? '') : (string) $e)
                    ->filter(fn ($l) => trim($l) !== '')
                    ->implode('; ');

                $boatRows[] = array_merge($boat, [
                    (string) $v->name,
                    round((float) $v->base_price, 2),
                    $v->cost !== null ? round((float) $v->cost, 2) : '',
                    (string) ($v->currency ?: 'EUR'),
                    $equipment,
                ]);
            }

            // Kept in display order, so the file reads like the boat's option
            // list and a re-import keeps that order for anything new.
            foreach (($options[$mid] ?? collect())->sortBy(fn ($o) => (int) ($o->position ?? 0)) as $o) {
                $optionRows[] = [
                    $brand,
                    (string) $m->name,
                    (string) ($o->category ?? ''),
                    (string) $o->label,
                    (string) ($o->description ?? ''),
                    $o->cost !== null ? round((float) $o->cost, 2) : '',
                    round((float) $o->price, 2),
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
                    ['Brig', 'Eagle 6.7', 'Standard', 45000, 32000, 'EUR',
                     'Taud de soleil; Échelle de bain; Douchette'],
                    ['Brig', 'Eagle 6.7', 'Confort', 52000, 37500, 'EUR',
                     'Taud de soleil; Échelle de bain; Douchette; Table cockpit'],
                    ['Salpa', 'Soleil 21.5', 'Standard', 63621, '', 'EUR', ''],
                ],
            ],
            self::SHEET_OPTIONS => [
                'headers' => self::OPTION_HEADERS,
                'widths'  => self::OPTION_WIDTHS,
                'rows'    => [
                    ['Brig', 'Eagle 6.7', 'Confort', 'Chapeau électrique', '', 1400, 1950],
                    ['Brig', 'Eagle 6.7', 'Pilotage', 'Direction hydraulique', 'Pompe + vérin', 1180, 1650],
                    ['Salpa', 'Soleil 21.5', 'Confort', 'WC chimique', '', '', 385],
                ],
            ],
        ], 'nautiqs-modele-catalogue-');
    }
}
