<?php

namespace App\Providers;

use App\Models\Client;
use App\Models\EmailTemplate;
use App\Models\Quote;
use App\Observers\ClientObserver;
use App\Observers\EmailTemplateObserver;
use App\Observers\QuoteObserver;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Mailer\Bridge\Brevo\Transport\BrevoTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;

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

        // Wire up CRUD observers so notifications fire automatically.
        Quote::observe(QuoteObserver::class);
        Client::observe(ClientObserver::class);
        EmailTemplate::observe(EmailTemplateObserver::class);

        // Register the Brevo HTTPS API transport so MAIL_MAILER=brevo works.
        // Required on hosts that block outbound SMTP (Railway, Fly, etc.) —
        // the API runs on 443 which never gets blocked. BREVO_KEY env var
        // is the v3 API key from Brevo → SMTP & API → API keys.
        Mail::extend('brevo', function (array $config = []) {
            $key = $config['key'] ?? env('BREVO_KEY');
            return (new BrevoTransportFactory)->create(new Dsn(
                scheme: 'brevo+api',
                host:   'default',
                user:   $key,
            ));
        });

        // Share recent notifications + unread count with the header AND
        // sidebar on every authenticated request — saves wiring this into
        // every controller.
        View::composer(['components.app.header', 'components.app.sidebar'], function ($view) {
            $user = auth()->user();
            if (! $user) {
                $view->with(['notifications' => collect(), 'unreadNotificationsCount' => 0]);
                return;
            }
            $svc = app(NotificationService::class);
            $userId = (string) $user->_id;
            $view->with([
                'notifications'           => $svc->recentForUser($userId, 10),
                'unreadNotificationsCount' => $svc->unreadCount($userId),
            ]);
        });
    }
}
