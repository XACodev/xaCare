<?php

namespace App\Modules\Reports\Services;

use App\Models\Hospital;
use App\Modules\QxLog\Models\OperatingRoom;
use App\Modules\QxLog\Models\PayoutBatch;
use App\Modules\QxLog\Models\SurgicalAssignment;
use App\Modules\QxLog\Models\SurgicalCase;
use Illuminate\Support\Collection;

/**
 * Consultas de reportes reutilizables sobre los datos de QxLog. Las
 * consultas de hospital confían en el TenantScope de cada modelo (el
 * usuario autenticado ya queda filtrado a su propio hospital); las de
 * plataforma se ejecutan como platform admin (hospital_id null), a quien
 * el TenantScope no le filtra nada, así que ve todos los hospitales.
 */
class ReportService
{
    /**
     * @return Collection<int, SurgicalCase>
     */
    public function proceduresByDateRange(?string $from, ?string $to): Collection
    {
        return SurgicalCase::query()
            ->with(['assignments.surgicalRole', 'assignments.user', 'procedureType'])
            ->when($from, fn ($q) => $q->whereDate('procedure_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('procedure_date', '<=', $to))
            ->orderByDesc('procedure_date')
            ->get();
    }

    /**
     * @return Collection<int, PayoutBatch>
     */
    public function payoutsByPeriod(?string $from, ?string $to): Collection
    {
        return PayoutBatch::query()
            ->with(['payee'])
            ->when($from, fn ($q) => $q->whereDate('paid_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('paid_at', '<=', $to))
            ->orderByDesc('paid_at')
            ->get();
    }

    /**
     * Distribución de monto/casos asignados por médico y por rol quirúrgico.
     *
     * @return Collection<int, array{role: string, user: string, cases: int, amount: float}>
     */
    public function procedureDistribution(?string $from, ?string $to): Collection
    {
        return SurgicalAssignment::query()
            ->with(['surgicalRole', 'user', 'surgicalCase'])
            ->whereHas('surgicalCase', function ($query) use ($from, $to) {
                $query->when($from, fn ($q) => $q->whereDate('procedure_date', '>=', $from))
                    ->when($to, fn ($q) => $q->whereDate('procedure_date', '<=', $to));
            })
            ->get()
            ->groupBy(fn (SurgicalAssignment $a) => $a->surgicalRole?->name.'|'.($a->user?->name ?? '—'))
            ->map(function (Collection $assignments) {
                $first = $assignments->first();

                return [
                    'role' => $first->surgicalRole?->name ?? '—',
                    'user' => $first->user?->name ?? '—',
                    'cases' => $assignments->count(),
                    'amount' => (float) $assignments->sum('calculated_amount'),
                ];
            })
            ->values();
    }

    /**
     * @return Collection<int, Hospital>
     */
    public function hospitalsWithPlans(): Collection
    {
        return Hospital::query()->orderBy('name')->get();
    }

    /**
     * Total de procedimientos por hospital.
     *
     * @return Collection<int, array{hospital_id: int, hospital: string, total: int}>
     */
    public function proceduresTotalByHospital(?string $from, ?string $to): Collection
    {
        return SurgicalCase::query()
            ->selectRaw('hospital_id, count(*) as total')
            ->when($from, fn ($q) => $q->whereDate('procedure_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('procedure_date', '<=', $to))
            ->groupBy('hospital_id')
            ->get()
            ->map(fn ($row) => [
                'hospital_id' => (int) $row->hospital_id,
                'hospital' => Hospital::find($row->hospital_id)?->name ?? '—',
                'total' => (int) $row->total,
            ]);
    }

    /**
     * Revenue estimado: suma de payout batches por hospital.
     *
     * @return Collection<int, array{hospital_id: int, hospital: string, total: float}>
     */
    public function revenueEstimateByHospital(?string $from, ?string $to): Collection
    {
        return PayoutBatch::query()
            ->selectRaw('hospital_id, sum(total_amount) as total')
            ->when($from, fn ($q) => $q->whereDate('paid_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('paid_at', '<=', $to))
            ->groupBy('hospital_id')
            ->get()
            ->map(fn ($row) => [
                'hospital_id' => (int) $row->hospital_id,
                'hospital' => Hospital::find($row->hospital_id)?->name ?? '—',
                'total' => (float) $row->total,
            ]);
    }

    /**
     * Ingresos por día (últimos N días) agrupados por tipo de ingreso real
     * del hospital (catálogo dinámico AdmissionType — no las 3 categorías
     * fijas Hosp/Emerg/COEX del mockup, que no existen como enum en el
     * esquema). Usa AdmissionType::colorBarClass() para el mismo color
     * consistente que ya usan los chips de tipo de ingreso en el resto de
     * la app.
     *
     * @return Collection<int, array{date: string, types: Collection, total: float}>
     */
    public function revenueByDayAndAdmissionType(int $days = 14): Collection
    {
        $from = now()->subDays($days - 1)->startOfDay();

        $cases = SurgicalCase::query()
            ->with('admission.admissionType')
            ->whereDate('procedure_date', '>=', $from)
            ->get()
            ->groupBy(fn (SurgicalCase $c) => $c->procedure_date->format('Y-m-d'));

        return collect(range(0, $days - 1))
            ->map(fn (int $i) => $from->copy()->addDays($i)->format('Y-m-d'))
            ->map(function (string $date) use ($cases) {
                $dayCases = $cases->get($date, collect());

                return [
                    'date' => $date,
                    'types' => $dayCases
                        ->groupBy(fn (SurgicalCase $c) => $c->admission?->admissionType?->name ?? '—')
                        ->map(fn (Collection $group, string $name) => [
                            'name' => $name,
                            'amount' => (float) $group->sum('calculated_amount'),
                            'color' => $group->first()->admission?->admissionType?->colorBarClass() ?? 'bg-zinc-400',
                        ])
                        ->values(),
                    'total' => (float) $dayCases->sum('calculated_amount'),
                ];
            });
    }

    /**
     * Procedimientos por quirófano en un rango, con porcentaje sobre el
     * total (para la lista "Procedimientos por quirófano").
     *
     * @return Collection<int, array{name: string, count: int, percent: int}>
     */
    public function proceduresByOperatingRoom(?string $from, ?string $to): Collection
    {
        $counts = SurgicalCase::query()
            ->when($from, fn ($q) => $q->whereDate('procedure_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('procedure_date', '<=', $to))
            ->selectRaw('operating_room_id, count(*) as total')
            ->groupBy('operating_room_id')
            ->pluck('total', 'operating_room_id');

        $totalAll = max($counts->sum(), 1);

        return OperatingRoom::query()
            ->whereIn('id', $counts->keys())
            ->get()
            ->map(fn (OperatingRoom $room) => [
                'name' => $room->name,
                'count' => (int) $counts->get($room->id, 0),
                'percent' => (int) round($counts->get($room->id, 0) / $totalAll * 100),
            ])
            ->sortByDesc('count')
            ->values();
    }
}
