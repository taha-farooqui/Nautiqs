<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-shot repair: quotes written while the model cast these fields to 'array'
 * stored them as JSON STRINGS instead of embedded documents. Reads were fine
 * (the cast decoded them) but MongoDB can't traverse a string, so nested
 * queries — the quotes list's brand/model filters, the client-name search —
 * matched nothing. Decodes each string field back into a real document.
 *
 * Idempotent: fields that are already documents are left untouched.
 */
class FixQuoteSnapshotTypes extends Command
{
    protected $signature = 'quotes:fix-snapshot-types {--dry-run : Report what would change without writing}';
    protected $description = 'Convert JSON-string quote snapshots into native embedded documents';

    /** Fields the old 'array' cast serialised. */
    private const FIELDS = [
        'client_snapshot', 'model_snapshot', 'variant_snapshot', 'included_equipment',
        'options', 'custom_items', 'category_discounts', 'trade_in', 'totals',
        'tracking', 'terms',
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $col    = DB::connection('mongodb')->getDatabase()->selectCollection('quotes');

        $scanned = 0; $touched = 0; $fieldsFixed = 0;

        foreach ($col->find([]) as $doc) {
            $scanned++;
            $id  = $doc['_id'];
            $set = [];

            foreach (self::FIELDS as $field) {
                $value = $doc[$field] ?? null;
                if (! is_string($value) || $value === '') {
                    continue;                       // already a document, or empty
                }
                $decoded = json_decode($value, true);
                if (! is_array($decoded)) {
                    $this->warn("  {$doc['number']}: {$field} is a string but not valid JSON — left alone");
                    continue;
                }
                $set[$field] = $decoded;
                $fieldsFixed++;
            }

            if (! $set) {
                continue;
            }

            $touched++;
            $this->line(sprintf('  %-16s %s', $doc['number'] ?? (string) $id, implode(', ', array_keys($set))));

            if (! $dryRun) {
                $col->updateOne(['_id' => $id], ['$set' => $set]);
            }
        }

        $this->info(sprintf(
            '%s%d quote(s) scanned, %d updated, %d field(s) converted.',
            $dryRun ? '[dry-run] ' : '', $scanned, $touched, $fieldsFixed
        ));

        return self::SUCCESS;
    }
}
