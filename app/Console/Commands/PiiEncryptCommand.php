<?php

namespace App\Console\Commands;

use App\Models\BackupRun;
use App\Support\Pii\PiiMigrator;
use Illuminate\Console\Command;

/**
 * Encrypts client data written before encryption existed.
 *
 * New writes are already encrypted by the models; this catches up the
 * documents that predate them. Safe to run any number of times — a value that
 * is already encrypted is left alone, so a second run changes nothing.
 *
 * Dry run unless --execute. With --execute it will not start without a
 * successful backup from the last hour: if anything goes wrong, that archive
 * and pii:decrypt are the way back.
 */
class PiiEncryptCommand extends Command
{
    protected $signature = 'pii:encrypt
                            {--execute : Write the changes (without this, only report them)}
                            {--company= : Only this dealership (company id)}';

    protected $description = 'Encrypt client data still stored in plaintext';

    public function handle(PiiMigrator $pii): int
    {
        $pii = $pii->forCompany($this->option('company') ?: null);

        $write = (bool) $this->option('execute');

        if ($write) {
            $recent = BackupRun::where('status', BackupRun::STATUS_OK)
                ->where('started_at', '>=', now()->subHour())
                ->exists();

            if (! $recent) {
                $this->error('No successful backup in the last hour. Run `php artisan backup:run` first.');
                return self::FAILURE;
            }
        }

        $this->info($write ? 'Encrypting…' : 'Dry run — nothing will be written. Add --execute to apply.');

        $result = $pii->encrypt($write);

        $this->table(
            ['Collection', 'Documents', $write ? 'Updated' : 'Would update', 'Fields'],
            collect($result)->map(fn ($r, $name) => [$name, $r['docs'], $r['changed'], $r['fields']])->values()
        );

        $total = array_sum(array_column($result, 'changed'));
        $this->info($write
            ? "{$total} document(s) encrypted. Run `php artisan pii:scan` to confirm."
            : "{$total} document(s) would be encrypted.");

        return self::SUCCESS;
    }
}
