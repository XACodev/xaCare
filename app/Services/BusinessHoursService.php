<?php

namespace App\Services;

use App\Models\AdmissionType;
use App\Models\BusinessHoliday;
use App\Models\BusinessHour;
use App\Models\Hospital;
use Carbon\Carbon;

class BusinessHoursService
{
    /**
     * Determina si una fecha/hora cae dentro del horario hábil configurado
     * para el hospital, considerando días no hábiles y feriados.
     */
    public function isBusinessHour(Hospital $hospital, Carbon $datetime): bool
    {
        if ($this->isHoliday($hospital, $datetime)) {
            return false;
        }

        $dayRecord = $this->dayRecord($hospital, $datetime);

        if (! $dayRecord || ! $dayRecord->is_business_day) {
            return false;
        }

        $time = $datetime->format('H:i:s');

        return $time >= $dayRecord->start_time->format('H:i:s')
            && $time <= $dayRecord->end_time->format('H:i:s');
    }

    /**
     * Resuelve el tipo de ingreso real a guardar a partir de un tipo base
     * y la fecha/hora de ingreso. Retorna null si no hay mapeo configurado
     * o el tipo destino no existe.
     */
    public function resolveAdmissionType(AdmissionType $baseType, Carbon $datetime): ?AdmissionType
    {
        $targetSlug = $this->isBusinessHour($baseType->hospital, $datetime)
            ? $baseType->business_hour_type_slug
            : $baseType->after_hours_type_slug;

        if (blank($targetSlug)) {
            return null;
        }

        return AdmissionType::query()
            ->where('hospital_id', $baseType->hospital_id)
            ->where('slug', $targetSlug)
            ->where('active', true)
            ->first();
    }

    private function isHoliday(Hospital $hospital, Carbon $datetime): bool
    {
        return BusinessHoliday::query()
            ->where('hospital_id', $hospital->id)
            ->whereDate('date', $datetime->toDateString())
            ->exists();
    }

    private function dayRecord(Hospital $hospital, Carbon $datetime): ?BusinessHour
    {
        // Carbon: 0=domingo, 6=sábado. BD: 0=lunes, 6=domingo.
        $dayOfWeek = ($datetime->dayOfWeek + 6) % 7;

        return BusinessHour::query()
            ->where('hospital_id', $hospital->id)
            ->where('day_of_week', $dayOfWeek)
            ->first();
    }
}
