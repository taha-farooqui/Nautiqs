<?php

namespace App\Services;

use App\Models\Engine;
use App\Models\EngineAccessory;
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
 * Accessories, in the layout of Suzuki's own price export:
 *
 *   KIT PRE-RIGGING / KIT PA HT / KIT PV HT
 *   HELICE / HELICE PA HT / HELICE PV HT
 *
 * Each distinct kit or propeller becomes one EngineAccessory, found again by
 * its name on the next import, and is linked to the engine on that row. The
 * MECA kit on twenty rows is one accessory with twenty links, so its price
 * lives in one place. "Inclus / non applicable" at €0 — how the file says a
 * small engine's price already covers them — creates nothing. Linking only
 * ever adds: a propeller the dealer linked by hand is not dropped because
 * this file suggests a different one. MOTEUR PA HT / MOTEUR PV HT are read
 * as the engine's own prices; the TOTAL columns are ignored.
 *
 * With no horsepower column, horsepower is read from the model name
 * ("DF140B TL/TX" → 140) for engines that have none, so the quote builder's
 * match on the boat's power works for imported engines.
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
        // Cost (PA HT) — "MOTEUR PA HT" in a file that also prices the kit
        // and propeller, so the three are told apart.
        'pa ht'       => 'cost',
        'moteur pa ht' => 'cost',
        'engine pa ht' => 'cost',
        'cost'        => 'cost',
        'cost ht'     => 'cost',
        'prix achat'  => 'cost',
        'prix achat ht' => 'cost',
        'purchase price' => 'cost',
        // Price (PV HT)
        'pv ht'       => 'price',
        'moteur pv ht' => 'price',
        'engine pv ht' => 'price',
        'price'       => 'price',
        'price ht'    => 'price',
        'public ht'   => 'price',
        'prix vente'  => 'price',
        'prix vente ht' => 'price',
        'prix ht'     => 'price',
        'prix'        => 'price',
        // Horsepower
        'cv'          => 'horsepower',
        'ch'          => 'horsepower',
        'hp'          => 'horsepower',
        'puissance'   => 'horsepower',
        'horsepower'  => 'horsepower',
        // Pre-rigging kit
        'kit pre-rigging' => 'kit_label',
        'kit pre rigging' => 'kit_label',
        'pre-rigging'     => 'kit_label',
        'kit'             => 'kit_label',
        'kit pa ht'       => 'kit_cost',
        'kit pv ht'       => 'kit_price',
        // Propeller
        'helice'          => 'prop_label',
        'propeller'       => 'prop_label',
        'helice pa ht'    => 'prop_cost',
        'helice pv ht'    => 'prop_price',
        'propeller pa ht' => 'prop_cost',
        'propeller pv ht' => 'prop_price',
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
        $hasHorsepower  = in_array('horsepower', $headerMap, true);
        $hasKit         = in_array('kit_label', $headerMap, true);
        $hasProp        = in_array('prop_label', $headerMap, true);

        $created = 0; $updated = 0; $skipped = 0; $errors = [];
        $accCreated = 0; $accUpdated = 0; $linksAdded = 0;
        $seen = [];

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

            if ($hasHorsepower && $data['horsepower'] !== null) {
                $payload['horsepower'] = $data['horsepower'];
            } elseif (! $existing || empty($existing->horsepower)) {
                // Never over a figure the dealer set: only where there is none.
                $hp = Engine::horsepowerFromCode($data['code']);
                if ($hp !== null) $payload['horsepower'] = $hp;
            }

            if ($existing) {
                $existing->update($payload);
                $engine = $existing;
                $updated++;
            } else {
                $engine = Engine::create(array_merge($payload, ['company_id' => $companyId, 'accessory_ids' => []]));
                $created++;
            }

            // The row's kit and propeller, created once and linked here.
            $ids = $engine->accessoryIds();
            $before = count($ids);
            foreach ([
                [$hasKit,  EngineAccessory::TYPE_KIT,       $data['kit_label'],  $data['kit_cost'],  $data['kit_price']],
                [$hasProp, EngineAccessory::TYPE_PROPELLER, $data['prop_label'], $data['prop_cost'], $data['prop_price']],
            ] as [$present, $type, $label, $cost, $price]) {
                if (! $present) continue;
                $acc = $this->accessory($companyId, $type, $label, $cost, $price, $data['vat_rate'], $seen, $accCreated, $accUpdated);
                if ($acc && ! in_array((string) $acc->_id, $ids, true)) {
                    $ids[] = (string) $acc->_id;
                }
            }
            if (count($ids) > $before) {
                $linksAdded += count($ids) - $before;
                $engine->accessory_ids = $ids;
                $engine->save();
            }
        }

        return $this->result($created, $updated, $skipped, $errors, $accCreated, $accUpdated, $linksAdded);
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
        // Accent- and spacing-blind on both sides, so "HÉLICE", "Hélice" and
        // "HELICE" are one column, and the accented aliases above still work.
        $norm = fn (string $s) => preg_replace('/\s+/', ' ', mb_strtolower(trim(Str::ascii($s))));

        $aliases = [];
        foreach (self::HEADER_ALIASES as $alias => $field) {
            $aliases[$norm($alias)] = $field;
        }

        $map = [];
        foreach ($headerRow as $i => $cell) {
            $key = $norm((string) $cell);
            if (isset($aliases[$key]) && ! in_array($aliases[$key], $map, true)) {
                $map[$i] = $aliases[$key];
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
            'horsepower'  => null,
            'kit_label'   => '',
            'kit_cost'    => 0.0,
            'kit_price'   => 0.0,
            'prop_label'  => '',
            'prop_cost'   => 0.0,
            'prop_price'  => 0.0,
        ];
        foreach ($headerMap as $col => $field) {
            $raw = trim((string) ($rawRow[$col] ?? ''));
            switch ($field) {
                case 'brand':
                case 'code':
                case 'kit_label':
                case 'prop_label':
                    $out[$field] = $raw;
                    break;
                case 'horsepower':
                    $out['horsepower'] = Engine::horsepowerFromCode($raw);
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
                case 'kit_cost':
                case 'kit_price':
                case 'prop_cost':
                case 'prop_price':
                    if ($raw !== '') {
                        $clean = preg_replace('/[€$\s]/u', '', $raw);
                        // Cents, for the same reason as OptionImporter: a cell
                        // displayed as "18 500 €" can hold 18499.999999999996.
                        $out[$field] = round((float) str_replace(',', '.', $clean), 2);
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
        foreach (['kit_cost' => 'KIT PA HT', 'kit_price' => 'KIT PV HT',
                  'prop_cost' => 'HELICE PA HT', 'prop_price' => 'HELICE PV HT'] as $f => $col) {
            if ($data[$f] < 0) return "{$col} must be zero or positive.";
        }
        if ($data['vat_rate'] < 0 || $data['vat_rate'] > 100) {
            return 'TVA must be between 0 and 100 (or 0 and 1 if decimal).';
        }
        return null;
    }

    private function result(int $created = 0, int $updated = 0, int $skipped = 0, array $errors = [],
                            int $accessoriesCreated = 0, int $accessoriesUpdated = 0, int $linksAdded = 0): array
    {
        return [
            'created'             => $created,
            'updated'             => $updated,
            'skipped'             => $skipped,
            'errors'              => $errors,
            'accessories_created' => $accessoriesCreated,
            'accessories_updated' => $accessoriesUpdated,
            'links_added'         => $linksAdded,
        ];
    }

    /**
     * The kit or propeller on one row, found or created. Null when the row
     * names none — blank, or the file's way of saying the engine price
     * already covers it ("Inclus / non applicable" at €0).
     *
     * @param  array<string, EngineAccessory>  $seen  per-import cache, by type + name
     */
    private function accessory(string $companyId, string $type, string $label, float $cost, float $price,
                               float $vat, array &$seen, int &$created, int &$updated): ?EngineAccessory
    {
        $label = trim(preg_replace('/\s+/u', ' ', $label));
        if ($label === '') {
            return null;
        }
        $included = preg_match('/\b(inclu|non applicable|n\/a)/iu', Str::ascii($label));
        if ($included && $cost == 0.0 && $price == 0.0) {
            return null;
        }

        $key = $type . '|' . mb_strtolower($label);
        $payload = ['cost' => $cost, 'price' => $price, 'vat_rate' => $vat];

        if (isset($seen[$key])) {
            // Same accessory again further down the file: the last price wins,
            // but it is still one record.
            $seen[$key]->update($payload);
            return $seen[$key];
        }

        $existing = EngineAccessory::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('type', $type)
            ->get()
            ->first(fn ($a) => mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) $a->label))) === mb_strtolower($label));

        if ($existing) {
            $existing->fill($payload);
            if ($existing->isDirty()) {
                $existing->save();
                $updated++;
            }
            return $seen[$key] = $existing;
        }

        $created++;
        return $seen[$key] = EngineAccessory::create(array_merge($payload, [
            'company_id' => $companyId,
            'type'       => $type,
            'label'      => $label,
            'currency'   => 'EUR',
        ]));
    }
}
