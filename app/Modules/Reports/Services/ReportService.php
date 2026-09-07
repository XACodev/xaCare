<?php

namespace App\Modules\Reports\Services;

use App\Models\Hospital;
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
            ->with(['assignments.surgicalRole', 'assignments.user'])
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
}
