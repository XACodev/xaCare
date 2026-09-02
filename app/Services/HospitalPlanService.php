<?php

namespace App\Services;

use App\Enums\SubscriptionStatus;
use App\Models\Hospital;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class HospitalPlanService
{
    /**
     * @return list<string>
     */
    public function featuresForPlan(string $plan): array
    {
        $features = config("billing.plans.{$plan}.features");

        if (! is_array($features)) {
            throw new InvalidArgumentException("Unknown billing plan [{$plan}].");
        }

        return array_values($features);
    }

    /**
     * @return array<string, array{name: string, stripe_price_id: ?string, features: list<string>}>
     */
    public function catalog(): array
    {
        /** @var array<string, array{name: string, stripe_price_id: ?string, features: list<string>}> $plans */
        $plans = config('billing.plans', []);

        return $plans;
    }

    public function applyPlan(Hospital $hospital, string $plan): Hospital
    {
        $hospital->forceFill([
            'plan' => $plan,
            'features' => $this->featuresForPlan($plan),
        ])->save();

        return $hospital->refresh();
    }

    public function startTrial(Hospital $hospital, string $plan = 'basic'): Hospital
    {
        $this->applyPlan($hospital, $plan);

        $hospital->forceFill([
            'subscription_status' => SubscriptionStatus::Trialing,
            'trial_ends_at' => now()->addDays((int) config('billing.trial_days')),
        ])->save();

        return $hospital->refresh();
    }

    public function setStatus(Hospital $hospital, SubscriptionStatus $status, ?Carbon $trialEndsAt = null): Hospital
    {
        $hospital->forceFill([
            'subscription_status' => $status,
            'trial_ends_at' => $trialEndsAt,
        ])->save();

        return $hospital->refresh();
    }
}
