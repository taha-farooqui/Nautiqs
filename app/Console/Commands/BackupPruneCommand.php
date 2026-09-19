<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;

/**
 * Frees server space by deleting archives that are past retention AND have
 * already been downloaded, so a copy exists somewhere other than this box.
 *
 * Scheduled daily. On its own it deletes nothing until the superadmin has
 * downloaded the old archives from the Backups page — which is the intended
 * rhythm: the server holds the last 30 days, anything older leaves the server
 * only after it has been taken off it.
 *
 * --force skips the "has been downloaded" requirement for the rare case where
 * archives must go regardless. It still honours retention and keep_minimum, so
 * even then the newest archives cannot be removed.
 */
class BackupPruneCommand extends Command
{
    protected $signature = 'backup:prune
                            {--force : Delete past-retention archives even if they were never downloaded}';

    protected $description = 'Delete downloaded backup archives that are past the retention window';

    public function handle(BackupService $backups): int
    {
        $force = (bool) $this->option('force');

        if ($force && ! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        // Scheduled runs wait out the grace period; the admin page's button
        // goes through the controller and does not.
        $result = $backups->prune($force, respectGrace: true);

        $this->info(sprintf(
            '%d archive(s) deleted, %s freed, %d kept.',
            $result['deleted'],
            $backups->humanBytes($result['freed_bytes']),
            $result['kept'],
        ));

        return self::SUCCESS;
    }

    private function confirmToProceed(): bool
    {
        if (! $this->input->isInteractive()) {
            return true;      // scripted use; the flag itself is the consent
        }

        return $this->confirm(
            'This deletes past-retention archives that were never downloaded. Continue?',
            false,
        );
    }
}
