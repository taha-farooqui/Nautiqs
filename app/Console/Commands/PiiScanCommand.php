<?php

namespace App\Console\Commands;

use App\Support\Pii\PiiMigrator;
use Illuminate\Console\Command;

/**
 * Read-only check that client data is encrypted at rest.
 *
 * For every personal field, counts the values still in plaintext, the values
 * encrypted with the current key, and any ciphertext the current key cannot
 * open (which means APP_KEY has changed). Then sweeps every other field of
 * the same collections for anything shaped like an email address, which is
 * how a copy of client data the encryption does not cover would show up.
 *
 * Exits non-zero while anything is left, so it can gate a deploy.
 */
class PiiScanCommand extends Command
{
    protected $signature = 'pii:scan
                            {--company= : Only this dealership (company id)}';

    protected $description = 'Report client data still stored in plaintext';

    public function handle(PiiMigrator $pii): int
    {
        $pii = $pii->forCompany($this->option('company') ?: null);

        $scan = $pii->scan();

        $this->table(
            ['Collection', 'Documents', 'Encrypted', 'Plaintext', 'Unreadable'],
            collect($scan)->map(fn ($s, $name) => [$name, $s['docs'], $s['encrypted'], $s['plaintext'], $s['unreadable']])->values()
        );

        foreach ($scan as $name => $s) {
            foreach ($s['samples'] as $sample) {
                $this->line("  plaintext: {$name} {$sample}");
            }
        }

        $strays = $pii->strays();
        if ($strays) {
            $this->newLine();
            $this->warn('Email addresses outside the encrypted fields:');
            foreach ($strays as $path => $n) {
                $this->line("  {$path}  ×{$n}");
            }
        }

        $plain      = array_sum(array_column($scan, 'plaintext'));
        $unreadable = array_sum(array_column($scan, 'unreadable'));

        $this->newLine();
        if ($unreadable > 0) {
            $this->error("{$unreadable} value(s) cannot be decrypted with the current APP_KEY.");
        }
        if ($plain === 0 && $unreadable === 0 && ! $strays) {
            $this->info('All client data is encrypted.');
            return self::SUCCESS;
        }

        $this->warn("{$plain} plaintext value(s), " . count($strays) . ' stray field(s).');
        return self::FAILURE;
    }
}
