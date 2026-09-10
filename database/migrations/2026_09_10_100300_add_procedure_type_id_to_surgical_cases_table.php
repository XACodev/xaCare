<?php

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
        Schema::table('surgical_cases', function (Blueprint $table) {
            $table->foreignId('procedure_type_id')->nullable()->after('procedure_type')->constrained();
        });

        DB::table('surgical_cases')->whereNotNull('procedure_type')->orderBy('hospital_id')->chunkById(200, function ($rows) {
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

                DB::table('surgical_cases')->where('id', $row->id)->update(['procedure_type_id' => $type->id]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('surgical_cases', function (Blueprint $table) {
            $table->dropConstrainedForeignId('procedure_type_id');
        });
    }
};
