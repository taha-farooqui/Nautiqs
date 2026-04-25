<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (! auth()->check()) {
            return;
        }

        $user = auth()->user();

        // Superadmin sees everything (spec §2: platform-wide access).
        if ($user->role === \App\Models\User::ROLE_SUPERADMIN) {
            return;
        }

        // Tenant users are pinned to their company_id.
        if ($user->company_id) {
            $builder->where('company_id', $user->company_id);
        } else {
            // Defensive: a tenant user with no company should see nothing
            // rather than leaking data.
            $builder->whereRaw(['_id' => ['$exists' => false]]);
        }
    }
}
