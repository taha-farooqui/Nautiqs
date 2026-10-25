<?php

namespace App\Models\Concerns;

use App\Models\Scopes\BrandScope;

/**
 * The brand-side twin of BelongsToTenant: a brand manager only ever reads and
 * writes their own account's records. Code acting for a dealer (BrandSync
 * copying a catalogue into it) reads these with withoutGlobalScopes() and an
 * explicit brand_account_id.
 */
trait BelongsToBrand
{
    public static function bootBelongsToBrand(): void
    {
        static::addGlobalScope(new BrandScope);

        static::creating(function ($model) {
            if (empty($model->brand_account_id) && auth()->check() && auth()->user()->brand_account_id) {
                $model->brand_account_id = (string) auth()->user()->brand_account_id;
            }
        });
    }
}
