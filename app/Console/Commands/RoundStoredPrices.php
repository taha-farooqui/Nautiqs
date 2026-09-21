<?php

namespace App\Console\Commands;

use App\Models\CompanyBoatVariant;
use App\Models\CompanyOption;
use App\Models\Engine;
use Illuminate\Console\Command;

/**
 * Rounds money already in the database to cents.
 *
 * Imports used to round only after an FX conversion, so rows priced in euros
 * kept whatever float the spreadsheet held — a cell displayed as "658 €"
 * carrying 658.3333333333334. Those values are on screen in the boat editor and
 * on quotes. The importers and every save path now round on write; this fixes
 * what was stored before that.
 *
 * Only the live price fields are touched. `original_price` / `original_cost`
 * record what the dealer actually typed or uploaded before conversion, so they
 * are left exactly as they were.
 *
 * Idempotent: a second run reports nothing to do.
 */
class RoundStoredPrices extends Command
{
    protected $signature = 'prices:round
                            {--execute : Write the changes. Without it this is a dry run.}';

    protected $description = 'Round stored option, engine and version prices to two decimals';

    /** field => model, for the three collections that hold money. */
    private const TARGETS = [
        CompanyOption::class      => ['price', 'cost'],
        Engine::class             => ['price', 'cost'],
        CompanyBoatVariant::class => ['base_price', 'cost'],
    ];

    public function handle(): int
    {
        $execute = (bool) $this->option('execute');

        if (! $execute) {
            $this->warn('Dry run — nothing will be written. Add --execute to apply.');
        }

        $grandTotal = 0;

        foreach (self::TARGETS as $model => $fields) {
            $label   = class_basename($model);
            $changed = 0;
            $samples = [];

            // withoutGlobalScopes: this runs from the console with no
            // authenticated user, and it must cover every dealership.
            foreach ($model::withoutGlobalScopes()->cursor() as $doc) {
                $update = [];

                foreach ($fields as $field) {
                    $value = $doc->{$field};
                    if ($value === null || $value === '') {
                        continue;
                    }

                    $rounded = round((float) $value, 2);
                    // Compare as strings: 658.33 !== 658.3300000000001 as
                    // floats even when both round to the same cents.
                    if ((string) $rounded !== (string) (float) $value) {
                        $update[$field] = $rounded;
                        if (count($samples) < 5) {
                            $samples[] = sprintf(
                                '%s %s: %s → %s',
                                $doc->label ?? $doc->name ?? $doc->code ?? (string) $doc->_id,
                                $field,
                                $value,
                                $rounded,
                            );
                        }
                    }
                }

                if ($update) {
                    $changed++;
                    if ($execute) {
                        $doc->forceFill($update)->saveQuietly();
                    }
                }
            }

            $grandTotal += $changed;
            $this->line(sprintf('%-20s %d document(s) %s', $label, $changed, $execute ? 'updated' : 'would change'));
            foreach ($samples as $s) {
                $this->line('    ' . $s);
            }
        }

        $this->newLine();
        $this->info($execute
            ? "Done: {$grandTotal} document(s) updated."
            : "{$grandTotal} document(s) would change. Re-run with --execute to apply.");

        return self::SUCCESS;
    }
}
