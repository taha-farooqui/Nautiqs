<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Where archives live
    |--------------------------------------------------------------------------
    |
    | Outside the web root and never served by nginx — the only way to fetch an
    | archive is the authenticated superadmin download route. The directory is
    | created with 0700 on first use.
    |
    */
    'path' => env('BACKUP_PATH', storage_path('app/backups')),

    /*
    |--------------------------------------------------------------------------
    | Retention
    |--------------------------------------------------------------------------
    |
    | 'retention_days'  Archives younger than this are "current" and are never
    |                   offered for removal. Older ones become archivable: the
    |                   superadmin downloads them to their own machine and then
    |                   frees the server space.
    |
    | 'keep_minimum'    A floor that outranks every other rule, including the
    |                   force flag. However the pruning is triggered, this many
    |                   of the newest successful archives always stay on disk,
    |                   so no sequence of clicks can leave the server with none.
    |
    */
    'retention_days' => (int) env('BACKUP_RETENTION_DAYS', 30),
    'keep_minimum'   => (int) env('BACKUP_KEEP_MINIMUM', 4),

    /*
    | Days between an archive being downloaded and the scheduled prune being
    | willing to delete it.
    |
    | Serving a file is not proof it arrived: a download can be cancelled or
    | truncated, and the archive is marked downloaded either way. Without a
    | grace period the next nightly prune would remove the server's copy of a
    | file the operator never actually got. The button on the Backups page is
    | an explicit human decision and ignores this.
    */
    'prune_grace_days' => (int) env('BACKUP_PRUNE_GRACE_DAYS', 2),

    /*
    |--------------------------------------------------------------------------
    | What goes in
    |--------------------------------------------------------------------------
    |
    | The database dump is always included. The two flags below add:
    |
    | 'include_storage'  storage/app/public — the dealership logos printed on
    |                    every quote PDF. Small, and a restore without them
    |                    silently degrades every document.
    |
    | 'include_env'      .env, which carries APP_KEY. Once client data is
    |                    encrypted at rest (plan.md §2) a database restored
    |                    without that key is unreadable, so the archive is only
    |                    a real disaster-recovery artefact with it. It also
    |                    means an archive carries live credentials: the file is
    |                    private on disk and must stay private off it.
    |
    */
    'include_storage' => (bool) env('BACKUP_INCLUDE_STORAGE', true),
    'include_env'     => (bool) env('BACKUP_INCLUDE_ENV', true),

    /*
    |--------------------------------------------------------------------------
    | Tools
    |--------------------------------------------------------------------------
    |
    | mongodump ships in MongoDB Database Tools, which is a separate package
    | from the server and from the PHP driver. Set the absolute path here if it
    | is not on PATH for the web user.
    |
    */
    'mongodump' => env('BACKUP_MONGODUMP', 'mongodump'),
    'tar'       => env('BACKUP_TAR', 'tar'),

    /*
    | Seconds any single dump may take before it is killed. The whole database
    | is a few megabytes today; this is a guard against a hung process holding
    | the lock, not a real expectation.
    */
    'timeout' => (int) env('BACKUP_TIMEOUT', 600),

    /*
    | Refuse to start a backup unless at least this much disk is free, so a
    | full disk fails fast and loudly instead of writing a truncated archive.
    */
    'min_free_disk_mb' => (int) env('BACKUP_MIN_FREE_DISK_MB', 512),

    /*
    |--------------------------------------------------------------------------
    | Failure notification
    |--------------------------------------------------------------------------
    |
    | A scheduled backup that fails is silent by nature — nobody is watching at
    | 00:00. Leave the address blank to disable the email.
    |
    */
    'notify_email' => env('BACKUP_NOTIFY_EMAIL', env('MAIL_FROM_ADDRESS')),

    /*
    | The admin page flags the last backup as stale after this many hours. With
    | runs at 00:00 and 12:30 the longest healthy gap is 12.5h, so 26 hours
    | means roughly two consecutive runs were missed before we complain.
    */
    'stale_after_hours' => (int) env('BACKUP_STALE_AFTER_HOURS', 26),

    /*
    |--------------------------------------------------------------------------
    | Schedule timezone
    |--------------------------------------------------------------------------
    |
    | The times in routes/console.php are read in this zone, and the Backups
    | page prints every run in it too. One value for both, because the server
    | runs on UTC: with the schedule in Paris time and the page in UTC, a
    | backup taken at midnight in Nice was listed as 22:00 the previous day,
    | and the only sensible conclusion to draw from that was that it had run
    | at the wrong time.
    |
    */
    'timezone' => env('BACKUP_TIMEZONE', 'Europe/Paris'),

];
