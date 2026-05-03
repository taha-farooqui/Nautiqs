<?php

use App\Http\Controllers\CatalogueController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\CompanySettingsController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmailLogController;
use App\Http\Controllers\EmailTemplateController;
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

Route::middleware(['auth', 'verified'])->group(function () {

    // Dashboard — §16.3
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // Clients — §16.1
    Route::resource('clients', ClientController::class);

    // Quotes — §11, §16.2
    Route::get('/quotes',                   [QuoteController::class, 'index'])->name('quotes.index');
    Route::get('/quotes/create',            [QuoteController::class, 'create'])->name('quotes.create');
    Route::get('/quotes/{id}',              [QuoteController::class, 'show'])->name('quotes.show');
    Route::get('/quotes/{id}/edit',         [QuoteController::class, 'edit'])->name('quotes.edit');
    Route::delete('/quotes/{id}',           [QuoteController::class, 'destroy'])->name('quotes.destroy');
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

    // Profile (Breeze default)
    Route::get('/profile',    [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile',  [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
