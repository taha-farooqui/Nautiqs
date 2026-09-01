<?php

namespace App\Services;

use App\Models\Engine;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Engine bulk-import for the dealer-side library. Five columns:
 *
 *   Brand     (required)
 *   Model     (required) — engine code/SKU, e.g. "DF200A TL/TX"
 *   PA HT     (optional) — purchase price excl. VAT, for margin display
 *   PV HT     (required) — selling price excl. VAT
 *   TVA       (optional) — VAT rate, defaults to 20. Accepts 20 or 0.2.
 *   Description (optional) — free text shown under the engine on the quote,
 *                 e.g. the propeller that goes with it. Line breaks are kept.
 *
 * No currency column — engines are EUR-only per the latest scope call.
 * Upsert key is (company_id, brand, code) so re-uploading the same file
 * with new prices updates in place.
 *
 * Dependency-free reader (ZipArchive + DOMDocument for XLSX, fgetcsv for
 * CSV) — same approach as OptionImporter.
 */
class EngineImporter
{
    private const HEADER_ALIASES = [
        // Brand
        'brand'       => 'brand',
        'marque'      => 'brand',
        // Code / Model
        'code'        => 'code',
        'model'       => 'code',
        'modèle'      => 'code',
        'modele'      => 'code',
        'sku'         => 'code',
        // Cost (PA HT)
        'pa ht'       => 'cost',
        'cost'        => 'cost',
        'cost ht'     => 'cost',
        'prix achat'  => 'cost',
        'prix achat ht' => 'cost',
        'purchase price' => 'cost',
        // Price (PV HT)
        'pv ht'       => 'price',
        'price'       => 'price',
        'price ht'    => 'price',
        'public ht'   => 'price',
        'prix vente'  => 'price',
        'prix vente ht' => 'price',
        'prix ht'     => 'price',
        'prix'        => 'price',
        // VAT
        'tva'         => 'vat_rate',
        'vat'         => 'vat_rate',
        'vat rate'    => 'vat_rate',
        'taux tva'    => 'vat_rate',
        // Description
        'description' => 'description',
        'désignation' => 'description',
        'designation' => 'description',
        'détail'      => 'description',
        'detail'      => 'description',
        'commentaire' => 'description',
        'commentaires'=> 'description',
        'notes'       => 'description',
        'note'        => 'description',
        'remarque'    => 'description',
        'remarques'   => 'description',
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
                'message' => 'Could not detect any known column. Expected at least Brand, Model, PV HT.',
            ]]);
        }

        $missing = array_diff(self::REQUIRED, array_values($headerMap));
        if (! empty($missing)) {
            $human = array_map(fn ($m) => match ($m) {
                'brand' => 'Brand',
                'code'  => 'Model',
                'price' => 'PV HT',
                default => $m,
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

        $hasDescription = in_array('description', $headerMap, true);

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

            // Upsert by (company_id, brand, code) — case-insensitive on
            // both so "Suzuki / DF200" and "SUZUKI / df200" map to the
            // same engine.
            $existing = Engine::where('company_id', $companyId)
                ->whereRaw([
                    'brand' => ['$regex' => '^' . preg_quote($data['brand'], '/') . '$', '$options' => 'i'],
                    'code'  => ['$regex' => '^' . preg_quote($data['code'],  '/') . '$', '$options' => 'i'],
                ])
                ->first();

            $payload = [
                'brand'       => $data['brand'],
                'code'        => $data['code'],
                'cost'        => $data['cost'],
                'price'       => $data['price'],
                'vat_rate'    => $data['vat_rate'],
                'currency'    => 'EUR',
                'is_archived' => false,
            ];

            // Only written when the file carries the column. A dealer
            // re-importing a plain price list would otherwise blank every
            // description they had typed by hand.
            if ($hasDescription) {
                $payload['description'] = $data['description'] !== '' ? $data['description'] : null;
            }

            if ($existing) {
                $existing->update($payload);
                $updated++;
            } else {
                Engine::create(array_merge($payload, ['company_id' => $companyId]));
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
        if ($zip->open($path) !== true) return [];

        $shared = [];
        if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
            $doc = new \DOMDocument();
            $doc->loadXML($xml, LIBXML_NOERROR | LIBXML_NOWARNING);
            foreach ($doc->getElementsByTagName('si') as $si) {
                $text = '';
                foreach ($si->getElementsByTagName('t') as $t) $text .= $t->nodeValue;
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
            $rowData = []; $maxCol = 0;
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
            for ($c = 0; $c <= $maxCol; $c++) $padded[] = $rowData[$c] ?? '';
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
            'brand'       => '',
            'code'        => '',
            'cost'        => 0.0,
            'price'       => 0.0,
            'vat_rate'    => 20.0,
            'description' => '',
        ];
        foreach ($headerMap as $col => $field) {
            $raw = trim((string) ($rawRow[$col] ?? ''));
            switch ($field) {
                case 'brand':
                case 'code':
                    $out[$field] = $raw;
                    break;
                case 'description':
                    // Deliberately not trimmed of inner newlines: a dealer
                    // listing the propeller on its own line wants that kept
                    // (the quote and PDF render descriptions with nl2br).
                    $out['description'] = trim((string) ($rawRow[$col] ?? ''), " 	
");
                    break;
                case 'cost':
                case 'price':
                    if ($raw !== '') {
                        $clean = preg_replace('/[€$\s]/u', '', $raw);
                        $out[$field] = (float) str_replace(',', '.', $clean);
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
        if ($data['brand'] === '')        return 'Brand is required.';
        if (mb_strlen($data['brand']) > 80) return 'Brand must be 80 characters or fewer.';
        if ($data['code']  === '')        return 'Model is required.';
        if (mb_strlen($data['code']) > 120) return 'Model must be 120 characters or fewer.';
        if ($data['price'] < 0)           return 'PV HT must be zero or positive.';
        if ($data['price'] > 1_000_000)   return 'PV HT is implausibly high (> €1M).';
        if ($data['cost']  < 0)           return 'PA HT must be zero or positive.';
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
