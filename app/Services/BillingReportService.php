<?php

namespace App\Services;

use App\Enums\SubscriptionStatus;
use App\Models\Hospital;
use Illuminate\Support\Collection;

class BillingReportService
{
    /**
     * Suma de precios de plan de los hospitales con suscripción activa.
     */
    public function estimatedMrr(): float
    {
        return Hospital::query()
            ->where('subscription_status', SubscriptionStatus::Active)
            ->get(['plan'])
            ->sum(fn (Hospital $hospital) => (float) config("billing.plans.{$hospital->plan}.price", 0));
    }

    /**
     * @return array<string, int>
     */
    public function hospitalsByStatus(): array
    {
        $counts = Hospital::query()
            ->selectRaw('subscription_status, count(*) as aggregate')
            ->groupBy('subscription_status')
            ->pluck('aggregate', 'subscription_status');

        $result = [];

        foreach (SubscriptionStatus::cases() as $status) {
            $result[$status->value] = (int) ($counts[$status->value] ?? 0);
        }

        return $result;
    }

    /**
     * Hospitales en trial cuyo trial_ends_at cae dentro de los próximos N días.
     *
     * @return Collection<int, Hospital>
     */
    public function trialsExpiringWithin(int $days): Collection
    {
        return Hospital::query()
            ->where('subscription_status', SubscriptionStatus::Trialing)
            ->whereNotNull('trial_ends_at')
            ->whereBetween('trial_ends_at', [now(), now()->addDays($days)])
            ->orderBy('trial_ends_at')
            ->get();
    }

    /**
     * @return Collection<int, Hospital>
     */
    public function filteredHospitals(?string $status, ?string $plan): Collection
    {
        return Hospital::query()
            ->when($status, fn ($query) => $query->where('subscription_status', $status))
            ->when($plan, fn ($query) => $query->where('plan', $plan))
            ->orderBy('name')
            ->get();
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function toCsvRows(Collection $hospitals): array
    {
        return $hospitals->map(fn (Hospital $hospital) => [
            'name' => $hospital->name,
            'plan' => $hospital->plan,
            'subscription_status' => $hospital->subscription_status->value,
            'trial_ends_at' => $hospital->trial_ends_at?->format('Y-m-d H:i') ?? '',
            'is_active' => $hospital->is_active ? '1' : '0',
        ])->all();
    }
}
