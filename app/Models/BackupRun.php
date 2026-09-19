<?php

namespace App\Models;

use Carbon\CarbonInterface;
use MongoDB\Laravel\Eloquent\Model;

/**
 * One row per backup attempt, successful or not.
 *
 * Platform-scoped: deliberately NOT BelongsToTenant. A backup covers the whole
 * database, so it belongs to no single dealership and is only ever visible
 * inside /admin.
 *
 * The record outlives the file it describes. When the superadmin frees space,
 * the archive is deleted from disk and `deleted_at` is stamped — the row stays
 * as the evidence that a backup was taken that day and where it went.
 */
class BackupRun extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'backup_runs';

    public const STATUS_RUNNING = 'running';
    public const STATUS_OK      = 'ok';
    public const STATUS_FAILED  = 'failed';

    public const TRIGGER_MANUAL    = 'manual';
    public const TRIGGER_SCHEDULED = 'scheduled';

    protected $fillable = [
        'filename',        // basename only; the directory comes from config
        'status',          // running | ok | failed
        'trigger',         // manual | scheduled
        'size_bytes',
        'checksum',        // sha256 of the archive, recorded at creation
        'contents',        // ['database' => true, 'storage' => true, 'env' => true]
        'document_count',  // documents in the database at dump time
        'duration_ms',
        'error',           // null unless status = failed
        'started_by',      // email of the superadmin who pressed the button; null when scheduled
        'started_at',
        'finished_at',
        'downloaded_at',   // set the first time the archive is served
        'downloaded_by',
        'deleted_at',      // set when the file is removed from the server
        'deleted_by',
    ];

    protected $casts = [
        'size_bytes'     => 'integer',
        'document_count' => 'integer',
        'duration_ms'    => 'integer',
        'started_at'     => 'datetime',
        'finished_at'    => 'datetime',
        'downloaded_at'  => 'datetime',
        'deleted_at'     => 'datetime',
    ];

    /* ------------------------------------------------------------------ */

    /** Absolute path of the archive. The file may no longer be there. */
    public function path(): string
    {
        return rtrim((string) config('backup.path'), '/\\') . DIRECTORY_SEPARATOR . $this->filename;
    }

    /** A usable archive: the run succeeded and the file is still on disk. */
    public function isAvailable(): bool
    {
        return $this->status === self::STATUS_OK
            && $this->deleted_at === null
            && $this->filename
            && is_file($this->path());
    }

    /**
     * Past the retention window, so it has served its purpose as a recent
     * restore point and is a candidate for moving off the server.
     */
    public function isArchivable(): bool
    {
        if (! $this->isAvailable()) {
            return false;
        }

        return $this->ageInDays() >= (int) config('backup.retention_days');
    }

    public function ageInDays(): int
    {
        $from = $this->started_at ?? $this->created_at;

        return $from instanceof CarbonInterface ? (int) $from->diffInDays(now()) : 0;
    }

    public function humanSize(): string
    {
        $bytes = (int) $this->size_bytes;
        if ($bytes <= 0) {
            return '—';
        }
        foreach (['B', 'KB', 'MB', 'GB'] as $i => $unit) {
            if ($bytes < 1024 || $unit === 'GB') {
                return round($bytes / (1024 ** $i), $i === 0 ? 0 : 1) . ' ' . $unit;
            }
        }

        return $bytes . ' B';
    }
}
