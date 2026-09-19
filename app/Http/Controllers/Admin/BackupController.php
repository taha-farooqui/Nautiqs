<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\BackupException;
use App\Http\Controllers\Controller;
use App\Models\BackupRun;
use App\Services\AuditLogger;
use App\Services\BackupService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Backups, superadmin only (the whole /admin group is behind the superadmin
 * middleware). Nothing here is tenant-scoped: an archive covers the entire
 * database, so it belongs to the platform rather than to any dealership.
 *
 * The page exists so the retention cycle can be completed by a human: the
 * server keeps the last 30 days, and older archives leave the server only
 * after they have been downloaded from here.
 */
class BackupController extends Controller
{
    public function __construct(private BackupService $backups)
    {
    }

    public function index()
    {
        // Resolved once here rather than per row: isProtected() walks the whole
        // set of available archives, so calling it from inside the table would
        // repeat that work for every line.
        $protectedIds = $this->backups->availableRuns()
            ->take((int) config('backup.keep_minimum'))
            ->map(fn (BackupRun $r) => (string) $r->_id)
            ->all();

        return view('admin.backups.index', [
            'runs'         => BackupRun::orderBy('started_at', 'desc')->paginate(30),
            'stats'        => $this->backups->stats(),
            'archivable'   => $this->backups->archivableRuns(),
            'prunable'     => $this->backups->prunableRuns(),
            'protectedIds' => $protectedIds,
            'retention'    => (int) config('backup.retention_days'),
            'keepMinimum'  => (int) config('backup.keep_minimum'),
            'service'      => $this->backups,
        ]);
    }

    /** Take a backup now. Synchronous — the database is small enough. */
    public function store()
    {
        @set_time_limit((int) config('backup.timeout') + 60);

        try {
            $run = $this->backups->run(BackupRun::TRIGGER_MANUAL, auth()->user()?->email);
        } catch (BackupException $e) {
            return back()->withErrors(['backup' => $e->getMessage()]);
        }

        AuditLogger::record('backup.create', $run, targetLabel: $run->filename);

        return back()->with('status', __('Backup created: :file (:size)', [
            'file' => $run->filename,
            'size' => $run->humanSize(),
        ]));
    }

    /** Download one archive. Serving it marks it as held off-server. */
    public function download(string $id): BinaryFileResponse
    {
        $run = BackupRun::findOrFail($id);

        abort_unless($run->isAvailable(), 404, __('That archive is no longer on the server.'));

        $this->backups->markDownloaded(collect([$run]), auth()->user()?->email);

        return response()->download($run->path(), $run->filename);
    }

    /**
     * Download every past-retention archive as one zip. This is the step that
     * moves the older month off the server; the archives become deletable
     * once it has run.
     */
    public function downloadArchivable(): BinaryFileResponse
    {
        $runs = $this->backups->archivableRuns();

        abort_if($runs->isEmpty(), 404, __('There is nothing to archive yet.'));

        @set_time_limit((int) config('backup.timeout') + 60);

        try {
            $path = $this->backups->bundle($runs);
        } catch (BackupException $e) {
            abort(500, $e->getMessage());
        }

        $this->backups->markDownloaded($runs, auth()->user()?->email);

        AuditLogger::record(
            'backup.download_bundle',
            targetLabel: $runs->count() . ' ' . __('archive(s)'),
        );

        return response()->download($path, basename($path))->deleteFileAfterSend(true);
    }

    /** Delete one archive's file, keeping its row as the record it existed. */
    public function destroy(string $id)
    {
        $run = BackupRun::findOrFail($id);

        if ($this->backups->isProtected($run)) {
            return back()->withErrors(['backup' => __('That is one of the most recent backups and cannot be deleted.')]);
        }

        if (! $this->backups->deleteFile($run, auth()->user()?->email)) {
            return back()->withErrors(['backup' => __('That archive is no longer on the server.')]);
        }

        AuditLogger::record('backup.delete', $run, targetLabel: $run->filename);

        return back()->with('status', __('Backup deleted: :file', ['file' => $run->filename]));
    }

    /**
     * Free the space taken by archives that are past retention and have
     * already been downloaded. Never touches anything that has not left the
     * server, nor the newest few archives.
     */
    public function pruneDownloaded()
    {
        $result = $this->backups->prune(force: false, actorEmail: auth()->user()?->email);

        if ($result['deleted'] === 0) {
            return back()->withErrors(['backup' => __('Nothing to free up. Download the older archives first.')]);
        }

        AuditLogger::record(
            'backup.prune',
            targetLabel: $result['deleted'] . ' ' . __('archive(s)'),
            after: $result,
        );

        return back()->with('status', __(':count archive(s) removed from the server, :size freed.', [
            'count' => $result['deleted'],
            'size'  => $this->backups->humanBytes($result['freed_bytes']),
        ]));
    }
}
