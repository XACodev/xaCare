<?php
// database/migrations/2026_09_02_120000_drop_legacy_columns_from_surgical_cases.php
use App\Modules\QxLog\Models\SurgicalAssignment;
use App\Modules\QxLog\Models\SurgicalCase;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Columnas legacy de instrumentista/doctor/circulante en surgical_cases, reemplazadas por
     * surgical_assignments (ver MigrateToSurgicalAssignments, ya retirado tras confirmar que
     * ya no queda nada por migrar).
     */
    private const LEGACY_COLUMNS = [
        'instrumentist_id',
        'instrumentist_name',
        'doctor_id',
        'doctor_name',
        'circulating_id',
        'circulating_name',
    ];

    public function up(): void
    {
        // GUARDIA DE SEGURIDAD: nunca dropear estas columnas si todavía queda algún
        // surgical_case con datos legacy (instrumentist_id/doctor_id/circulating_id) que no
        // tenga NINGUNA fila correspondiente en surgical_assignments. Si eso pasara, dropear
        // las columnas perdería para siempre el único registro de quién participó en ese caso.
        //
        // El check corre y puede lanzar ANTES de tocar el schema con Schema::table(...), así
        // que si lanza, la migración queda marcada como fallida y ninguna columna fue tocada
        // (no se necesita envolver el DDL en una transacción explícita: nunca se llega a él).
        $orphanedCount = $this->countLegacyCasesWithoutAssignments();

        if ($orphanedCount > 0) {
            throw new \RuntimeException(
                "No se puede eliminar columnas legacy: {$orphanedCount} casos quirúrgicos tienen datos de ".
                'instrumentista/doctor/circulante (por ID o por nombre libre) que nunca se migraron a '.
                'surgical_assignments. No se puede continuar con el drop sin perder esos datos: revisar el '.
                'historial de esta rama para el comando de migración usado (ya retirado de este código), o '.
                'migrar manualmente los casos afectados antes de reintentar.'
            );
        }

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            // SQLite (usado en dev/test) no permite dropear una columna que forma parte de una
            // foreign key inline -- ni con PRAGMA foreign_keys=OFF ni con legacy_alter_table --
            // por lo que Schema::table()->dropColumn() falla ahí aunque funcione en MySQL. La
            // única forma soportada por SQLite es reconstruir la tabla sin esas columnas/FKs.
            $this->sqliteRebuildSurgicalCasesWithoutLegacyColumns();

            return;
        }

        Schema::table('surgical_cases', function (Blueprint $table) {
            // Estos índices compuestos fueron creados con nombre explícito en la migración
            // original de `procedures` (2026_01_12_165926) y sobreviven el rename de tabla a
            // `surgical_cases` sin cambiar de nombre, así que se pueden dropear por nombre
            // exacto sin importar el driver.
            $table->dropIndex('instrumentist_status_idx');
            $table->dropIndex('doctor_status_idx');
            $table->dropIndex('circulating_status_idx');

            // En MySQL/Postgres las foreign keys de instrumentist_id/doctor_id/circulating_id
            // fueron nombradas con el prefijo de tabla vigente al momento de crearlas
            // (`procedures_..._foreign`); un RENAME TABLE no renombra constraints existentes,
            // así que hay que dropearlas por su nombre original antes de poder dropear las
            // columnas.
            $table->dropForeign('procedures_instrumentist_id_foreign');
            $table->dropForeign('procedures_doctor_id_foreign');
            $table->dropForeign('procedures_circulating_id_foreign');

            $table->dropColumn(self::LEGACY_COLUMNS);
        });
    }

    /**
     * Reconstruye surgical_cases sin las columnas/foreign keys/índices legacy, tal como exige
     * SQLite para poder eliminar una columna referenciada por una foreign key inline. Lee el
     * esquema ACTUAL de la tabla vía PRAGMA (columnas, foreign keys, índices) en vez de
     * hardcodear una copia de la definición, para no arriesgarse a divergir de la tabla real y
     * perder alguna columna que no está en este archivo. Todos los datos se preservan con un
     * INSERT INTO ... SELECT explícito por nombre de columna.
     */
    private function sqliteRebuildSurgicalCasesWithoutLegacyColumns(): void
    {
        $table = 'surgical_cases';
        $tmpTable = 'surgical_cases_drop_legacy_tmp';

        $columns = collect(DB::select("PRAGMA table_info(\"{$table}\")"))
            ->reject(fn ($column) => in_array($column->name, self::LEGACY_COLUMNS, true))
            ->values();

        $foreignKeys = collect(DB::select("PRAGMA foreign_key_list(\"{$table}\")"))
            ->reject(fn ($fk) => in_array($fk->from, self::LEGACY_COLUMNS, true))
            ->values();

        $indexesToKeep = collect(DB::select(
            "SELECT name, sql FROM sqlite_master WHERE type = 'index' AND tbl_name = ? AND sql IS NOT NULL",
            [$table]
        ))->reject(fn ($idx) => in_array($idx->name, [
            'instrumentist_status_idx', 'doctor_status_idx', 'circulating_status_idx',
        ], true));

        $columnDefs = $columns->map(function ($column) {
            $def = "\"{$column->name}\" {$column->type}";

            if ($column->pk) {
                $def .= ' primary key autoincrement';
            } elseif ($column->notnull) {
                $def .= ' not null';
            }

            if ($column->dflt_value !== null) {
                $def .= " default {$column->dflt_value}";
            }

            return $def;
        });

        $foreignKeyDefs = $foreignKeys->map(function ($fk) {
            $def = "foreign key(\"{$fk->from}\") references {$fk->table}(\"{$fk->to}\")";

            if ($fk->on_delete && strtoupper($fk->on_delete) !== 'NO ACTION') {
                $def .= ' on delete '.strtolower($fk->on_delete);
            }

            if ($fk->on_update && strtoupper($fk->on_update) !== 'NO ACTION') {
                $def .= ' on update '.strtolower($fk->on_update);
            }

            return $def;
        });

        $allDefs = $columnDefs->merge($foreignKeyDefs)->implode(', ');

        DB::statement("CREATE TABLE \"{$tmpTable}\" ({$allDefs})");

        $columnNames = $columns->map(fn ($column) => "\"{$column->name}\"")->implode(', ');
        DB::statement("INSERT INTO \"{$tmpTable}\" ({$columnNames}) SELECT {$columnNames} FROM \"{$table}\"");

        DB::statement("DROP TABLE \"{$table}\"");
        DB::statement("ALTER TABLE \"{$tmpTable}\" RENAME TO \"{$table}\"");

        foreach ($indexesToKeep as $index) {
            DB::statement($index->sql);
        }
    }

    public function down(): void
    {
        Schema::table('surgical_cases', function (Blueprint $table) {
            // Mismos tipos que la migración original de creación de `procedures`
            // (2026_01_12_165926_create_procedures_table.php).
            $table->foreignId('instrumentist_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('doctor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('circulating_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('instrumentist_name')->nullable();
            $table->string('doctor_name')->nullable();
            $table->string('circulating_name')->nullable();

            $table->index(['instrumentist_id', 'status'], 'instrumentist_status_idx');
            $table->index(['doctor_id', 'status'], 'doctor_status_idx');
            $table->index(['circulating_id', 'status'], 'circulating_status_idx');
        });

        // NOTA IMPORTANTE: este down() solo restaura el ESQUEMA (columnas nullable, vacías).
        // NO restaura los datos legacy que existían antes del up() -- eso requeriría un backup
        // de datos aparte tomado antes de correr esta migración, fuera de alcance de esta
        // migración. Si se necesita revertir con datos, restaurar surgical_cases desde un
        // backup tomado antes del drop.
    }

    /**
     * Cuenta cuántos surgical_cases tienen datos legacy de instrumentista/doctor/circulante (
     * CUALQUIERA de las 6 columnas legacy -- instrumentist_id/doctor_id/circulating_id o sus
     * contrapartes _name -- no nula) y NO tienen ninguna fila en surgical_assignments para ese
     * caso. Las columnas _name deben chequearse por separado de las _id: en casos de emergencia
     * un caso puede tener el nombre de instrumentista/doctor/circulante cargado como texto libre
     * SIN ningún User vinculado (instrumentist_id/doctor_id/circulating_id nulos), así que
     * chequear solo los IDs dejaría pasar -- y perder para siempre -- esos nombres. No se excluye
     * el string vacío de este chequeo a propósito: ante la duda de si '' representa "sin dato" o
     * un valor real cargado así, se prefiere la interpretación más conservadora (tratarlo como
     * dato presente) dado que la política del proyecto es no perder datos de producción bajo
     * ninguna circunstancia.
     *
     * Basta con que exista al menos una fila de asignación por caso: MigrateToSurgicalAssignments
     * ::migrateOneAssignment() migraba los 3 roles (instrumentista/cirujano/circulante) de forma
     * atómica por caso dentro de una transacción, así que "al menos una asignación" ya garantiza
     * que el caso completo fue migrado.
     */
    private function countLegacyCasesWithoutAssignments(): int
    {
        $legacyCaseIds = SurgicalCase::withoutGlobalScopes()
            ->where(function ($query) {
                $query->whereNotNull('instrumentist_id')
                    ->orWhereNotNull('doctor_id')
                    ->orWhereNotNull('circulating_id')
                    ->orWhereNotNull('instrumentist_name')
                    ->orWhereNotNull('doctor_name')
                    ->orWhereNotNull('circulating_name');
            })
            ->pluck('id');

        if ($legacyCaseIds->isEmpty()) {
            return 0;
        }

        $migratedCaseIds = SurgicalAssignment::withoutGlobalScopes()
            ->whereIn('surgical_case_id', $legacyCaseIds)
            ->distinct()
            ->pluck('surgical_case_id');

        return $legacyCaseIds->diff($migratedCaseIds)->count();
    }
};
