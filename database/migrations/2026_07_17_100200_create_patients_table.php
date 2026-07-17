<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_id')->nullable()->constrained('hospitals')->nullOnDelete();
            $table->string('expediente_no')->nullable();

            $table->string('primer_apellido');
            $table->string('segundo_apellido')->nullable();
            $table->string('primer_nombre');
            $table->string('segundo_nombre')->nullable();

            $table->string('dpi')->nullable();
            $table->date('fecha_nacimiento')->nullable();
            $table->string('sexo')->nullable();           // M|F
            $table->string('lugar_nacimiento')->nullable();
            $table->string('nacionalidad')->nullable();
            $table->string('estado_civil')->nullable();    // S|C|U|D|V

            $table->string('direccion_habitual')->nullable();
            $table->string('calle_o_lugar')->nullable();
            $table->string('municipio')->nullable();
            $table->string('departamento')->nullable();
            $table->string('telefono')->nullable();

            $table->string('nombre_padre')->nullable();
            $table->string('nombre_madre')->nullable();
            $table->string('nombre_conyuge')->nullable();
            $table->string('contacto_emergencia')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['hospital_id', 'expediente_no']);
            $table->index(['hospital_id', 'primer_apellido', 'primer_nombre'], 'patients_name_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
