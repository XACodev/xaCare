<?php
// database/migrations/2026_09_10_100100_add_procedure_type_id_to_role_rates_table.php

use App\Modules\QxLog\Models\ProcedureType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('role_rates', function (Blueprint $table) {
            $table->foreignId('procedure_type_id')->nullable()->after('procedure_type')->constrained();
        });

        // El unique original (surgical_role_id, user_id, procedure_type) referencia la columna
        // string que la migración hermana (2026_09_10_100200) va a dropear -- SQLite no permite
        // dropear una columna todavía referenciada por un índice. Lo reemplazamos aquí por el
        // equivalente sobre procedure_type_id antes de que llegue ese drop.
        Schema::table('role_rates', function (Blueprint $table) {
            $table->dropUnique('role_rates_unique_key');
            $table->unique(['surgical_role_id', 'user_id', 'procedure_type_id'], 'role_rates_procedure_type_id_unique_key');
        });

        DB::table('role_rates')->whereNotNull('procedure_type')->orderBy('hospital_id')->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                $normalized = trim((string) $row->procedure_type);
                if ($normalized === '') {
                    continue;
                }

                $type = ProcedureType::withoutGlobalScopes()
                    ->where('hospital_id', $row->hospital_id)
                    ->whereRaw('LOWER(name) = ?', [Str::lower($normalized)])
                    ->first();

                if (! $type) {
                    $type = ProcedureType::withoutGlobalScopes()->create([
                        'hospital_id' => $row->hospital_id,
                        'name' => $normalized,
                        'active' => true,
                    ]);
                }

                DB::table('role_rates')->where('id', $row->id)->update(['procedure_type_id' => $type->id]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('role_rates', function (Blueprint $table) {
            $table->dropUnique('role_rates_procedure_type_id_unique_key');
            $table->unique(['surgical_role_id', 'user_id', 'procedure_type'], 'role_rates_unique_key');
        });

        Schema::table('role_rates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('procedure_type_id');
        });
    }
};
