<?php

namespace App\Services;

use App\Models\RateModifier;
use App\Models\RoleRate;
use App\Models\SurgicalRole;
use App\Models\User;
use App\Support\TimeHelper;

class RateResolutionService
{
    /**
     * @param  int[]  $manualToggleIds  Ids de RateModifier (trigger_type=manual_toggle) marcados por el usuario.
     * @return array{amount: float, snapshot: array<string, mixed>}
     */
    public function resolve(
        SurgicalRole $role,
        ?User $user,
        ?string $procedureType,
        string $procedureDate,
        string $startTimeHHMM,
        int $durationMinutes,
        bool $isCourtesy,
        array $manualToggleIds = [],
    ): array {
        if ($isCourtesy) {
            return [
                'amount' => 0.0,
                'snapshot' => [
                    'version' => 1,
                    'rule' => 'courtesy',
                    'amount' => 0.0,
                    'role_rate_id' => null,
                    'is_courtesy' => true,
                ],
            ];
        }

        $roleRate = $this->resolveRoleRate($role, $user, $procedureType);

        if (! $roleRate) {
            return [
                'amount' => 0.0,
                'snapshot' => [
                    'version' => 1,
                    'rule' => null,
                    'amount' => 0.0,
                    'role_rate_id' => null,
                    'is_courtesy' => false,
                ],
            ];
        }

        $amount = (float) $roleRate->base_rate;
        $rule = 'base_rate';
        $candidates = ['base_rate' => $amount];
        $evaluated = [];

        foreach ($roleRate->modifiers()->where('active', true)->orderBy('sort_order')->get() as $modifier) {
            $applies = $this->modifierApplies($modifier, $procedureDate, $startTimeHHMM, $durationMinutes, $manualToggleIds);

            $evaluated[] = [
                'id' => $modifier->id,
                'name' => $modifier->name,
                'trigger_type' => $modifier->trigger_type,
                'applies' => $applies,
                'amount' => (float) $modifier->amount,
            ];

            if (! $applies) {
                continue;
            }

            $candidateAmount = $modifier->rate_type === RateModifier::RATE_MULTIPLIER
                ? $amount * (float) $modifier->amount
                : (float) $modifier->amount;

            $candidates[$modifier->name] = $candidateAmount;

            if ($candidateAmount > $amount) {
                $amount = $candidateAmount;
                $rule = $modifier->name;
            }
        }

        return [
            'amount' => $amount,
            'snapshot' => [
                'version' => 1,
                'rule' => $rule,
                'amount' => $amount,
                'role_rate_id' => $roleRate->id,
                'base_rate' => (float) $roleRate->base_rate,
                'candidates' => $candidates,
                'modifiers_evaluated' => $evaluated,
                'is_courtesy' => false,
                'duration_minutes' => $durationMinutes,
                'start_time' => $startTimeHHMM,
            ],
        ];
    }

    private function resolveRoleRate(SurgicalRole $role, ?User $user, ?string $procedureType): ?RoleRate
    {
        $query = fn () => RoleRate::query()
            ->where('surgical_role_id', $role->id)
            ->where('active', true);

        if ($user && $procedureType) {
            $found = $query()->where('user_id', $user->id)->where('procedure_type', $procedureType)->first();
            if ($found) {
                return $found;
            }
        }

        if ($user) {
            $found = $query()->where('user_id', $user->id)->whereNull('procedure_type')->first();
            if ($found) {
                return $found;
            }
        }

        if ($procedureType) {
            $found = $query()->whereNull('user_id')->where('procedure_type', $procedureType)->first();
            if ($found) {
                return $found;
            }
        }

        return $query()->whereNull('user_id')->whereNull('procedure_type')->first();
    }

    private function modifierApplies(
        RateModifier $modifier,
        string $procedureDate,
        string $startTimeHHMM,
        int $durationMinutes,
        array $manualToggleIds,
    ): bool {
        $config = $modifier->trigger_config ?? [];

        return match ($modifier->trigger_type) {
            RateModifier::TRIGGER_TIME_WINDOW => TimeHelper::isWithinTimeWindow(
                $startTimeHHMM,
                (string) ($config['start'] ?? '00:00'),
                (string) ($config['end'] ?? '23:59'),
            ),
            RateModifier::TRIGGER_DURATION_GTE => $durationMinutes >= (int) ($config['minutes'] ?? PHP_INT_MAX),
            RateModifier::TRIGGER_MANUAL_TOGGLE => in_array($modifier->id, $manualToggleIds, true),
            default => false,
        };
    }
}
