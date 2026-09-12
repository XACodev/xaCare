<?php
// database/migrations/2026_09_12_120200_create_surgery_quote_line_items_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('surgery_quote_line_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('surgery_quote_id')->constrained('surgery_quotes')->cascadeOnDelete();
            $table->foreignId('surgical_role_id')->nullable()->constrained('surgical_roles')->nullOnDelete();
            $table->string('label');
            $table->decimal('amount', 10, 2)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('surgery_quote_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('surgery_quote_line_items');
    }
};
