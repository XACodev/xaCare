<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payout_batches', function (Blueprint $table) {
            $table->foreignId('hospital_id')->nullable()->after('id')
                ->constrained('hospitals')->nullOnDelete();
        });

        Schema::table('payout_items', function (Blueprint $table) {
            $table->foreignId('hospital_id')->nullable()->after('id')
                ->constrained('hospitals')->nullOnDelete();
        });

        Schema::table('pricing_settings', function (Blueprint $table) {
            $table->foreignId('hospital_id')->nullable()->after('id')
                ->constrained('hospitals')->nullOnDelete();
        });

        // Backfill: cada payout_batch hereda el hospital de su instrumentista.
        DB::statement('
            UPDATE payout_batches
            SET hospital_id = (SELECT hospital_id FROM users WHERE users.id = payout_batches.instrumentist_id)
        ');

        // payout_items hereda el hospital de su payout_batch.
        DB::statement('
            UPDATE payout_items
            SET hospital_id = (SELECT hospital_id FROM payout_batches WHERE payout_batches.id = payout_items.payout_batch_id)
        ');

        // pricing_settings era un singleton global; si existe un único hospital, se lo asignamos.
        $hospitalId = DB::table('hospitals')->count() === 1
            ? DB::table('hospitals')->value('id')
            : null;

        if ($hospitalId) {
            DB::table('pricing_settings')->whereNull('hospital_id')->update(['hospital_id' => $hospitalId]);
        }
    }

    public function down(): void
    {
        Schema::table('payout_batches', function (Blueprint $table) {
            $table->dropForeign(['hospital_id']);
            $table->dropColumn('hospital_id');
        });

        Schema::table('payout_items', function (Blueprint $table) {
            $table->dropForeign(['hospital_id']);
            $table->dropColumn('hospital_id');
        });

        Schema::table('pricing_settings', function (Blueprint $table) {
            $table->dropForeign(['hospital_id']);
            $table->dropColumn('hospital_id');
        });
    }
};
