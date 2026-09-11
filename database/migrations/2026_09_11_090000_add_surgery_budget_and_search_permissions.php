<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * `admin` es global (team_id null): recibe view_total + manage.
     * Los roles core por-hospital (doctor/instrumentist/circulating) reciben
     * view_own + search.appear_as_suggestion — para TODOS los hospitales ya
     * existentes, uno por uno, porque `role_has_permissions` no tiene team_id
     * (el team vive en el propio Role), así que hay que tocar cada fila de rol
     * por hospital directamente.
     */
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'surgeries.budget.view_total',
            'surgeries.budget.view_own',
            'surgeries.budget.manage',
            'search.appear_as_suggestion',
        ];

        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'team_id' => null]);
        $admin->givePermissionTo(['surgeries.budget.view_total', 'surgeries.budget.manage']);

        Role::whereIn('name', ['doctor', 'instrumentist', 'circulating'])
            ->whereNotNull('team_id')
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo([
                'surgeries.budget.view_own',
                'search.appear_as_suggestion',
            ]));
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $admin = Role::where('name', 'admin')->whereNull('team_id')->first();

        if ($admin) {
            $admin->revokePermissionTo(['surgeries.budget.view_total', 'surgeries.budget.manage']);
        }

        Role::whereIn('name', ['doctor', 'instrumentist', 'circulating'])
            ->whereNotNull('team_id')
            ->get()
            ->each(fn (Role $role) => $role->revokePermissionTo([
                'surgeries.budget.view_own',
                'search.appear_as_suggestion',
            ]));
    }
};
