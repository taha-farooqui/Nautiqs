<?php

namespace App\Console\Commands;

use App\Support\Pii\PiiMigrator;
use Illuminate\Console\Command;

/**
 * The rollback: writes every encrypted client value back as plaintext.
 *
 * Only for backing the feature out. The models keep encrypting new writes
 * until the code that does so is reverted too, so run this after that deploy,
 * not before. Dry run unless --execute.
 */
class PiiDecryptCommand extends Command
{
    protected $signature = 'pii:decrypt
                            {--execute : Write the changes (without this, only report them)}
                            {--company= : Only this dealership (company id)}';

    protected $description = 'Decrypt client data back to plaintext (rollback)';

    public function handle(PiiMigrator $pii): int
    {
        $pii = $pii->forCompany($this->option('company') ?: null);

        $write = (bool) $this->option('execute');

        if ($write && $this->input->isInteractive()
            && ! $this->confirm('This stores every client name, email and phone in plaintext again. Continue?', false)) {
            return self::FAILURE;
        }

        $result = $pii->decrypt($write);

        $this->table(
            ['Collection', 'Documents', $write ? 'Updated' : 'Would update', 'Fields'],
            collect($result)->map(fn ($r, $name) => [$name, $r['docs'], $r['changed'], $r['fields']])->values()
        );

        return self::SUCCESS;
    }
}
