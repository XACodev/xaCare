<?php

use App\Models\Hospital;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addTeamIdColumns();
        $this->backfillTeamIds();
        $this->forgetPermissionCache();
    }

    public function down(): void
    {
        $tableNames = config('permission.table_names');
        $teamKey = config('permission.column_names.team_foreign_key', 'team_id');

        if (Schema::hasColumn($tableNames['model_has_roles'], $teamKey)) {
            Schema::table($tableNames['model_has_roles'], function (Blueprint $table) use ($teamKey) {
                $table->dropIndex('model_has_roles_team_foreign_key_index');
                $table->dropColumn($teamKey);
            });
        }

        if (Schema::hasColumn($tableNames['model_has_permissions'], $teamKey)) {
            Schema::table($tableNames['model_has_permissions'], function (Blueprint $table) use ($teamKey) {
                $table->dropIndex('model_has_permissions_team_foreign_key_index');
                $table->dropColumn($teamKey);
            });
        }

        if (Schema::hasColumn($tableNames['roles'], $teamKey)) {
            Schema::table($tableNames['roles'], function (Blueprint $table) use ($teamKey) {
                $table->dropUnique(['team_id', 'name', 'guard_name']);
                $table->dropIndex('roles_team_foreign_key_index');
                $table->dropColumn($teamKey);
                $table->unique(['name', 'guard_name']);
            });
        }

        $this->forgetPermissionCache();
    }

    public function addTeamIdColumns(): void
    {
        $tableNames = config('permission.table_names');
        $teamKey = config('permission.column_names.team_foreign_key', 'team_id');

        if (! Schema::hasColumn($tableNames['roles'], $teamKey)) {
            Schema::table($tableNames['roles'], function (Blueprint $table) use ($teamKey) {
                $table->unsignedBigInteger($teamKey)->nullable()->after('id');
                $table->index($teamKey, 'roles_team_foreign_key_index');

                $table->dropUnique(['name', 'guard_name']);
                $table->unique([$teamKey, 'name', 'guard_name']);
            });
        }

        if (! Schema::hasColumn($tableNames['model_has_permissions'], $teamKey)) {
            Schema::table($tableNames['model_has_permissions'], function (Blueprint $table) use ($teamKey) {
                $table->unsignedBigInteger($teamKey)->nullable();
                $table->index($teamKey, 'model_has_permissions_team_foreign_key_index');
            });
        }

        if (! Schema::hasColumn($tableNames['model_has_roles'], $teamKey)) {
            Schema::table($tableNames['model_has_roles'], function (Blueprint $table) use ($teamKey) {
                $table->unsignedBigInteger($teamKey)->nullable();
                $table->index($teamKey, 'model_has_roles_team_foreign_key_index');
            });
        }
    }

    public function backfillTeamIds(): void
    {
        $tableNames = config('permission.table_names');
        $teamKey = config('permission.column_names.team_foreign_key', 'team_id');
        $userType = (new User)->getMorphClass();

        DB::table($tableNames['roles'])
            ->whereIn('name', Hospital::CORE_ROLES)
            ->update([$teamKey => null]);

        $this->backfillCustomRolesFromEnabledRoles($tableNames['roles'], $teamKey);
        $this->backfillPivotTeamIds($tableNames['model_has_roles'], $teamKey, $userType);
        $this->backfillPivotTeamIds($tableNames['model_has_permissions'], $teamKey, $userType);
    }

    /**
     * @param  array<string, mixed>  $tableNames
     */
    private function backfillCustomRolesFromEnabledRoles(string $rolesTable, string $teamKey): void
    {
        $counts = [];
        $owners = [];

        foreach (DB::table('hospitals')->select('id', 'enabled_roles')->get() as $hospital) {
            $enabledRoles = $hospital->enabled_roles;

            if (is_string($enabledRoles)) {
                $enabledRoles = json_decode($enabledRoles, true);
            }

            if (! is_array($enabledRoles)) {
                continue;
            }

            foreach ($enabledRoles as $roleName) {
                if (! is_string($roleName) || in_array($roleName, Hospital::CORE_ROLES, true)) {
                    continue;
                }

                $counts[$roleName] = ($counts[$roleName] ?? 0) + 1;
                $owners[$roleName] = $hospital->id;
            }
        }

        foreach ($counts as $roleName => $count) {
            if ($count !== 1) {
                continue;
            }

            DB::table($rolesTable)
                ->where('name', $roleName)
                ->whereNull($teamKey)
                ->update([$teamKey => $owners[$roleName]]);
        }
    }

    private function backfillPivotTeamIds(string $pivotTable, string $teamKey, string $userType): void
    {
        $rows = DB::table($pivotTable)
            ->where('model_type', $userType)
            ->whereNull($teamKey)
            ->get();

        foreach ($rows as $row) {
            $hospitalId = DB::table('users')->where('id', $row->model_id)->value('hospital_id');

            if ($hospitalId === null) {
                continue;
            }

            DB::table($pivotTable)
                ->where('model_id', $row->model_id)
                ->where('model_type', $row->model_type)
                ->whereNull($teamKey)
                ->when(
                    isset($row->role_id),
                    fn ($query) => $query->where('role_id', $row->role_id),
                )
                ->when(
                    isset($row->permission_id),
                    fn ($query) => $query->where('permission_id', $row->permission_id),
                )
                ->update([$teamKey => $hospitalId]);
        }
    }

    private function forgetPermissionCache(): void
    {
        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }
};
