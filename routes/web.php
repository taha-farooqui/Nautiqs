<?php

use App\Http\Controllers\CatalogueController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\CompanySettingsController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmailLogController;
use App\Http\Controllers\EmailTemplateController;
use App\Http\Controllers\EngineController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

// Email open-tracking pixel. Public on purpose — the only credential is
// the random per-quote token in the URL. Always returns a 1x1 GIF.
Route::get('/e/p/{token}', \App\Http\Controllers\EmailPixelController::class)
    ->name('email.pixel');

// UI locale switcher — drops a 1-year `locale` cookie and bounces the user
// back where they came from. Reachable while logged in or out so the login
// screen language toggle works.
Route::get('/locale/{lang}', function (string $lang) {
    $supported = ['en', 'fr'];
    if (! in_array($lang, $supported, true)) {
        $lang = 'fr';
    }
    return back()->cookie(cookie('locale', $lang, 60 * 24 * 365));
})->name('locale.switch');

/*
 * One-shot mail diagnostic gated by DIAG_TOKEN env var. Designed for hosts
 * (Railway etc.) that don't expose an SSH/shell. Hit:
 *
 *   GET /_diag/mail?token=YOUR_TOKEN&to=you@email.com
 *
 * Returns JSON with: the active mail config, whether the test send raised
 * an exception, and the exception message if any. The token guard means
 * leaving the route enabled is OK as long as DIAG_TOKEN stays secret. To
 * disable entirely, unset DIAG_TOKEN in production.
 */
Route::get('/_diag/mail', function (\Illuminate\Http\Request $request) {
    $expected = env('DIAG_TOKEN');
    if (! $expected || ! hash_equals($expected, (string) $request->query('token', ''))) {
        abort(404);
    }

    $to = $request->query('to') ?: config('mail.from.address');

    $config = [
        'MAIL_MAILER'       => config('mail.default'),
        'MAIL_HOST'         => config('mail.mailers.smtp.host'),
        'MAIL_PORT'         => config('mail.mailers.smtp.port'),
        'MAIL_USERNAME'     => config('mail.mailers.smtp.username'),
        'MAIL_PASSWORD_SET' => (bool) config('mail.mailers.smtp.password'),
        'MAIL_TIMEOUT_S'    => config('mail.mailers.smtp.timeout'),
        'MAIL_FROM_ADDRESS' => config('mail.from.address'),
        'MAIL_FROM_NAME'    => config('mail.from.name'),
        'APP_ENV'           => config('app.env'),
        'APP_URL'           => config('app.url'),
    ];

    $start = microtime(true);
    try {
        \Illuminate\Support\Facades\Mail::raw(
            'Diagnostic ping from Nautiqs at ' . now()->toDateTimeString(),
            fn ($m) => $m->to($to)->subject('Nautiqs · /_diag/mail')
        );
        return response()->json([
            'ok'         => true,
            'sent_to'    => $to,
            'duration_ms' => round((microtime(true) - $start) * 1000),
            'config'     => $config,
            'next_step'  => 'Symfony Mailer accepted the message. Check Brevo → Transactional → Logs to confirm it arrived at Brevo.',
        ]);
    } catch (\Throwable $e) {
        return response()->json([
            'ok'           => false,
            'duration_ms'  => round((microtime(true) - $start) * 1000),
            'config'       => $config,
            'error_class'  => get_class($e),
            'error_message'=> $e->getMessage(),
        ], 500);
    }
})->name('diag.mail');

Route::middleware(['auth', 'verified', 'maintenance'])->group(function () {

    // Dashboard — §16.3
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // Clients — §16.1
    Route::resource('clients', ClientController::class);

    // Quotes — §11, §16.2
    Route::get('/quotes',                   [QuoteController::class, 'index'])->name('quotes.index');
    Route::get('/quotes/create',            [QuoteController::class, 'create'])->name('quotes.create');
    Route::get('/quotes/trash',             [QuoteController::class, 'trash'])->name('quotes.trash');
    Route::delete('/quotes/trash/empty',    [QuoteController::class, 'emptyTrash'])->name('quotes.empty-trash');
    Route::get('/quotes/{id}',              [QuoteController::class, 'show'])->name('quotes.show');
    Route::get('/quotes/{id}/edit',         [QuoteController::class, 'edit'])->name('quotes.edit');
    Route::delete('/quotes/{id}',           [QuoteController::class, 'destroy'])->name('quotes.destroy');
    Route::post('/quotes/{id}/restore',     [QuoteController::class, 'restore'])->name('quotes.restore');
    Route::delete('/quotes/{id}/force',     [QuoteController::class, 'forceDelete'])->name('quotes.force-delete');
    Route::post('/quotes/{id}/mark-sent',   [QuoteController::class, 'markSent'])->name('quotes.mark-sent');
    Route::post('/quotes/{id}/mark-won',    [QuoteController::class, 'markWon'])->name('quotes.mark-won');
    Route::post('/quotes/{id}/mark-lost',   [QuoteController::class, 'markLost'])->name('quotes.mark-lost');
    Route::post('/quotes/{id}/duplicate',   [QuoteController::class, 'duplicate'])->name('quotes.duplicate');
    Route::get('/quotes/{id}/pdf',          [QuoteController::class, 'pdf'])->name('quotes.pdf');
    Route::post('/quotes/{id}/send-email',  [QuoteController::class, 'sendEmail'])->name('quotes.send-email');
    Route::get('/quotes/{id}/order-confirmation', [QuoteController::class, 'orderConfirmation'])->name('quotes.order-confirmation');

    // Company settings — §17
    Route::get('/settings/company',    [CompanySettingsController::class, 'edit'])->name('company.settings');
    Route::patch('/settings/company',  [CompanySettingsController::class, 'update'])->name('company.settings.update');

    // Team — multi-user sub-accounts. Admin-gated; the role middleware
    // returns 403 to salespeople who try to reach these.
    Route::middleware('role:tenant_admin')->group(function () {
        Route::get('/settings/team',                        [\App\Http\Controllers\TeamController::class, 'index'])->name('team.index');
        Route::post('/settings/team/invite',                [\App\Http\Controllers\TeamController::class, 'invite'])->name('team.invite');
        Route::post('/settings/team/invite/{id}/resend',    [\App\Http\Controllers\TeamController::class, 'resend'])->name('team.invite.resend');
        Route::delete('/settings/team/invite/{id}',         [\App\Http\Controllers\TeamController::class, 'revoke'])->name('team.invite.revoke');
        Route::post('/settings/team/{userId}/deactivate',   [\App\Http\Controllers\TeamController::class, 'deactivate'])->name('team.deactivate');
        Route::post('/settings/team/{userId}/activate',     [\App\Http\Controllers\TeamController::class, 'activate'])->name('team.activate');
        Route::patch('/settings/team/{userId}/role',        [\App\Http\Controllers\TeamController::class, 'updateRole'])->name('team.role');
    });

    // Notifications + global search
    Route::get('/notifications',            [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/notifications/read-all',  [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::get('/search',                   SearchController::class)->name('search');

    // Catalogue (§6 + §7) — dealer's workspace catalogue
    Route::prefix('catalogue')->name('catalogue.')->group(function () {
        // Browse / list
        Route::get('/models',   [CatalogueController::class, 'models'])->name('models');
        Route::get('/brands',   [CatalogueController::class, 'brands'])->name('brands');
        Route::get('/updates',  [CatalogueController::class, 'updates'])->name('updates');

        // Per-boat options bulk import (button lives inside the Options tab
        // of the boat editor). The boat is implied by {modelId} so the file
        // omits the CODE MODELE column entirely — the dealer doesn't need to
        // remember or type the boat's internal code.
        Route::get('/models/{modelId}/options/template',   [CatalogueController::class, 'optionsTemplateForBoat'])->name('options.template-for-boat');
        Route::post('/models/{modelId}/options/import-file', [CatalogueController::class, 'importOptionsForBoat'])->name('options.import-for-boat');

        // Brand activation
        Route::post('/brands/activate/{globalBrandId}',          [CatalogueController::class, 'activateBrand'])->name('brands.activate');
        Route::post('/brands/{companyBrandId}/deactivate',       [CatalogueController::class, 'deactivateBrand'])->name('brands.deactivate');
        Route::post('/brands/{companyBrandId}/reactivate',       [CatalogueController::class, 'reactivateBrand'])->name('brands.reactivate');
        Route::post('/brands/private',                           [CatalogueController::class, 'storePrivateBrand'])->name('brands.private.store');
        Route::delete('/brands/{companyBrandId}/private',        [CatalogueController::class, 'destroyPrivateBrand'])->name('brands.private.destroy');

        // Variant cherry-pick activation
        Route::post('/variants/activate/{globalVariantId}',      [CatalogueController::class, 'activateVariant'])->name('variants.activate');
        Route::post('/variants/activate-bulk',                   [CatalogueController::class, 'activateVariantsBulk'])->name('variants.activate-bulk');
        Route::post('/variants/{companyVariantId}/toggle',       [CatalogueController::class, 'toggleVariant'])->name('variants.toggle');

        // Private model CRUD
        Route::get('/models/create',                             [CatalogueController::class, 'createModel'])->name('models.create');
        Route::post('/models',                                   [CatalogueController::class, 'storeModel'])->name('models.store');
        Route::get('/models/{modelId}/edit',                     [CatalogueController::class, 'editModel'])->name('models.edit');
        Route::patch('/models/{modelId}',                        [CatalogueController::class, 'updateModel'])->name('models.update');
        Route::delete('/models/{modelId}',                       [CatalogueController::class, 'destroyModel'])->name('models.destroy');

        // Variant CRUD on a model
        Route::get('/variants/create',                           [CatalogueController::class, 'createVariant'])->name('variants.create');
        Route::post('/variants',                                 [CatalogueController::class, 'storeVariantStandalone'])->name('variants.store-standalone');
        Route::post('/models/{modelId}/variants',                [CatalogueController::class, 'storeVariant'])->name('variants.store');
        Route::patch('/variants/{variantId}',                    [CatalogueController::class, 'updateVariant'])->name('variants.update');
        Route::delete('/variants/{variantId}',                   [CatalogueController::class, 'destroyVariant'])->name('variants.destroy');

        // Option CRUD on a model
        Route::post('/models/{modelId}/options',                 [CatalogueController::class, 'storeOption'])->name('options.store');
        Route::post('/models/{modelId}/options/import',          [CatalogueController::class, 'importGlobalOptions'])->name('options.import');
        Route::post('/models/{modelId}/options/reorder',         [CatalogueController::class, 'reorderOptions'])->name('options.reorder');
        Route::patch('/options/{optionId}',                      [CatalogueController::class, 'updateOption'])->name('options.update');
        Route::delete('/options/{optionId}',                     [CatalogueController::class, 'destroyOption'])->name('options.destroy');
    });

    // Email templates — three per company: quote, order confirmation, follow-up (§14)
    Route::get('/settings/email-templates',                  [EmailTemplateController::class, 'index'])->name('email-templates.index');
    Route::get('/settings/email-templates/{type}/edit',      [EmailTemplateController::class, 'edit'])->name('email-templates.edit');
    Route::patch('/settings/email-templates/{type}',         [EmailTemplateController::class, 'update'])->name('email-templates.update');
    Route::post('/settings/email-templates/{type}/reset',    [EmailTemplateController::class, 'reset'])->name('email-templates.reset');

    // Email log — append-only audit of every outbound email (§3 EMAIL_LOG)
    Route::get('/email-log',           [EmailLogController::class, 'index'])->name('email-log.index');
    Route::get('/email-log/{id}',      [EmailLogController::class, 'show'])->name('email-log.show');

    // Engines — per-company library of motor SKUs (Suzuki DF200, Yamaha
    // F300, etc.) referenced from the catalogue and the quote builder.
    // Bulk-import routes declared BEFORE the resource so they win
    // against any future {engine} route binding on the same prefix.
    Route::get('/engines/template', [EngineController::class, 'template'])->name('engines.template');
    Route::post('/engines/import',  [EngineController::class, 'import'])->name('engines.import');
    Route::resource('engines', EngineController::class)->except(['show']);

    // Inline brand picker for the catalogue form — used by the autocomplete
    // dropdown when adding a new model. Returns active CompanyBrand rows.
    Route::get('/catalogue/brands/lookup', [CatalogueController::class, 'brandLookup'])->name('catalogue.brands.lookup');
    Route::post('/catalogue/brands/inline', [CatalogueController::class, 'storeInlineBrand'])->name('catalogue.brands.inline');

    // Profile (Breeze default)
    Route::get('/profile',    [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile',  [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    /* -----------------------------------------------------------------
     | Superadmin platform area — spec §4. Gated by RequireSuperadmin
     | which returns 404 (not 403) on auth failure so the panel's
     | existence isn't leaked.
     ----------------------------------------------------------------- */
    Route::prefix('admin')->name('admin.')->middleware('superadmin')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\DashboardController::class, 'index'])->name('dashboard');

        // Dealers (tenants) — list, detail, suspend, reactivate.
        Route::get('/dealers',                       [\App\Http\Controllers\Admin\DealerController::class, 'index'])->name('dealers.index');
        Route::get('/dealers/{id}',                  [\App\Http\Controllers\Admin\DealerController::class, 'show'])->name('dealers.show');
        Route::post('/dealers/{id}/suspend',         [\App\Http\Controllers\Admin\DealerController::class, 'suspend'])->name('dealers.suspend');
        Route::post('/dealers/{id}/reactivate',      [\App\Http\Controllers\Admin\DealerController::class, 'reactivate'])->name('dealers.reactivate');

        // Activity log (audit trail of superadmin actions).
        Route::get('/audit',                [\App\Http\Controllers\Admin\AuditController::class, 'index'])->name('audit.index');

        // Global catalogue CRUD — spec §4.1. All CRUD pages live on
        // Admin\CatalogueController so the audit-log call sites stay consistent.
        $c = \App\Http\Controllers\Admin\CatalogueController::class;
        Route::get('/brands',              [$c, 'brandsIndex'])->name('brands.index');
        Route::get('/brands/create',       [$c, 'brandsCreate'])->name('brands.create');
        Route::post('/brands',             [$c, 'brandsStore'])->name('brands.store');
        Route::get('/brands/{id}/edit',    [$c, 'brandsEdit'])->name('brands.edit');
        Route::patch('/brands/{id}',       [$c, 'brandsUpdate'])->name('brands.update');
        Route::post('/brands/{id}/toggle', [$c, 'brandsToggle'])->name('brands.toggle');

        Route::get('/models',              [$c, 'modelsIndex'])->name('models.index');
        Route::get('/models/create',       [$c, 'modelsCreate'])->name('models.create');
        Route::post('/models',             [$c, 'modelsStore'])->name('models.store');
        Route::get('/models/{id}/edit',    [$c, 'modelsEdit'])->name('models.edit');
        Route::patch('/models/{id}',      [$c, 'modelsUpdate'])->name('models.update');
        Route::post('/models/{id}/archive',[$c, 'modelsArchive'])->name('models.archive');

        Route::get('/variants',            [$c, 'variantsIndex'])->name('variants.index');
        Route::get('/variants/create',     [$c, 'variantsCreate'])->name('variants.create');
        Route::post('/variants',           [$c, 'variantsStore'])->name('variants.store');
        Route::get('/variants/{id}/edit',  [$c, 'variantsEdit'])->name('variants.edit');
        Route::patch('/variants/{id}',     [$c, 'variantsUpdate'])->name('variants.update');
        Route::post('/variants/{id}/archive', [$c, 'variantsArchive'])->name('variants.archive');

        Route::get('/equipment',           [$c, 'equipmentIndex'])->name('equipment.index');
        Route::get('/equipment/create',    [$c, 'equipmentCreate'])->name('equipment.create');
        Route::post('/equipment',          [$c, 'equipmentStore'])->name('equipment.store');
        Route::get('/equipment/{id}/edit', [$c, 'equipmentEdit'])->name('equipment.edit');
        Route::patch('/equipment/{id}',    [$c, 'equipmentUpdate'])->name('equipment.update');
        Route::delete('/equipment/{id}',   [$c, 'equipmentDestroy'])->name('equipment.destroy');

        Route::get('/options',             [$c, 'optionsIndex'])->name('options.index');
        Route::get('/options/create',      [$c, 'optionsCreate'])->name('options.create');
        Route::post('/options',            [$c, 'optionsStore'])->name('options.store');
        Route::get('/options/{id}/edit',   [$c, 'optionsEdit'])->name('options.edit');
        Route::patch('/options/{id}',      [$c, 'optionsUpdate'])->name('options.update');
        Route::post('/options/{id}/archive', [$c, 'optionsArchive'])->name('options.archive');

        Route::get('/engines',             [$c, 'enginesIndex'])->name('engines.index');
        Route::get('/engines/create',      [$c, 'enginesCreate'])->name('engines.create');
        Route::post('/engines',            [$c, 'enginesStore'])->name('engines.store');
        Route::get('/engines/{id}/edit',   [$c, 'enginesEdit'])->name('engines.edit');
        Route::patch('/engines/{id}',      [$c, 'enginesUpdate'])->name('engines.update');
        Route::post('/engines/{id}/archive', [$c, 'enginesArchive'])->name('engines.archive');

        // Platform settings — platform name + logo.
        Route::get('/settings',   [\App\Http\Controllers\Admin\SettingsController::class, 'edit'])->name('settings.edit');
        Route::patch('/settings', [\App\Http\Controllers\Admin\SettingsController::class, 'update'])->name('settings.update');

        // Translation dictionary — search any user-facing string in the
        // app and customise it. Falls back to lang/{locale}.json defaults.
        Route::get('/dictionary',           [\App\Http\Controllers\Admin\DictionaryController::class, 'index'])->name('dictionary.index');
        Route::get('/dictionary/export',    [\App\Http\Controllers\Admin\DictionaryController::class, 'export'])->name('dictionary.export');
        Route::post('/dictionary/update',   [\App\Http\Controllers\Admin\DictionaryController::class, 'update'])->name('dictionary.update');
        Route::post('/dictionary/reset',    [\App\Http\Controllers\Admin\DictionaryController::class, 'reset'])->name('dictionary.reset');
    });
});

require __DIR__.'/auth.php';
