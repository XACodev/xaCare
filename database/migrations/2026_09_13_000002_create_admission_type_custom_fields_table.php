<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admission_type_custom_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->cascadeOnDelete();
            $table->foreignId('admission_type_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('step');
            $table->string('label');
            $table->string('slug');
            $table->string('field_type');
            $table->json('options')->nullable();
            $table->boolean('required')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['hospital_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_type_custom_fields');
    }
};
