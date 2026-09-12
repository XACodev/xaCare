<?php

namespace App\Support;

use App\Models\Hospital;
use App\Models\Patient;
use Illuminate\Support\Facades\DB;

final class PatientExpediente
{
    /**
     * Genera el siguiente número de expediente para un hospital.
     *
     * Se basa en el valor numérico más alto actual; si existe un registro
     * manual no numérico, se ignora y se continúa la secuencia numérica.
     */
    public static function siguiente(Hospital $hospital): string
    {
        $ultimo = Patient::withoutGlobalScopes()
            ->where('hospital_id', $hospital->id)
            ->whereNotNull('expediente_no')
            ->select(DB::raw('MAX(CAST(expediente_no AS UNSIGNED)) as maximo'))
            ->value('maximo');

        $siguiente = ((int) $ultimo) + 1;

        return (string) max($siguiente, 1);
    }
}
