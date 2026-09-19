<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Automatic quote follow-ups ("Relances"). Daily is enough — the delay
// granularity is days/weeks/months. Needs the OS cron running
// `php artisan schedule:run` every minute on the server.
// (Single VPS today; add ->onOneServer() if this ever runs on several.)
Schedule::command('quotes:send-follow-ups')
    ->dailyAt('09:00')
    ->timezone('Europe/Paris')
    ->withoutOverlapping(60)
    ->appendOutputTo(storage_path('logs/follow-ups.log'));

/*
 * Backups — twice daily, at midnight and half past twelve, Paris time.
 *
 * Two separate entries rather than twiceDailyAt(), which forces the same
 * minute past the hour on both runs. The timezone is explicit so the times
 * hold across DST rather than drifting by an hour twice a year.
 *
 * withoutOverlapping stops the 12:30 run starting on top of a 00:00 run that
 * is somehow still going; BackupService takes its own lock as well, so a
 * manual backup from the admin page can't collide with either.
 */
Schedule::command('backup:run --trigger=scheduled')
    ->dailyAt('00:00')
    ->timezone('Europe/Paris')
    ->withoutOverlapping(120)
    ->appendOutputTo(storage_path('logs/backups.log'));

Schedule::command('backup:run --trigger=scheduled')
    ->dailyAt('12:30')
    ->timezone('Europe/Paris')
    ->withoutOverlapping(120)
    ->appendOutputTo(storage_path('logs/backups.log'));

/*
 * Retention. Deletes nothing until the superadmin has downloaded the
 * past-retention archives from the Backups page — see BackupService::prune().
 */
Schedule::command('backup:prune')
    ->dailyAt('03:00')
    ->timezone('Europe/Paris')
    ->appendOutputTo(storage_path('logs/backups.log'));
