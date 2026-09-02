<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection(config('activitylog.database_connection'))->table(config('activitylog.table_name'), function (Blueprint $table) {
            $table->foreignId('hospital_id')->nullable()->after('id')->constrained('hospitals')->nullOnDelete();
        });

        $connection = config('activitylog.database_connection');
        $tableName = config('activitylog.table_name');

        DB::connection($connection)->table($tableName)->whereNull('hospital_id')->orderBy('id')->each(function (object $log) use ($connection, $tableName): void {
            if (! $log->subject_type || ! $log->subject_id || ! class_exists($log->subject_type)) {
                return;
            }

            $subjectTable = (new $log->subject_type)->getTable();

            if (! Schema::connection($connection)->hasColumn($subjectTable, 'hospital_id')) {
                return;
            }

            $hospitalId = DB::connection($connection)->table($subjectTable)->where('id', $log->subject_id)->value('hospital_id');

            if ($hospitalId) {
                DB::connection($connection)->table($tableName)->where('id', $log->id)->update(['hospital_id' => $hospitalId]);
            }
        });
    }

    public function down(): void
    {
        Schema::connection(config('activitylog.database_connection'))->table(config('activitylog.table_name'), function (Blueprint $table) {
            $table->dropConstrainedForeignId('hospital_id');
        });
    }
};
