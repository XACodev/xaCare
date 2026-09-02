<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payout_batches', function (Blueprint $table) {
            $table->renameColumn('instrumentist_id', 'payee_id');
        });

        Schema::table('payout_items', function (Blueprint $table) {
            $table->dropForeign(['procedure_id']);
            $table->renameColumn('procedure_id', 'surgical_assignment_id');
        });

        Schema::table('payout_items', function (Blueprint $table) {
            $table->foreign('surgical_assignment_id')->references('id')->on('surgical_assignments')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payout_items', function (Blueprint $table) {
            $table->dropForeign(['surgical_assignment_id']);
            $table->renameColumn('surgical_assignment_id', 'procedure_id');
        });

        Schema::table('payout_batches', function (Blueprint $table) {
            $table->renameColumn('payee_id', 'instrumentist_id');
        });
    }
};
