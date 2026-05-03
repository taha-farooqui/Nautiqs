<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\CompanyBrand;
use App\Models\GlobalBrand;
use App\Services\CatalogueService;
use Illuminate\Console\Command;

/**
 * One-shot migration helper: now that the dealer's catalogue lives in the
 * company tier (CompanyBrand/Model/Variant/Option), every company that
 * already exists in production needs its workspace seeded so the quote
 * builder isn't empty after this release.
 *
 * Activates every published global brand for every company that doesn't
 * already have any company brands. Skips companies that already activated
 * brands manually so we never overwrite their customisations.
 *
 * Idempotent — safe to run multiple times.
 */
class BootstrapCompanyCatalogues extends Command
{
    protected $signature = 'catalogue:bootstrap {--force : Activate even if the company already has brands}';
    protected $description = 'Activate all published global brands into every company workspace (one-time migration)';

    public function handle(CatalogueService $catalogue): int
    {
        $companies = Company::all();
        $brands    = GlobalBrand::where('is_active', true)->get();

        if ($brands->isEmpty()) {
            $this->warn('No active global brands found — nothing to bootstrap.');
            return self::SUCCESS;
        }

        $force = (bool) $this->option('force');

        foreach ($companies as $company) {
            $existing = CompanyBrand::where('company_id', (string) $company->_id)->count();

            if ($existing > 0 && ! $force) {
                $this->line("• {$company->name} — skipped ({$existing} brands already in workspace)");
                continue;
            }

            $this->info("→ {$company->name}");
            foreach ($brands as $brand) {
                $catalogue->activateGlobalBrand((string) $company->_id, $brand);
                $this->line("    ✓ {$brand->name}");
            }
        }

        $this->info('Done.');
        return self::SUCCESS;
    }
}
