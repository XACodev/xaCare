<?php

namespace App\Modules\QxLog\Services;

use App\Modules\QxLog\Models\OperatingRoom;
use App\Modules\QxLog\Models\SurgicalCase;
use Carbon\Carbon;

class OperatingRoomAvailabilityService
{
    /**
     * Verifica que el rango [startTime, endTime) no choque con otra cirugía ya
     * programada (no borrador, no cancelada) en el mismo quirófano y fecha.
     */
    public function isAvailable(
        OperatingRoom $room,
        string $procedureDate,
        string $startTime,
        string $endTime,
        ?int $excludeCaseId = null,
    ): bool {
        $start = Carbon::parse("$procedureDate $startTime");
        $end = Carbon::parse("$procedureDate $endTime");

        if ($end->lte($start)) {
            $end->addDay();
        }

        $query = SurgicalCase::query()
            ->where('operating_room_id', $room->id)
            ->whereDate('procedure_date', $procedureDate)
            ->where('is_draft', false)
            ->where('status', '!=', 'cancelled');

        if ($excludeCaseId) {
            $query->where('id', '!=', $excludeCaseId);
        }

        foreach ($query->get() as $case) {
            $caseStart = Carbon::parse("$procedureDate {$case->start_time}");
            $caseEnd = Carbon::parse("$procedureDate {$case->end_time}");

            if ($caseEnd->lte($caseStart)) {
                $caseEnd->addDay();
            }

            if ($start->lt($caseEnd) && $caseStart->lt($end)) {
                return false;
            }
        }

        return true;
    }
}
