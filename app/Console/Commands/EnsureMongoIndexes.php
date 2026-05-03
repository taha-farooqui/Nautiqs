<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Idempotent index creation for the Mongo collections we hit on every page.
 * Without these, every WHERE on company_id is a full collection scan, which
 * is fatal once collections grow past a few hundred docs (and made worse by
 * cross-region Atlas latency).
 *
 * Run after every deploy or when adding a new collection:
 *   php artisan mongo:ensure-indexes
 *
 * Mongo's createIndex is idempotent — re-running is safe.
 */
class EnsureMongoIndexes extends Command
{
    protected $signature = 'mongo:ensure-indexes';
    protected $description = 'Create the Mongo indexes the app relies on (idempotent)';

    /**
     * One entry per index. Each is [collection, fields, options].
     * Compound indexes are listed in lookup order — most-selective field first.
     */
    private array $indexes = [
        // Quotes — heavy reads on the dashboard, list, and detail views.
        ['quotes',                  ['company_id' => 1, 'created_at' => -1]],
        ['quotes',                  ['company_id' => 1, 'status' => 1]],
        ['quotes',                  ['company_id' => 1, 'sent_at' => -1]],
        ['quotes',                  ['company_id' => 1, 'won_at' => -1]],
        ['quotes',                  ['company_id' => 1, 'lost_at' => -1]],
        ['quotes',                  ['number' => 1]],

        // Email log — the show page checks for prior sends per quote.
        ['email_log',               ['quote_id' => 1, 'type' => 1, 'status' => 1]],
        ['email_log',               ['company_id' => 1, 'sent_at' => -1]],

        // Notifications — header + sidebar both read unread counts.
        ['notifications',           ['user_id' => 1, 'created_at' => -1]],
        ['notifications',           ['user_id' => 1, 'read_at' => 1]],

        // Catalogue
        ['company_brands',          ['company_id' => 1, 'is_active' => 1]],
        ['company_brands',          ['company_id' => 1, 'global_brand_id' => 1]],
        ['company_boat_models',     ['company_id' => 1, 'company_brand_id' => 1, 'is_archived' => 1]],
        ['company_boat_variants',   ['company_id' => 1, 'company_model_id' => 1]],
        ['company_boat_variants',   ['company_id' => 1, 'global_variant_id' => 1]],
        ['company_options',         ['company_id' => 1, 'company_model_id' => 1]],

        // Global catalogue — read by the Available tab.
        ['global_boat_models',      ['brand_id' => 1, 'is_archived' => 1]],
        ['global_boat_variants',    ['model_id' => 1, 'is_archived' => 1]],
        ['global_options',          ['model_id' => 1, 'is_archived' => 1]],

        // Tenant-scoped lookups
        ['clients',                 ['company_id' => 1, 'last_name' => 1]],
        ['email_templates',         ['company_id' => 1, 'type' => 1]],
        ['users',                   ['company_id' => 1]],
        ['users',                   ['email' => 1]],
    ];

    public function handle(): int
    {
        $connection = DB::connection('mongodb');

        foreach ($this->indexes as [$collection, $fields]) {
            $name = $collection . '_' . implode('_', array_keys($fields));
            try {
                $connection->getCollection($collection)->createIndex($fields, ['name' => $name]);
                $this->line("  ✓ {$name}");
            } catch (\Throwable $e) {
                $this->error("  ✗ {$name} — {$e->getMessage()}");
            }
        }

        $this->info('Done.');
        return self::SUCCESS;
    }
}
