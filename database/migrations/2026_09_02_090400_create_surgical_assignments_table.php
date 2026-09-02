<?php
// database/migrations/2026_09_02_090400_create_surgical_assignments_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('surgical_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_id')->constrained('hospitals')->cascadeOnDelete();
            $table->foreignId('surgical_case_id')->constrained('surgical_cases')->cascadeOnDelete();
            $table->foreignId('surgical_role_id')->constrained('surgical_roles')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->decimal('calculated_amount', 10, 2)->default(0);
            $table->json('pricing_snapshot')->nullable();
            $table->boolean('is_courtesy')->default(false);
            $table->string('note')->nullable();

            $table->string('status')->default('pending'); // pending | paid
            $table->unsignedBigInteger('payout_item_id')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'status'], 'surgical_assignments_user_status_idx');
            $table->index(['surgical_case_id'], 'surgical_assignments_case_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('surgical_assignments');
    }
};
