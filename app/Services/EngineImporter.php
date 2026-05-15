<?php

namespace App\Services;

use App\Models\Engine;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Engine bulk-import. Reads CSV or XLSX into a normalised row shape,
 * validates each row, then upserts into the per-company `engines`
 * collection keyed on (company_id, brand, code).
 *
 * Deliberately dependency-free — uses PHP's built-in fgetcsv for CSV
 * and ZipArchive + DOMDocument for XLSX so we don't pull in
 * phpoffice/phpspreadsheet (~30MB).
 *
 * Result shape returned to the caller:
 *   [
 *     'created' => int,
 *     'updated' => int,
 *     'skipped' => int,                       // empty / fully-blank rows
 *     'errors'  => [ ['row' => int, 'message' => string], ... ],
 *   ]
 */
class EngineImporter
{
    /**
     * Column header → internal field name. Lowercased + trimmed before
     * matching so 'Brand', 'brand', 'BRAND' all work. Aliases listed so
     * dealers can use either EN or FR headers.
     */
    private const HEADER_ALIASES = [
        'brand'       => 'brand',
        'marque'      => 'brand',
        'code'        => 'code',
        'sku'         => 'code',
        'code/sku'    => 'code',
        'code / sku'  => 'code',
        'hp'          => 'horsepower',
        'horsepower'  => 'horsepower',
        'cv'          => 'horsepower',
        'puissance'   => 'horsepower',
        'price'       => 'price',
        'public ht'   => 'price',
        'prix ht'     => 'price',
        'prix'        => 'price',
        'vat'         => 'vat_rate',
        'vat rate'    => 'vat_rate',
        'vat %'       => 'vat_rate',
        'tva'         => 'vat_rate',
        'taux tva'    => 'vat_rate',
        'currency'    => 'currency',
        'devise'      => 'currency',
    ];

    private const REQUIRED  = ['brand', 'code', 'price'];
    private const MAX_ROWS  = 5000;

    public function import(UploadedFile $file, string $companyId): array
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
                'message' => 'Could not detect any known column. Expected at least Brand, Code, Price.',
            ]]);
        }

        $missing = array_diff(self::REQUIRED, array_values($headerMap));
        if (! empty($missing)) {
            return $this->result(errors: [[
                'row'     => 1,
                'message' => 'Missing required column(s): ' . implode(', ', $missing),
            ]]);
        }

        if (count($rows) > self::MAX_ROWS) {
            return $this->result(errors: [[
                'row'     => 0,
                'message' => 'Too many rows. Split into batches of ' . self::MAX_ROWS . ' or fewer.',
            ]]);
        }

        $created = 0; $updated = 0; $skipped = 0; $errors = [];

        foreach ($rows as $i => $rawRow) {
            $rowNumber = $i + 2; // +1 for the header, +1 for 1-based numbering

            // Skip blank rows silently — common when dealers leave trailing
            // empty lines in a CSV.
            if (! array_filter(array_map('trim', array_map('strval', $rawRow)))) {
                $skipped++;
                continue;
            }

            $data = $this->extract($rawRow, $headerMap);

            $error = $this->validate($data);
            if ($error) {
                $errors[] = ['row' => $rowNumber, 'message' => $error];
                continue;
            }

            // Upsert by (company_id, brand, code). Case-insensitive on
            // brand + code so "Suzuki / DF200" and "SUZUKI / df200" map
            // to the same engine.
            $existing = Engine::where('company_id', $companyId)
                ->whereRaw([
                    'brand' => ['$regex' => '^' . preg_quote($data['brand'], '/') . '$', '$options' => 'i'],
                    'code'  => ['$regex' => '^' . preg_quote($data['code'],  '/') . '$', '$options' => 'i'],
                ])
                ->first();

            if ($existing) {
                $existing->update([
                    'horsepower' => $data['horsepower'],
                    'price'      => $data['price'],
                    'vat_rate'   => $data['vat_rate'],
                    'currency'   => $data['currency'],
                    'is_archived'=> false,
                ]);
                $updated++;
            } else {
                Engine::create([
                    'company_id'  => $companyId,
                    'brand'       => $data['brand'],
                    'code'        => $data['code'],
                    'horsepower' => $data['horsepower'],
                    'price'      => $data['price'],
                    'vat_rate'   => $data['vat_rate'],
                    'currency'   => $data['currency'],
                    'is_archived'=> false,
                ]);
                $created++;
            }
        }

        return $this->result($created, $updated, $skipped, $errors);
    }

    /**
     * Read either CSV or XLSX into a 2D array of strings. The first row
     * is treated as the header. Returns rows including the header so the
     * caller can shift it off.
     */
    private function readFile(UploadedFile $file): array
    {
        $ext = strtolower($file->getClientOriginalExtension());
        $path = $file->getRealPath();

        if (in_array($ext, ['xlsx', 'xlsm'], true)) {
            return $this->readXlsx($path);
        }

        // Default to CSV — accepts both .csv and unrecognised extensions
        // so users who export tab-delimited / semicolon-delimited from
        // Excel still get parsed (fgetcsv auto-detects on the first row).
        return $this->readCsv($path);
    }

    private function readCsv(string $path): array
    {
        // Detect delimiter from the header row. Excel often saves CSVs
        // with semicolons in FR locales — common enough to handle.
        $first = fgets(fopen($path, 'r')) ?: '';
        $delimiter = ',';
        if (substr_count($first, ';') > substr_count($first, ',')) $delimiter = ';';
        elseif (substr_count($first, "\t") > substr_count($first, ',')) $delimiter = "\t";

        $rows = [];
        if (($handle = fopen($path, 'r')) !== false) {
            // Strip a UTF-8 BOM if present so the header "Brand" doesn't
            // become "\u{FEFF}Brand" and miss the alias lookup.
            $bom = fread($handle, 3);
            if ($bom !== "\xef\xbb\xbf") rewind($handle);

            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                $rows[] = $row;
            }
            fclose($handle);
        }
        return $rows;
    }

    /**
     * Minimal XLSX reader. XLSX is a zip of XML files; we read sheet1
     * and resolve shared-string indices to text. Handles the common
     * cases dealers will produce (Excel / LibreOffice / Google Sheets).
     */
    private function readXlsx(string $path): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return [];
        }

        // Shared strings (Excel pools repeated text into a separate file).
        $shared = [];
        if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
            $doc = new \DOMDocument();
            $doc->loadXML($xml, LIBXML_NOERROR | LIBXML_NOWARNING);
            foreach ($doc->getElementsByTagName('si') as $si) {
                // <si> can contain a plain <t> or rich-text runs <r><t>.
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
                $ref = $cell->getAttribute('r');                // e.g. "B5"
                $col = $this->columnIndex(preg_replace('/\d+/', '', $ref));
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
            // Pad sparse rows so column positions line up with the header.
            $padded = [];
            for ($c = 0; $c <= $maxCol; $c++) {
                $padded[] = $rowData[$c] ?? '';
            }
            $rows[] = $padded;
        }
        return $rows;
    }

    /**
     * "A" → 0, "Z" → 25, "AA" → 26 … Used to place cells in their
     * correct column when a row has gaps.
     */
    private function columnIndex(string $letters): int
    {
        $letters = strtoupper($letters);
        $n = 0;
        for ($i = 0, $len = strlen($letters); $i < $len; $i++) {
            $n = $n * 26 + (ord($letters[$i]) - 64);
        }
        return $n - 1;
    }

    /**
     * Build a column-index → field-name map from the raw header row.
     */
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
            'brand'      => '',
            'code'       => '',
            'horsepower' => null,
            'price'      => 0.0,
            'vat_rate'   => 20.0,
            'currency'   => 'EUR',
        ];

        foreach ($headerMap as $col => $field) {
            $raw = trim((string) ($rawRow[$col] ?? ''));
            switch ($field) {
                case 'brand':
                case 'code':
                    $out[$field] = $raw;
                    break;
                case 'horsepower':
                    $out['horsepower'] = $raw === '' ? null : (float) str_replace([' ', ','], ['', '.'], $raw);
                    break;
                case 'price':
                case 'vat_rate':
                    if ($raw !== '') {
                        $out[$field] = (float) str_replace([' ', ','], ['', '.'], $raw);
                    }
                    break;
                case 'currency':
                    $upper = strtoupper($raw);
                    if (in_array($upper, ['EUR', 'USD'], true)) {
                        $out['currency'] = $upper;
                    }
                    break;
            }
        }
        return $out;
    }

    private function validate(array $data): ?string
    {
        if ($data['brand'] === '') return 'Brand is required.';
        if (mb_strlen($data['brand']) > 100) return 'Brand must be 100 characters or fewer.';
        if ($data['code'] === '') return 'Code is required.';
        if (mb_strlen($data['code']) > 120) return 'Code must be 120 characters or fewer.';
        if ($data['price'] < 0) return 'Price must be zero or positive.';
        if ($data['price'] > 1_000_000) return 'Price is implausibly high (> €1M).';
        if ($data['vat_rate'] < 0 || $data['vat_rate'] > 100) return 'VAT rate must be between 0 and 100.';
        if ($data['horsepower'] !== null && $data['horsepower'] < 0) return 'Horsepower cannot be negative.';
        return null;
    }

    private function result(int $created = 0, int $updated = 0, int $skipped = 0, array $errors = []): array
    {
        return compact('created', 'updated', 'skipped', 'errors');
    }
}
