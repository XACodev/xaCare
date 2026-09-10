<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * El rol "admin" es global (team_id null, compartido por todos los hospitales). Las
     * nuevas features no se asignan por defecto a ningún rol (decisión de diseño del
     * tablero de cirugías), pero eso dejó a TODO admin de hospital sin surgeries.* desde
     * que la feature se lanzó, sin ningún camino en la UI para autoasignárselo ("Roles
     * Custom" no permite editar roles core). Admin debe tener siempre el catálogo
     * completo de permisos de hospital.
     */
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'surgeries.view',
            'surgeries.schedule',
            'surgeries.cancel',
            'surgeries.delete',
        ];

        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $admin = Role::where('name', 'admin')->whereNull('team_id')->first();

        if ($admin) {
            $admin->givePermissionTo($permissions);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $admin = Role::where('name', 'admin')->whereNull('team_id')->first();

        if ($admin) {
            $admin->revokePermissionTo([
                'surgeries.view',
                'surgeries.schedule',
                'surgeries.cancel',
                'surgeries.delete',
            ]);
        }
    }
};
