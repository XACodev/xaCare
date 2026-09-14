<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admission_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->boolean('active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('es_ingreso_rapido_default')->default(false);
            $table->json('visible_sections')->nullable();
            $table->json('required_sections')->nullable();
            $table->timestamps();

            $table->unique(['hospital_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_types');
    }
};
