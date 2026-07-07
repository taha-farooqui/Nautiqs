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
