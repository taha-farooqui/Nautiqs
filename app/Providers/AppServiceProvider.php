<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Force HTTPS on URL generation in production. Behind Railway's edge
        // proxy the app sees plain HTTP requests, but cookies, password-reset
        // links, and OAuth callbacks must use https:// to match the public URL.
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
