<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operating_rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_id')->constrained('hospitals')->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_default')->default(false);
            $table->json('default_for_procedure_types')->nullable();
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['hospital_id', 'name'], 'operating_rooms_hospital_name_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operating_rooms');
    }
};
