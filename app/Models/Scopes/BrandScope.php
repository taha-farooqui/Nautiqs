<?php

namespace App\Models\Scopes;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class BrandScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (! auth()->check()) {
            return; // console, queued work: callers filter explicitly
        }
        $user = auth()->user();

        if ($user->role === User::ROLE_SUPERADMIN) {
            return;
        }
        if ($user->role === User::ROLE_BRAND_MANAGER && $user->brand_account_id) {
            $builder->where('brand_account_id', (string) $user->brand_account_id);
            return;
        }
        // Dealers never read brand records through the normal query path —
        // only BrandSync does, explicitly and unscoped.
        $builder->whereRaw(['_id' => ['$exists' => false]]);
    }
}
