<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procedure_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug', 12)->unique();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['hospital_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procedure_types');
    }
};
