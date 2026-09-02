<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        // Auth::user() / Auth::check() re-entran al provider y recargan User,
        // lo que re-aplica este scope y produce recursión infinita (HTTP 500).
        if (! Auth::hasUser()) {
            return;
        }

        $user = Auth::user();

        // Sin usuario, o super admin sin hospital: no se filtra (ve todo).
        if (! $user || ! $user->hospital_id) {
            return;
        }

        $builder->where($model->getTable().'.hospital_id', $user->hospital_id);
    }
}
