<?php

namespace App\Models\Concerns;

use App\Models\Scopes\TenantScope;

/**
 * Enforces spec §18.3: strict company_id scoping at the query level.
 * Every tenant-scoped model uses this trait so no cross-tenant data
 * access is ever possible, even by accident.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($model) {
            if (empty($model->company_id) && auth()->check() && auth()->user()->company_id) {
                $model->company_id = auth()->user()->company_id;
            }
        });
    }
}
