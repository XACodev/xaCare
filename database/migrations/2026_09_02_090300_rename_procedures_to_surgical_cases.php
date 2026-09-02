<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('procedures', 'surgical_cases');
    }

    public function down(): void
    {
        Schema::rename('surgical_cases', 'procedures');
    }
};
