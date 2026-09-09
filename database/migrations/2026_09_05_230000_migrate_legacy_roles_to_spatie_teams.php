<?php

use App\Models\Hospital;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Backfill de roles Spatie a partir de la columna legacy `users.role`.
     *
     * Idempotente: solo asigna el rol Spatie si el usuario aún no lo tiene, y
     * omite usuarios cuyo rol legacy no tenga un Role Spatie correspondiente
     * (no crea roles nuevos aquí, eso es responsabilidad de
     * RolesAndPermissionsSeeder). Segura para correr sobre datos reales:
     * no falla si faltan roles, no duplica asignaciones existentes.
     */
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        User::query()
            ->whereNotNull('role')
            ->where('role', '!=', '')
            ->orderBy('id')
            ->chunkById(200, function ($users): void {
                foreach ($users as $user) {
                    $this->assignLegacyRole($user);
                }
            });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Intencionalmente no reversible: no podemos distinguir con seguridad
        // los roles que esta migración asignó de los asignados por otras vías
        // (seeders, UI de administración) antes o después de correrla.
    }

    private function assignLegacyRole(User $user): void
    {
        $roleName = $user->role;

        if (! is_string($roleName) || $roleName === '') {
            return;
        }

        $teamId = in_array($roleName, Hospital::CORE_ROLES, true) ? null : $user->hospital_id;

        $role = Role::query()
            ->where('name', $roleName)
            ->where('guard_name', 'web')
            ->when(
                $teamId === null,
                fn ($query) => $query->whereNull('team_id'),
                fn ($query) => $query->where('team_id', $teamId),
            )
            ->first();

        if ($role === null) {
            return;
        }

        if ($user->hasRole($roleName)) {
            return;
        }

        $user->assignRole($role);
    }
};
