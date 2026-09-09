<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('surgical_cases', function (Blueprint $table) {
            $table->foreignId('operating_room_id')->nullable()->after('hospital_id')
                ->constrained('operating_rooms')->nullOnDelete();
            $table->foreignId('surgery_status_id')->nullable()->after('operating_room_id')
                ->constrained('surgery_statuses')->nullOnDelete();
            $table->boolean('is_draft')->default(false)->after('surgery_status_id');
        });
    }

    public function down(): void
    {
        Schema::table('surgical_cases', function (Blueprint $table) {
            $table->dropConstrainedForeignId('operating_room_id');
            $table->dropConstrainedForeignId('surgery_status_id');
            $table->dropColumn('is_draft');
        });
    }
};
