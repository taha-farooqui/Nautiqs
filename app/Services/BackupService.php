<?php

namespace App\Services;

use App\Exceptions\BackupException;
use App\Models\BackupRun;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Process;
use Throwable;

/**
 * Takes and manages full-database backups.
 *
 * An archive is a single .tar.gz holding a mongodump of the whole database,
 * the uploaded files (dealership logos), and .env. Nothing about it is
 * Nautiqs-specific on the way back in: `mongorestore --gzip --archive` reads
 * the dump directly, so a restore never depends on this class still existing.
 *
 * Lifecycle, and why it is shaped this way:
 *
 *   run() writes an archive and a BackupRun row describing it.
 *   For `retention_days` the archive stays on the server as a fast restore
 *   point. After that it is "archivable": the superadmin downloads it to their
 *   own machine, and only once it has actually been served does prune() become
 *   willing to delete the file and reclaim the space. The row is kept either
 *   way, so the history of what was taken and where it went survives the file.
 *
 * Nothing here deletes a backup the superadmin has not been offered first, and
 * `keep_minimum` newest archives are exempt from every deletion path.
 */
class BackupService
{
    private const LOCK_KEY     = 'backup:running';
    private const LOCK_SECONDS = 900;

    /**
     * Take a backup. Returns the persisted, successful BackupRun.
     *
     * @throws BackupException on any failure — the row is recorded as failed
     *                         and the notification sent before it is thrown.
     */
    public function run(string $trigger = BackupRun::TRIGGER_SCHEDULED, ?string $actorEmail = null): BackupRun
    {
        $lock = cache()->lock(self::LOCK_KEY, self::LOCK_SECONDS);

        if (! $lock->get()) {
            throw new BackupException(__('A backup is already running. Try again in a moment.'));
        }

        $startedAt = now();
        $run = BackupRun::create([
            'filename'   => null,
            'status'     => BackupRun::STATUS_RUNNING,
            'trigger'    => $trigger,
            'started_by' => $actorEmail,
            'started_at' => $startedAt,
        ]);

        $work = null;

        try {
            $this->assertDiskSpace();

            $work = $this->makeWorkDir();
            $contents = $this->stage($work);
            $documents = $this->documentCount();

            $filename = 'nautiqs-' . $startedAt->format('Ymd-His') . '.tar.gz';
            $target   = $this->archivePath($filename);

            $this->compress($work, $target);
            @chmod($target, 0600);
            $this->verify($target);

            $run->update([
                'filename'       => $filename,
                'status'         => BackupRun::STATUS_OK,
                'size_bytes'     => filesize($target),
                'checksum'       => hash_file('sha256', $target),
                'contents'       => $contents,
                'document_count' => $documents,
                'duration_ms'    => (int) ($startedAt->diffInMilliseconds(now())),
                'finished_at'    => now(),
            ]);

            return $run->refresh();
        } catch (Throwable $e) {
            $run->update([
                'status'      => BackupRun::STATUS_FAILED,
                'error'       => $this->shorten($e->getMessage()),
                'duration_ms' => (int) ($startedAt->diffInMilliseconds(now())),
                'finished_at' => now(),
            ]);

            // A half-written archive is worse than none: it looks like a
            // restore point in the listing and is not one.
            if (! empty($filename) && is_file($this->archivePath($filename))) {
                @unlink($this->archivePath($filename));
            }

            $this->notifyFailure($run, $e);

            throw $e instanceof BackupException
                ? $e
                : new BackupException($this->shorten($e->getMessage()), previous: $e);
        } finally {
            if ($work) {
                File::deleteDirectory($work);
            }
            $lock->release();
        }
    }

    /* --------------------------------------------------------- Retention */

    /**
     * Delete archives that have served their purpose, and stamp the rows.
     *
     * Default rules, all of which must hold:
     *   - the run succeeded and the file is still on disk;
     *   - it is older than `retention_days`;
     *   - it has been downloaded (so a copy exists off this server);
     *   - it is not among the `keep_minimum` newest archives.
     *
     * $force drops only the "has been downloaded" requirement. The age and
     * keep_minimum rules hold in every case, which is what stops any sequence
     * of clicks or flags from emptying the server.
     *
     * @return array{deleted:int, freed_bytes:int, kept:int}
     */
    public function prune(bool $force = false, ?string $actorEmail = null): array
    {
        $available = $this->availableRuns();
        $protected = $available->take((int) config('backup.keep_minimum'))
            ->map(fn (BackupRun $r) => (string) $r->_id)
            ->all();

        $deleted = 0;
        $freed   = 0;
        $kept    = 0;

        foreach ($available as $run) {
            if (in_array((string) $run->_id, $protected, true)) {
                $kept++;
                continue;
            }
            if (! $run->isArchivable()) {
                $kept++;
                continue;
            }
            if (! $force && $run->downloaded_at === null) {
                $kept++;
                continue;
            }

            $size = (int) $run->size_bytes;
            if ($this->deleteFile($run, $actorEmail)) {
                $deleted++;
                $freed += $size;
            }
        }

        return ['deleted' => $deleted, 'freed_bytes' => $freed, 'kept' => $kept];
    }

    /** Remove one archive from disk, keeping its row. */
    public function deleteFile(BackupRun $run, ?string $actorEmail = null): bool
    {
        if (! $run->isAvailable()) {
            return false;
        }

        if ($this->isProtected($run)) {
            return false;
        }

        @unlink($run->path());

        $run->update([
            'deleted_at' => now(),
            'deleted_by' => $actorEmail,
        ]);

        return true;
    }

    /**
     * True when this run is one of the newest `keep_minimum` archives, which
     * are never deletable through any path.
     */
    public function isProtected(BackupRun $run): bool
    {
        return $this->availableRuns()
            ->take((int) config('backup.keep_minimum'))
            ->contains(fn (BackupRun $r) => (string) $r->_id === (string) $run->_id);
    }

    /** Stamp archives as downloaded, which is what makes them prunable. */
    public function markDownloaded(Collection $runs, ?string $actorEmail = null): void
    {
        foreach ($runs as $run) {
            $run->update([
                'downloaded_at' => now(),
                'downloaded_by' => $actorEmail,
            ]);
        }
    }

    /**
     * Bundle several archives into one zip for a single download. Returns the
     * temp path; the caller is responsible for sending it with
     * deleteFileAfterSend().
     */
    public function bundle(Collection $runs): string
    {
        if (! class_exists(\ZipArchive::class)) {
            throw new BackupException(__('The zip PHP extension is required to download several backups at once.'));
        }

        $path = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR
              . 'nautiqs-backups-' . now()->format('Ymd-His') . '.zip';

        $zip = new \ZipArchive();
        if ($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new BackupException(__('Could not create the download bundle.'));
        }

        $added = 0;
        foreach ($runs as $run) {
            if ($run->isAvailable()) {
                $zip->addFile($run->path(), $run->filename);
                $added++;
            }
        }
        $zip->close();

        if ($added === 0) {
            @unlink($path);
            throw new BackupException(__('None of those backups are still on the server.'));
        }

        return $path;
    }

    /* ------------------------------------------------------------ Queries */

    /** Successful runs whose file is still on disk, newest first. */
    public function availableRuns(): Collection
    {
        return BackupRun::where('status', BackupRun::STATUS_OK)
            ->whereNull('deleted_at')
            ->orderBy('started_at', 'desc')
            ->get()
            ->filter(fn (BackupRun $r) => $r->isAvailable())
            ->values();
    }

    /** Past retention, still on disk — what the superadmin is asked to take. */
    public function archivableRuns(): Collection
    {
        $protected = $this->availableRuns()
            ->take((int) config('backup.keep_minimum'))
            ->map(fn (BackupRun $r) => (string) $r->_id)
            ->all();

        return $this->availableRuns()
            ->filter(fn (BackupRun $r) => $r->isArchivable() && ! in_array((string) $r->_id, $protected, true))
            ->values();
    }

    /** Downloaded and past retention: safe to remove from the server. */
    public function prunableRuns(): Collection
    {
        return $this->archivableRuns()
            ->filter(fn (BackupRun $r) => $r->downloaded_at !== null)
            ->values();
    }

    /** @return array{last:?BackupRun, is_stale:bool, disk_bytes:int, free_bytes:int, count:int} */
    public function stats(): array
    {
        $last = BackupRun::whereIn('status', [BackupRun::STATUS_OK, BackupRun::STATUS_FAILED])
            ->orderBy('started_at', 'desc')
            ->first();

        $available = $this->availableRuns();

        $stale = true;
        if ($last && $last->status === BackupRun::STATUS_OK && $last->started_at) {
            $stale = $last->started_at->diffInHours(now()) > (int) config('backup.stale_after_hours');
        }

        return [
            'last'       => $last,
            'is_stale'   => $stale,
            'count'      => $available->count(),
            'disk_bytes' => (int) $available->sum('size_bytes'),
            'free_bytes' => (int) max(0, (int) @disk_free_space($this->directory())),
        ];
    }

    /* ------------------------------------------------------------ Internals */

    /**
     * Build the directory that becomes the archive. Returns the manifest of
     * what actually went in, which is recorded on the run so a restorer can
     * see whether an archive carries files and .env or only the database.
     *
     * @return array{database:bool, storage:bool, env:bool}
     */
    private function stage(string $work): array
    {
        $this->dumpDatabase($work . '/db.archive.gz');

        $contents = ['database' => true, 'storage' => false, 'env' => false];

        if (config('backup.include_storage')) {
            $public = storage_path('app/public');
            if (is_dir($public)) {
                File::ensureDirectoryExists($work . '/storage');
                File::copyDirectory($public, $work . '/storage');
                $contents['storage'] = true;
            }
        }

        if (config('backup.include_env') && is_file(base_path('.env'))) {
            File::copy(base_path('.env'), $work . '/.env');
            $contents['env'] = true;
        }

        File::put($work . '/manifest.json', json_encode([
            'created_at'  => now()->toIso8601String(),
            'database'    => config('database.connections.mongodb.database'),
            'app_version' => $this->appVersion(),
            'contents'    => $contents,
            'restore'     => 'mongorestore --uri="<target>" --gzip --archive=db.archive.gz --drop',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $contents;
    }

    /** Short commit of the deployed code, for the manifest. Best-effort. */
    private function appVersion(): ?string
    {
        $out = @exec('git -C ' . escapeshellarg(base_path()) . ' rev-parse --short HEAD 2>/dev/null');

        return trim((string) $out) ?: null;
    }

    /**
     * mongodump the whole database into a single gzipped archive file.
     *
     * The connection string is handed over in a 0600 config file rather than
     * on the command line, where it would sit in `ps` output and in any shell
     * history for every process on the box to read.
     */
    private function dumpDatabase(string $target): void
    {
        $uri = (string) config('database.connections.mongodb.dsn');
        $db  = (string) config('database.connections.mongodb.database');

        if ($uri === '') {
            throw new BackupException('MONGODB_URI is not set — nothing to dump.');
        }

        $confFile = $target . '.conf';
        File::put($confFile, "uri: " . $uri . "\n");
        @chmod($confFile, 0600);

        try {
            $result = Process::timeout((int) config('backup.timeout'))
                ->run([
                    (string) config('backup.mongodump'),
                    '--config=' . $confFile,
                    '--db=' . $db,
                    '--gzip',
                    '--archive=' . $target,
                ]);

            if (! $result->successful()) {
                $err = trim($result->errorOutput() ?: $result->output());

                if (str_contains($err, 'not found') || str_contains($err, 'No such file')) {
                    throw new BackupException(
                        'mongodump was not found. Install MongoDB Database Tools on the server, '
                        . 'or set BACKUP_MONGODUMP to its absolute path.'
                    );
                }

                throw new BackupException('mongodump failed: ' . $this->shorten($err));
            }
        } finally {
            @unlink($confFile);
        }

        if (! is_file($target) || filesize($target) === 0) {
            throw new BackupException('mongodump produced an empty archive.');
        }
    }

    private function compress(string $work, string $target): void
    {
        $result = Process::timeout((int) config('backup.timeout'))
            ->run([(string) config('backup.tar'), '-czf', $target, '-C', $work, '.']);

        if (! $result->successful()) {
            throw new BackupException('tar failed: ' . $this->shorten($result->errorOutput()));
        }
    }

    /**
     * Prove the archive is readable and holds the dump before the run is
     * called a success — an unverified backup is only a hope.
     */
    private function verify(string $target): void
    {
        if (! is_file($target) || filesize($target) === 0) {
            throw new BackupException('The archive is missing or empty.');
        }

        $result = Process::timeout((int) config('backup.timeout'))
            ->run([(string) config('backup.tar'), '-tzf', $target]);

        if (! $result->successful()) {
            throw new BackupException('The archive is unreadable: ' . $this->shorten($result->errorOutput()));
        }

        if (! str_contains($result->output(), 'db.archive.gz')) {
            throw new BackupException('The archive does not contain a database dump.');
        }
    }

    private function documentCount(): int
    {
        try {
            $stats = DB::connection('mongodb')->getMongoDB()->command(['dbStats' => 1])->toArray()[0];

            return (int) ($stats->objects ?? 0);
        } catch (Throwable) {
            return 0;      // a nice-to-have, never a reason to fail a backup
        }
    }

    private function assertDiskSpace(): void
    {
        $free = (int) @disk_free_space($this->directory());
        $need = (int) config('backup.min_free_disk_mb') * 1024 * 1024;

        if ($free > 0 && $free < $need) {
            throw new BackupException(sprintf(
                'Not enough disk space: %s free, %s required.',
                $this->humanBytes($free),
                $this->humanBytes($need),
            ));
        }
    }

    public function directory(): string
    {
        $dir = rtrim((string) config('backup.path'), '/\\');

        if (! is_dir($dir)) {
            File::ensureDirectoryExists($dir, 0700);
        }

        return $dir;
    }

    private function archivePath(string $filename): string
    {
        return $this->directory() . DIRECTORY_SEPARATOR . $filename;
    }

    private function makeWorkDir(): string
    {
        $work = $this->directory() . DIRECTORY_SEPARATOR . '.work-' . uniqid();
        File::ensureDirectoryExists($work, 0700);

        return $work;
    }

    private function notifyFailure(BackupRun $run, Throwable $e): void
    {
        Log::error('Backup failed', ['run' => (string) $run->_id, 'error' => $e->getMessage()]);

        $to = (string) config('backup.notify_email');
        if ($to === '') {
            return;
        }

        try {
            $body = "A Nautiqs backup failed.\n\n"
                  . 'When:    ' . $run->started_at?->toDateTimeString() . " UTC\n"
                  . 'Trigger: ' . $run->trigger . "\n"
                  . 'Error:   ' . $this->shorten($e->getMessage()) . "\n\n"
                  . "The server still holds the previous archives. Check the Backups page in the admin area.";

            Mail::raw($body, fn ($m) => $m->to($to)->subject('[Nautiqs] Backup failed'));
        } catch (Throwable $mailError) {
            // Never let a mail problem hide the backup problem.
            Log::error('Backup failure notification could not be sent', ['error' => $mailError->getMessage()]);
        }
    }

    private function shorten(string $message, int $limit = 500): string
    {
        $message = trim(preg_replace('/\s+/', ' ', $message) ?? '');

        return mb_strlen($message) > $limit ? mb_substr($message, 0, $limit) . '…' : $message;
    }

    public function humanBytes(int $bytes): string
    {
        return BackupRun::formatBytes($bytes);
    }
}
