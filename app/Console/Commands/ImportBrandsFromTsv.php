<?php

namespace App\Console\Commands;

use App\Models\GlobalBrand;
use Illuminate\Console\Command;

/**
 * One-shot import of the full boat-brand catalogue from a TSV file
 * (slug<TAB>NAME — one per line). Used to seed the platform's
 * `brands` collection from external sources such as the bandofboats.com
 * "Toutes les marques" page.
 *
 *   php artisan brands:import-tsv                         # default file
 *   php artisan brands:import-tsv --file=path/to/file.tsv
 *   php artisan brands:import-tsv --wipe                  # delete existing first
 *   php artisan brands:import-tsv --dry-run               # report only
 */
class ImportBrandsFromTsv extends Command
{
    protected $signature = 'brands:import-tsv
        {--file=database/data/brands-bandofboats.tsv : Path to the TSV (slug<TAB>NAME)}
        {--wipe : Truncate the brands collection before importing}
        {--dry-run : Show what would happen without writing}';

    protected $description = 'Import boat brands into GlobalBrand from a TSV file';

    public function handle(): int
    {
        $path = base_path($this->option('file'));
        if (! is_file($path)) {
            $this->error("File not found: {$path}");
            return self::FAILURE;
        }

        $rows = collect(file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES))
            ->map(fn ($line) => array_pad(explode("\t", $line, 2), 2, null))
            ->filter(fn ($row) => ! empty($row[1]))
            ->map(fn ($row) => [
                'slug'  => trim((string) $row[0]),
                'name'  => $this->titleCase(trim((string) $row[1])),
            ])
            ->unique('name')
            ->values();

        $this->info("Parsed {$rows->count()} unique brand rows from " . $this->option('file'));

        if ($this->option('dry-run')) {
            $rows->take(15)->each(fn ($r) => $this->line(" • {$r['name']}"));
            if ($rows->count() > 15) {
                $this->line("   …and " . ($rows->count() - 15) . " more");
            }
            $this->warn('Dry run — nothing written.');
            return self::SUCCESS;
        }

        if ($this->option('wipe')) {
            $deleted = GlobalBrand::query()->delete();
            $this->warn("Wiped {$deleted} existing brands.");
        }

        $created = 0;
        $updated = 0;
        $order   = 0;

        foreach ($rows as $row) {
            $existing = GlobalBrand::where('name', $row['name'])->first();
            if ($existing) {
                $existing->update([
                    'name'          => $row['name'],
                    'display_order' => $order++,
                    'is_active'     => true,
                ]);
                $updated++;
            } else {
                GlobalBrand::create([
                    'name'          => $row['name'],
                    'display_order' => $order++,
                    'is_active'     => true,
                ]);
                $created++;
            }
        }

        $this->info("Created: {$created}  ·  Updated: {$updated}  ·  Total: " . ($created + $updated));
        return self::SUCCESS;
    }

    /**
     * Normalise an UPPER-CASE brand name into Title Case while preserving
     * brand-specific stylings:
     *   - Single-token names of <= 3 letters stay UPPER (ACM, ZAR, AB).
     *   - Multi-token names: only the FIRST token may stay UPPER if it's
     *     a short acronym (AB Inflatables, 3D Tender, X-Yachts). The rest
     *     are Title Cased ("Williams Jet Tenders", not "Williams JET …").
     *   - Tokens containing digits stay UPPER (3B, 3D).
     */
    private function titleCase(string $name): string
    {
        $lower  = mb_strtolower(trim(preg_replace('/\s+/', ' ', $name)), 'UTF-8');
        $tokens = preg_split('/\s+/u', $lower);

        // Single-word brand: keep short ones in all-caps (ACM, ZAR).
        if (count($tokens) === 1) {
            $w = $tokens[0];
            if (mb_strlen($w) <= 3) {
                return mb_strtoupper($w, 'UTF-8');
            }
            return $this->capitalizeWord($w);
        }

        // Multi-word: special-case the first token, plain-cap the rest.
        $first = array_shift($tokens);
        $head  = $this->shouldStayUpper($first)
            ? mb_strtoupper($first, 'UTF-8')
            : $this->capitalizeWord($first);
        $tail = array_map(fn ($w) => $this->capitalizeWord($w), $tokens);

        return $head . ' ' . implode(' ', $tail);
    }

    private function shouldStayUpper(string $token): bool
    {
        // Tokens that mix letters and digits, or are <= 3 letters, are
        // treated as acronyms when they appear as the leading token.
        if (preg_match('/\d/u', $token)) return true;
        return mb_strlen($token, 'UTF-8') <= 3;
    }

    /**
     * Capitalise a word, preserving internal hyphens (x-yachts → X-Yachts).
     */
    private function capitalizeWord(string $word): string
    {
        return preg_replace_callback(
            '/([\p{L}\p{N}]+)/u',
            fn ($m) => mb_strtoupper(mb_substr($m[1], 0, 1, 'UTF-8'), 'UTF-8')
                . mb_substr($m[1], 1, null, 'UTF-8'),
            $word
        );
    }
}
