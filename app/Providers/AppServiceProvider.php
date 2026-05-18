<?php

namespace App\Providers;

use App\Models\Client;
use App\Models\EmailTemplate;
use App\Models\Quote;
use App\Models\Translation;
use App\Observers\ClientObserver;
use App\Observers\EmailTemplateObserver;
use App\Observers\QuoteObserver;
use App\Observers\TranslationObserver;
use App\Services\DbOverlayTranslator;
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
        // Replace Laravel's default Translator with the DB-overlay variant
        // so __() can be customised at runtime via the superadmin Dictionary
        // page. Uses extend() (not bind/singleton) so we keep the framework's
        // wiring of file loader + fallback locale and only swap the class.
        $this->app->extend('translator', function ($translator, $app) {
            $overlay = new DbOverlayTranslator($translator->getLoader(), $app->getLocale());
            $overlay->setFallback($translator->getFallback());
            return $overlay;
        });
    }

    public function boot(): void
    {
        // Force HTTPS on URL generation in production. Behind Railway's edge
        // proxy the app sees plain HTTP requests, but cookies, password-reset
        // links, and OAuth callbacks must use https:// to match the public URL.
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        // Let the superadmin override the email-tracking base URL at runtime
        // from /admin/settings. Falls back to the EMAIL_TRACKING_BASE_URL env
        // variable when blank. Wrapped in a try/catch so a missing Mongo
        // connection during artisan commands (migrations, route:cache) won't
        // explode boot.
        try {
            $override = \App\Models\PlatformSetting::singleton()->email_tracking_base_url;
            if (! empty($override)) {
                config(['app.tracking_base_url' => $override]);
            }
        } catch (\Throwable $e) {
            // Database not reachable yet (CI, fresh install) — skip override.
        }

        // Wire up CRUD observers so notifications fire automatically.
        Quote::observe(QuoteObserver::class);
        Client::observe(ClientObserver::class);
        EmailTemplate::observe(EmailTemplateObserver::class);
        Translation::observe(TranslationObserver::class);

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
