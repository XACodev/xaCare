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
        $user = Auth::user();

        // Sin usuario, o super admin sin hospital: no se filtra (ve todo).
        if (! $user || ! $user->hospital_id) {
            return;
        }

        $builder->where($model->getTable().'.hospital_id', $user->hospital_id);
    }
}
