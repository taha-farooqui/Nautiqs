<?php

namespace App\Services;

use App\Models\CompanyBoatModel;
use App\Models\CompanyOption;
use Illuminate\Http\UploadedFile;

/**
 * Bulk-import for options (priced add-ons). Mirrors the column layout of
 * the client's existing-software export so dealers can re-use files they
 * already have:
 *
 *   CODE  | CODE MODELE | MARQUE | DESIGNATION FR | DESIGNATION GB |
 *   FAMILLE | PA HT | PV HT | TVA | OPTION CHANTIER
 *
 * Each row creates/updates one CompanyOption attached to the boat whose
 * `internal_code` matches CODE MODELE. Match is upsert on
 * (company_id, company_model_id, code) so re-uploading the same file with
 * new prices just updates in place.
 *
 * Dependency-free reader (ZipArchive + DOMDocument for XLSX, fgetcsv for
 * CSV) — same approach as EngineImporter.
 */
class OptionImporter
{
    /** Header text (lowercased + trimmed) → internal field. EN + FR aliases. */
    private const HEADER_ALIASES = [
        'code'              => 'code',
        'sku'               => 'code',

        'code modele'       => 'model_code',
        'code modèle'       => 'model_code',
        'model code'        => 'model_code',
        'boat code'         => 'model_code',

        'marque'            => 'brand',
        'brand'             => 'brand',

        'designation fr'    => 'label',
        'désignation fr'    => 'label',
        'designation'       => 'label',
        'désignation'       => 'label',
        'label'             => 'label',
        'libellé'           => 'label',
        'libelle'           => 'label',

        'designation gb'    => 'label_en',
        'désignation gb'    => 'label_en',
        'designation en'    => 'label_en',
        'label en'          => 'label_en',
        'english label'     => 'label_en',

        'famille'           => 'category',
        'category'          => 'category',
        'categorie'         => 'category',
        'catégorie'         => 'category',

        'pa ht'             => 'cost',
        'cost'              => 'cost',
        'cost ht'           => 'cost',
        'prix achat'        => 'cost',
        'prix achat ht'     => 'cost',
        'purchase price'    => 'cost',

        'pv ht'             => 'price',
        'price'             => 'price',
        'price ht'          => 'price',
        'public ht'         => 'price',
        'prix vente'        => 'price',
        'prix vente ht'     => 'price',
        'prix ht'           => 'price',

        'tva'               => 'vat_rate',
        'vat'               => 'vat_rate',
        'vat rate'          => 'vat_rate',
        'taux tva'          => 'vat_rate',

        'option chantier'   => 'yard_option',
        'yard option'       => 'yard_option',
        'chantier'          => 'yard_option',
    ];

    private const REQUIRED = ['code', 'model_code', 'label', 'price'];
    private const MAX_ROWS = 5000;

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
                'message' => 'Could not detect any known column. Expected at least CODE, CODE MODELE, DESIGNATION FR, PV HT.',
            ]]);
        }

        $missing = array_diff(self::REQUIRED, array_values($headerMap));
        if (! empty($missing)) {
            $human = array_map(fn ($m) => match ($m) {
                'code'       => 'CODE',
                'model_code' => 'CODE MODELE',
                'label'      => 'DESIGNATION FR',
                'price'      => 'PV HT',
                default      => $m,
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

        // Cache boats by internal_code so we look up each one only once
        // even when the file has 80 rows pointing at the same boat.
        $boatCache = [];
        $resolveBoat = function (string $internalCode) use (&$boatCache, $companyId) {
            $key = mb_strtolower(trim($internalCode));
            if (array_key_exists($key, $boatCache)) {
                return $boatCache[$key];
            }
            $boat = CompanyBoatModel::where('company_id', $companyId)
                ->whereRaw([
                    'internal_code' => [
                        '$regex'   => '^' . preg_quote($internalCode, '/') . '$',
                        '$options' => 'i',
                    ],
                ])
                ->first();
            return $boatCache[$key] = $boat;
        };

        $created = 0; $updated = 0; $skipped = 0; $errors = [];

        foreach ($rows as $i => $rawRow) {
            $rowNumber = $i + 2;

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

            $boat = $resolveBoat($data['model_code']);
            if (! $boat) {
                $errors[] = [
                    'row'     => $rowNumber,
                    'message' => 'No boat found with internal code "' . $data['model_code'] . '". Create the boat first, then re-import.',
                ];
                continue;
            }

            // Upsert by (company_id, company_model_id, code) — case-insensitive
            // on the code so "ANT7OB_TRA_0001" and "ant7ob_tra_0001" map to
            // the same option.
            $existing = CompanyOption::where('company_id', $companyId)
                ->where('company_model_id', (string) $boat->_id)
                ->whereRaw([
                    'code' => [
                        '$regex'   => '^' . preg_quote($data['code'], '/') . '$',
                        '$options' => 'i',
                    ],
                ])
                ->first();

            $payload = [
                'category'    => $data['category'],
                'label'       => $data['label'],
                'label_en'    => $data['label_en'],
                'brand'       => $data['brand'],
                'code'        => $data['code'],
                'price'       => $data['price'],
                'cost'        => $data['cost'],
                'vat_rate'    => $data['vat_rate'],
                'yard_option' => $data['yard_option'],
                'currency'    => 'EUR',
                'is_archived' => false,
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
            'code'        => '',
            'model_code'  => '',
            'brand'       => '',
            'label'       => '',
            'label_en'    => '',
            'category'    => '',
            'cost'        => 0.0,
            'price'       => 0.0,
            'vat_rate'    => 20.0,
            'yard_option' => false,
        ];

        foreach ($headerMap as $col => $field) {
            $raw = trim((string) ($rawRow[$col] ?? ''));
            switch ($field) {
                case 'code':
                case 'model_code':
                case 'brand':
                case 'label':
                case 'label_en':
                case 'category':
                    $out[$field] = $raw;
                    break;
                case 'cost':
                case 'price':
                    if ($raw !== '') {
                        $out[$field] = (float) str_replace([' ', ','], ['', '.'], $raw);
                    }
                    break;
                case 'vat_rate':
                    if ($raw !== '') {
                        $v = (float) str_replace([' ', ','], ['', '.'], $raw);
                        // Client file stores TVA as 0.2 (= 20%). Detect the
                        // decimal form and scale up so the DB always holds
                        // the percentage representation Nautiqs uses.
                        if ($v > 0 && $v <= 1) {
                            $v = $v * 100;
                        }
                        $out['vat_rate'] = $v;
                    }
                    break;
                case 'yard_option':
                    $lower = mb_strtolower($raw);
                    $out['yard_option'] = in_array($lower, ['1', 'yes', 'y', 'true', 'oui', 'o'], true);
                    break;
            }
        }
        return $out;
    }

    private function validate(array $data): ?string
    {
        if ($data['code'] === '')       return 'CODE is required.';
        if (mb_strlen($data['code']) > 120) return 'CODE must be 120 characters or fewer.';
        if ($data['model_code'] === '') return 'CODE MODELE is required.';
        if ($data['label'] === '')      return 'DESIGNATION FR is required.';
        if (mb_strlen($data['label']) > 255) return 'DESIGNATION FR must be 255 characters or fewer.';
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
