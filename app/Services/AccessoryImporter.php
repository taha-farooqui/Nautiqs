<?php

namespace App\Services;

use App\Models\Accessory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Bulk import of the dealer's accessories from XLSX or CSV, in the layout the
 * export writes:
 *
 *   NOM | PRIX ACHAT HT | PRIX VENTE HT | TVA | DEVISE
 *
 * A row whose name matches an existing accessory (case-insensitive) updates
 * it, so "export, edit the prices, import back" is the way to reprice the
 * list. Nothing is ever deleted by an import.
 */
class AccessoryImporter
{
    public const MAX_ROWS = 5000;

    /** Accepted header spellings per field, compared accent- and case-blind. */
    private const ALIASES = [
        'label'    => ['nom', 'name', 'label', 'libelle', 'designation', 'accessoire', 'accessory', 'article'],
        'cost'     => ['prix achat ht', 'prix achat', 'pa ht', 'pa', 'cout', 'cost', 'purchase price', 'prix d achat'],
        'price'    => ['prix vente ht', 'prix vente', 'pv ht', 'pv', 'prix', 'price', 'selling price', 'prix de vente'],
        'vat_rate' => ['tva', 'vat', 'vat rate', 'taux tva', 'tva %', 'vat %'],
        'currency' => ['devise', 'currency', 'monnaie'],
    ];

    public function import(UploadedFile $file, string $companyId, float $defaultVat = 20): array
    {
        $rows = $this->read($file);
        $out  = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];

        if (count($rows) < 2) {
            $out['errors'][] = ['row' => 1, 'message' => __('The file has no data rows.')];
            return $out;
        }

        $map = $this->mapHeaders(array_shift($rows));
        if (! isset($map['label'], $map['price'])) {
            $out['errors'][] = ['row' => 1, 'message' => __('Missing column: NOM and PRIX VENTE HT are required.')];
            return $out;
        }
        if (count($rows) > self::MAX_ROWS) {
            $out['errors'][] = ['row' => 1, 'message' => __('Too many rows (max :max).', ['max' => self::MAX_ROWS])];
            return $out;
        }

        $existing = Accessory::withoutGlobalScopes()->where('company_id', $companyId)->get()
            ->keyBy(fn ($a) => mb_strtolower(trim((string) $a->label)));

        foreach ($rows as $i => $raw) {
            $rowNumber = $i + 2;
            $cell = fn (string $f) => isset($map[$f]) ? trim((string) ($raw[$map[$f]] ?? '')) : '';

            $label = $cell('label');
            if ($label === '' && implode('', array_map('trim', array_map('strval', $raw))) === '') {
                $out['skipped']++; // blank line
                continue;
            }
            if ($label === '') {
                $out['errors'][] = ['row' => $rowNumber, 'message' => __('Name is required.')];
                continue;
            }

            $price = $this->number($cell('price'));
            if ($price === null) {
                $out['errors'][] = ['row' => $rowNumber, 'message' => __('Selling price is missing or not a number.')];
                continue;
            }
            $costRaw = $cell('cost');
            $cost    = $costRaw === '' ? null : $this->number($costRaw);
            if ($costRaw !== '' && $cost === null) {
                $out['errors'][] = ['row' => $rowNumber, 'message' => __('Purchase price is not a number.')];
                continue;
            }
            $vatRaw = $cell('vat_rate');
            $vat    = $vatRaw === '' ? $defaultVat : $this->number(rtrim($vatRaw, '% '));
            // A spreadsheet formatted as a percentage hands over 0.2 for 20 %.
            if ($vat !== null && $vat > 0 && $vat < 1) {
                $vat *= 100;
            }
            if ($vat === null || $vat < 0 || $vat > 100) {
                $out['errors'][] = ['row' => $rowNumber, 'message' => __('VAT must be between 0 and 100.')];
                continue;
            }
            $currency = strtoupper($cell('currency') ?: 'EUR');
            $currency = ['€' => 'EUR', '$' => 'USD'][$currency] ?? $currency;
            if (! in_array($currency, Accessory::CURRENCIES, true)) {
                $out['errors'][] = ['row' => $rowNumber, 'message' => __('Currency must be EUR or USD.')];
                continue;
            }

            $fields = [
                'label'    => $label,
                'cost'     => $cost !== null ? round($cost, 2) : null,
                'price'    => round($price, 2),
                'vat_rate' => round($vat, 2),
                'currency' => $currency,
            ];

            $key = mb_strtolower($label);
            if (isset($existing[$key])) {
                $existing[$key]->fill($fields)->save();
                $out['updated']++;
            } else {
                $existing[$key] = Accessory::create($fields + ['company_id' => $companyId]);
                $out['created']++;
            }
        }

        return $out;
    }

    /** First sheet of an XLSX, or a CSV with its delimiter sniffed. */
    private function read(UploadedFile $file): array
    {
        $ext = strtolower($file->getClientOriginalExtension());
        if (! in_array($ext, ['csv', 'txt'], true)) {
            $sheets = Xlsx::read($file->getRealPath());
            return $sheets ? reset($sheets) : [];
        }

        $text = (string) file_get_contents($file->getRealPath());
        $text = preg_replace('/^\xEF\xBB\xBF/', '', $text);
        $first = strtok($text, "\n") ?: '';
        $sep = substr_count($first, ';') > substr_count($first, ',') ? ';'
            : (substr_count($first, "\t") > substr_count($first, ',') ? "\t" : ',');

        $rows = [];
        foreach (preg_split('/\r\n|\n|\r/', $text) as $line) {
            if ($line !== '') {
                $rows[] = str_getcsv($line, $sep);
            }
        }
        return $rows;
    }

    /** @return array<string, int> field => column index, first match wins */
    private function mapHeaders(array $headers): array
    {
        $norm = fn (string $s) => trim(preg_replace('/[^a-z0-9%]+/', ' ', mb_strtolower(Str::ascii($s))));

        $map = [];
        foreach ($headers as $col => $header) {
            $h = $norm((string) $header);
            foreach (self::ALIASES as $field => $aliases) {
                if (! isset($map[$field]) && in_array($h, array_map($norm, $aliases), true)) {
                    $map[$field] = $col;
                    break;
                }
            }
        }
        return $map;
    }

    /** "1 234,50 €", "1234.5", "1,234.50" → float; null when not a number. */
    private function number(string $v): ?float
    {
        $v = str_replace(["\u{00A0}", "\u{202F}", ' ', '€', '$'], '', $v);
        if ($v === '') {
            return null;
        }
        if (str_contains($v, ',') && str_contains($v, '.')) {
            // Whichever comes last is the decimal mark.
            $v = strrpos($v, ',') > strrpos($v, '.')
                ? str_replace(',', '.', str_replace('.', '', $v))
                : str_replace(',', '', $v);
        } else {
            $v = str_replace(',', '.', $v);
        }
        return is_numeric($v) ? (float) $v : null;
    }
}
