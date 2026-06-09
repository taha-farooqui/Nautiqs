<?php

namespace App\Console\Commands;

use App\Models\GlobalEngine;
use Illuminate\Console\Command;

/**
 * One-shot cleanup: delete every platform/global engine.
 *
 * Engines are now dealer-owned only — each dealership adds or imports its
 * own and nothing is provided by default. This wipes the legacy global
 * engine library so a brand-new dealer sees an empty Engines screen.
 *
 * Idempotent: running it again simply deletes 0 rows. Does NOT touch the
 * per-company `engines` collection (dealers' own engines).
 */
class PurgeGlobalEngines extends Command
{
    protected $signature = 'engines:purge-global';
    protected $description = 'Delete all platform/global engines (engines are dealer-owned only)';

    public function handle(): int
    {
        $count = GlobalEngine::count();
        GlobalEngine::query()->delete();
        $this->info("Removed {$count} global engine(s). Dealers now see only their own engines.");

        return self::SUCCESS;
    }
}
