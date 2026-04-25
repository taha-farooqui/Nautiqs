<?php

use App\Http\Controllers\ClientController;
use App\Http\Controllers\CompanySettingsController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QuoteController;
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
    Route::get('/quotes/{id}/order-confirmation', [QuoteController::class, 'orderConfirmation'])->name('quotes.order-confirmation');

    // Company settings — §17
    Route::get('/settings/company',    [CompanySettingsController::class, 'edit'])->name('company.settings');
    Route::patch('/settings/company',  [CompanySettingsController::class, 'update'])->name('company.settings.update');

    // Profile (Breeze default)
    Route::get('/profile',    [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile',  [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
