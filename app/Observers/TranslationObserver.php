<?php

namespace App\Observers;

use App\Models\Translation;
use App\Services\DbOverlayTranslator;

/**
 * Bust the DbOverlayTranslator cache whenever a translation row is created,
 * updated, or deleted so the next request sees the change immediately.
 */
class TranslationObserver
{
    public function saved(Translation $translation): void
    {
        DbOverlayTranslator::forget($translation->locale);
    }

    public function deleted(Translation $translation): void
    {
        DbOverlayTranslator::forget($translation->locale);
    }
}
