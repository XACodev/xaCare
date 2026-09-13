<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admission_custom_field_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('custom_field_id')
                ->constrained('admission_type_custom_fields')
                ->cascadeOnDelete();
            $table->text('value')->nullable();
            $table->timestamps();

            $table->index(['admission_id', 'custom_field_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_custom_field_values');
    }
};
