<?php

namespace App\Services;

use App\Models\CompanyBoatModel;
use App\Models\CompanyOption;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

use App\Services\FxRateService;

/**
 * Bulk-import for boat options. Seven columns visible to the dealer:
 *
 *   FAMILLE        (category)         — required
 *   DESIGNATION    (label)            — required
 *   PA HT          (cost)             — optional
 *   PA CURRENCY    (cost currency)    — optional, defaults EUR. EUR or USD.
 *   PV HT          (price)            — required, selling price excl. VAT
 *   PV CURRENCY    (price currency)   — optional, defaults EUR. EUR or USD.
 *   TVA            (vat rate)         — optional, defaults 20. Accepts 20 or 0.2.
 *
 * Currencies are converted to EUR at import time via FxRateService
 * (frankfurter.app, ECB rates). The original amount + currency + rate
 * are stored alongside so the dealer can audit what was imported. If
 * the FX lookup fails for a row, that row is rejected with a clear
 * error and the rest of the import completes.
 *
 * Upsert key is auto-derived from (category, label) so re-importing the
 * same file with updated prices updates in place — the dealer never has
 * to manage a SKU.
 *
 * Dependency-free reader (ZipArchive + DOMDocument for XLSX, fgetcsv for
 * CSV) — same approach as EngineImporter.
 */
class OptionImporter
{
    public function __construct(private ?FxRateService $fx = null)
    {
        $this->fx = $fx ?? new FxRateService();
    }

    /** Header text (lowercased + trimmed) → internal field. EN + FR aliases. */
    private const HEADER_ALIASES = [
        // Category
        'famille'        => 'category',
        'category'       => 'category',
        'categorie'      => 'category',
        'catégorie'      => 'category',

        // Label
        'designation'    => 'label',
        'désignation'    => 'label',
        'designation fr' => 'label',
        'désignation fr' => 'label',
        'label'          => 'label',
        'libellé'        => 'label',
        'libelle'        => 'label',
        'option'         => 'label',
        'nom'            => 'label',
        'name'           => 'label',

        // Cost
        'pa ht'          => 'cost',
        'cost'           => 'cost',
        'cost ht'        => 'cost',
        'prix achat'     => 'cost',
        'prix achat ht'  => 'cost',
        'purchase price' => 'cost',
        'coût'           => 'cost',
        'cout'           => 'cost',

        // Cost currency
        'pa currency'    => 'cost_currency',
        'pa devise'      => 'cost_currency',
        'devise pa'      => 'cost_currency',
        'devise achat'   => 'cost_currency',
        'cost currency'  => 'cost_currency',

        // Price
        'pv ht'          => 'price',
        'price'          => 'price',
        'price ht'       => 'price',
        'public ht'      => 'price',
        'prix vente'     => 'price',
        'prix vente ht'  => 'price',
        'prix ht'        => 'price',
        'prix'           => 'price',

        // Price currency
        'pv currency'    => 'price_currency',
        'pv devise'      => 'price_currency',
        'devise pv'      => 'price_currency',
        'devise vente'   => 'price_currency',
        'devise'         => 'price_currency',
        'currency'       => 'price_currency',
        'price currency' => 'price_currency',

        // VAT
        'tva'            => 'vat_rate',
        'vat'            => 'vat_rate',
        'vat rate'       => 'vat_rate',
        'taux tva'       => 'vat_rate',
    ];

    private const REQUIRED = ['category', 'label', 'price'];
    private const MAX_ROWS = 5000;

    /**
     * Import options for a tenant, attaching every row to $fixedBoatId.
     * In Nautiqs the import is always launched from inside a boat's
     * Options tab, so the boat is known up-front and the file doesn't
     * need to reference it.
     */
    public function import(UploadedFile $file, string $companyId, string $fixedBoatId): array
    {
        $rows = $this->readFile($file);
        if (empty($rows)) {
            return $this->result(errors: [['row' => 0, 'message' => 'File is empty.']]);
        }

        $headerRow = array_shift($rows);
        $headerMap = $this->mapHeaders($headerRow);
        if (empty($headerMap)) {
            return $this->result(errors: [[
                'row'     => 1,
                'message' => 'Could not detect any known column. Expected at least FAMILLE, DESIGNATION, PV HT.',
            ]]);
        }

        $missing = array_diff(self::REQUIRED, array_values($headerMap));
        if (! empty($missing)) {
            $human = array_map(fn ($m) => match ($m) {
                'category' => 'FAMILLE',
                'label'    => 'DESIGNATION',
                'price'    => 'PV HT',
                default    => $m,
            }, $missing);
            return $this->result(errors: [[
                'row'     => 1,
                'message' => 'Missing required column(s): ' . implode(', ', $human),
            ]]);
        }

        if (count($rows) > self::MAX_ROWS) {
            return $this->result(errors: [[
                'row'     => 0,
                'message' => 'Too many rows. Split into batches of ' . self::MAX_ROWS . ' or fewer.',
            ]]);
        }

        $boat = CompanyBoatModel::where('company_id', $companyId)
            ->where('_id', $fixedBoatId)
            ->first();
        if (! $boat) {
            return $this->result(errors: [[
                'row'     => 0,
                'message' => 'Target boat not found.',
            ]]);
        }

        $created = 0; $updated = 0; $skipped = 0; $errors = [];

        foreach ($rows as $i => $rawRow) {
            $rowNumber = $i + 2;

            if (! array_filter(array_map('trim', array_map('strval', $rawRow)))) {
                $skipped++;
                continue;
            }

            $data  = $this->extract($rawRow, $headerMap);
            $error = $this->validate($data);
            if ($error) {
                $errors[] = ['row' => $rowNumber, 'message' => $error];
                continue;
            }

            // FX: convert cost + price into EUR if the row used another
            // currency. If the rate lookup fails, reject the row with a
            // clear message and let the rest of the import continue.
            $costEur  = $data['cost'];
            $priceEur = $data['price'];
            $rateUsed = 1.0;
            if ($data['cost_currency'] !== 'EUR' && $data['cost'] > 0) {
                $rate = $this->fx->rate($data['cost_currency'], 'EUR');
                if ($rate === null) {
                    $errors[] = ['row' => $rowNumber, 'message' => 'FX rate unavailable for ' . $data['cost_currency'] . '→EUR. Please retry.'];
                    continue;
                }
                $costEur  = round($data['cost'] * $rate, 2);
                $rateUsed = $rate;
            }
            if ($data['price_currency'] !== 'EUR' && $data['price'] > 0) {
                $rate = $this->fx->rate($data['price_currency'], 'EUR');
                if ($rate === null) {
                    $errors[] = ['row' => $rowNumber, 'message' => 'FX rate unavailable for ' . $data['price_currency'] . '→EUR. Please retry.'];
                    continue;
                }
                $priceEur = round($data['price'] * $rate, 2);
                $rateUsed = $rate; // last-wins; both rates are typically the same anyway
            }

            // Auto-generated stable key from (category, label). Used for
            // upsert matching only — the dealer never sees or types it.
            // Examples:
            //   "Transport" + "Bandol → Marseille"  → "transport__bandol-marseille"
            //   "Électronique" + "Garmin 1243xsv"  → "electronique__garmin-1243xsv"
            $autoCode = Str::slug($data['category']) . '__' . Str::slug($data['label']);

            $existing = CompanyOption::where('company_id', $companyId)
                ->where('company_model_id', (string) $boat->_id)
                ->where('code', $autoCode)
                ->first();

            $payload = [
                'category'                => $data['category'],
                'label'                   => $data['label'],
                'code'                    => $autoCode,
                'price'                   => $priceEur,
                'cost'                    => $costEur,
                'vat_rate'                => $data['vat_rate'],
                'currency'                => 'EUR',
                'original_cost'           => $data['cost'],
                'original_cost_currency'  => $data['cost_currency'],
                'original_price'          => $data['price'],
                'original_price_currency' => $data['price_currency'],
                'fx_rate_used'            => $rateUsed,
                'fx_rate_date'            => now(),
                'is_archived'             => false,
            ];

            if ($existing) {
                $existing->update($payload);
                $updated++;
            } else {
                $position = (int) (CompanyOption::where('company_id', $companyId)
                    ->where('company_model_id', (string) $boat->_id)
                    ->max('position') ?? 0) + 1;

                CompanyOption::create(array_merge($payload, [
                    'company_id'       => $companyId,
                    'company_model_id' => (string) $boat->_id,
                    'global_option_id' => null,
                    'source'           => 'private',
                    'position'         => $position,
                ]));
                $created++;
            }
        }

        return $this->result($created, $updated, $skipped, $errors);
    }

    /* ---------------------------------------------------- File readers */

    private function readFile(UploadedFile $file): array
    {
        $ext = strtolower($file->getClientOriginalExtension());
        $path = $file->getRealPath();

        if (in_array($ext, ['xlsx', 'xlsm'], true)) {
            return $this->readXlsx($path);
        }
        return $this->readCsv($path);
    }

    private function readCsv(string $path): array
    {
        $first = fgets(fopen($path, 'r')) ?: '';
        $delimiter = ',';
        if (substr_count($first, ';') > substr_count($first, ',')) $delimiter = ';';
        elseif (substr_count($first, "\t") > substr_count($first, ',')) $delimiter = "\t";

        $rows = [];
        if (($handle = fopen($path, 'r')) !== false) {
            $bom = fread($handle, 3);
            if ($bom !== "\xef\xbb\xbf") rewind($handle);

            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                $rows[] = $row;
            }
            fclose($handle);
        }
        return $rows;
    }

    private function readXlsx(string $path): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return [];
        }

        $shared = [];
        if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
            $doc = new \DOMDocument();
            $doc->loadXML($xml, LIBXML_NOERROR | LIBXML_NOWARNING);
            foreach ($doc->getElementsByTagName('si') as $si) {
                $text = '';
                foreach ($si->getElementsByTagName('t') as $t) {
                    $text .= $t->nodeValue;
                }
                $shared[] = $text;
            }
        }

        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        if ($sheetXml === false) return [];

        $doc = new \DOMDocument();
        $doc->loadXML($sheetXml, LIBXML_NOERROR | LIBXML_NOWARNING);

        $rows = [];
        foreach ($doc->getElementsByTagName('row') as $rowEl) {
            $rowData = [];
            $maxCol  = 0;
            foreach ($rowEl->getElementsByTagName('c') as $cell) {
                $ref  = $cell->getAttribute('r');
                $col  = $this->columnIndex(preg_replace('/\d+/', '', $ref));
                $type = $cell->getAttribute('t');
                $vEl  = $cell->getElementsByTagName('v')->item(0);
                $value = $vEl ? $vEl->nodeValue : '';

                if ($type === 's') {
                    $value = $shared[(int) $value] ?? '';
                } elseif ($type === 'inlineStr') {
                    $tEl   = $cell->getElementsByTagName('t')->item(0);
                    $value = $tEl ? $tEl->nodeValue : '';
                }

                $rowData[$col] = (string) $value;
                if ($col > $maxCol) $maxCol = $col;
            }
            $padded = [];
            for ($c = 0; $c <= $maxCol; $c++) {
                $padded[] = $rowData[$c] ?? '';
            }
            $rows[] = $padded;
        }
        return $rows;
    }

    private function columnIndex(string $letters): int
    {
        $letters = strtoupper($letters);
        $n = 0;
        for ($i = 0, $len = strlen($letters); $i < $len; $i++) {
            $n = $n * 26 + (ord($letters[$i]) - 64);
        }
        return $n - 1;
    }

    /* ---------------------------------------------- Row extract + check */

    private function mapHeaders(array $headerRow): array
    {
        $map = [];
        foreach ($headerRow as $i => $cell) {
            $key = mb_strtolower(trim((string) $cell));
            if (isset(self::HEADER_ALIASES[$key])) {
                $map[$i] = self::HEADER_ALIASES[$key];
            }
        }
        return $map;
    }

    private function extract(array $rawRow, array $headerMap): array
    {
        $out = [
            'category'       => '',
            'label'          => '',
            'cost'           => 0.0,
            'cost_currency'  => 'EUR',
            'price'          => 0.0,
            'price_currency' => 'EUR',
            'vat_rate'       => 20.0,
        ];

        foreach ($headerMap as $col => $field) {
            $raw = trim((string) ($rawRow[$col] ?? ''));
            switch ($field) {
                case 'category':
                case 'label':
                    $out[$field] = $raw;
                    break;
                case 'cost':
                case 'price':
                    if ($raw !== '') {
                        // Strip currency symbols + thousand spaces; accept FR comma decimals.
                        $clean = preg_replace('/[€$\s]/u', '', $raw);
                        $out[$field] = (float) str_replace(',', '.', $clean);
                    }
                    break;
                case 'cost_currency':
                case 'price_currency':
                    $upper = strtoupper($raw);
                    if (in_array($upper, ['EUR', 'USD'], true)) {
                        $out[$field] = $upper;
                    }
                    break;
                case 'vat_rate':
                    if ($raw !== '') {
                        $v = (float) str_replace([' ', ','], ['', '.'], $raw);
                        // Auto-scale 0.2 → 20 like the rest of the app.
                        if ($v > 0 && $v <= 1) $v = $v * 100;
                        $out['vat_rate'] = $v;
                    }
                    break;
            }
        }
        return $out;
    }

    private function validate(array $data): ?string
    {
        if ($data['category'] === '') return 'FAMILLE is required.';
        if (mb_strlen($data['category']) > 80) return 'FAMILLE must be 80 characters or fewer.';
        if ($data['label']    === '') return 'DESIGNATION is required.';
        if (mb_strlen($data['label']) > 255) return 'DESIGNATION must be 255 characters or fewer.';
        if ($data['price'] < 0)         return 'PV HT must be zero or positive.';
        if ($data['price'] > 1_000_000) return 'PV HT is implausibly high (> €1M).';
        if ($data['cost']  < 0)         return 'PA HT must be zero or positive.';
        if ($data['vat_rate'] < 0 || $data['vat_rate'] > 100) {
            return 'TVA must be between 0 and 100 (or 0 and 1 if decimal).';
        }
        return null;
    }

    private function result(int $created = 0, int $updated = 0, int $skipped = 0, array $errors = []): array
    {
        return compact('created', 'updated', 'skipped', 'errors');
    }
}
