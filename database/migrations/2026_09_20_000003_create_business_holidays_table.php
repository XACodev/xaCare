<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_holidays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('name')->nullable();
            $table->timestamps();

            $table->unique(['hospital_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_holidays');
    }
};
