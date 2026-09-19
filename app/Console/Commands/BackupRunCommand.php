<?php

namespace App\Console\Commands;

use App\Exceptions\BackupException;
use App\Models\BackupRun;
use App\Services\BackupService;
use Illuminate\Console\Command;

/**
 * Takes one backup. Scheduled twice a day (see routes/console.php) and
 * available by hand for anything the admin page can't cover.
 *
 * Exits non-zero on failure so the scheduler's onFailure hook fires and a
 * human hears about it — a backup job that fails quietly is worse than none,
 * because the listing still looks healthy.
 */
class BackupRunCommand extends Command
{
    protected $signature = 'backup:run
                            {--trigger=scheduled : scheduled|manual — recorded on the run}
                            {--prune : Also run retention afterwards}';

    protected $description = 'Back up the database, uploaded files and .env into one archive';

    public function handle(BackupService $backups): int
    {
        $trigger = $this->option('trigger') === BackupRun::TRIGGER_MANUAL
            ? BackupRun::TRIGGER_MANUAL
            : BackupRun::TRIGGER_SCHEDULED;

        $this->info('Starting backup…');

        try {
            $run = $backups->run($trigger);
        } catch (BackupException $e) {
            $this->error('Backup failed: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Backup complete: %s (%s, %d documents, %.1fs)',
            $run->filename,
            $run->humanSize(),
            $run->document_count,
            $run->duration_ms / 1000,
        ));

        if ($this->option('prune')) {
            $result = $backups->prune();
            $this->line(sprintf(
                'Retention: %d archive(s) removed, %s freed, %d kept.',
                $result['deleted'],
                $backups->humanBytes($result['freed_bytes']),
                $result['kept'],
            ));
        }

        return self::SUCCESS;
    }
}
