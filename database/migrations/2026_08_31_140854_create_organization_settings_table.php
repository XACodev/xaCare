<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('organization_settings', function (Blueprint $table) {
            $table->id();

            $table->string('org_name')->default('Hospital Nuestra Señora del Carmen');
            // MySQL no admite DEFAULT en columnas TEXT (error 1101); el valor
            // real lo pone el INSERT de abajo.
            $table->text('voucher_legend');
            $table->decimal('flat_default_rate', 10, 2)->default(200.00);

            $table->timestamps();
            $table->softDeletes();
        });

        // Fila única con los valores vigentes en producción, para que la migración
        // no cambie el comportamiento visible del voucher al desplegarse.
        DB::table('organization_settings')->insert([
            'org_name' => 'Hospital Nuestra Señora del Carmen',
            'voucher_legend' => 'Por honorarios correspondientes a servicios de instrumentación prestados en procedimientos quirúrgicos.',
            'flat_default_rate' => 200.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organization_settings');
    }
};
