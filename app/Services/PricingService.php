<?php

namespace App\Services;

use App\Models\PricingSetting;
use App\Models\User;
use App\Support\TimeHelper;

class PricingService
{
    /**
     * Calcula el pago de un procedimiento.
     *
     * @param  User  $user
     * @return array{amount: float, snapshot: array<string, mixed>}
     */
    public function calculate(
        User $instrumentist,
        bool $isVideosurgery,
        bool $isCourtesy,
        int $durationMinutes,
        string $startTimeHHMM, // HH:MM
        string $endTimeHHMM, // HH:MM
    ): array {

        $usePayScheme = (bool) $instrumentist->use_pay_scheme;

        $settings = PricingSetting::firstOrCreate([
            'id' => 1,
        ]);

        $base = (float) $settings->default_rate;

        $amount = $base;

        $rule = 'default_rate';

        // Settings usados (auditoria)
        $rates = [
            'default_rate' => (float) $base,
            'video_rate' => (float) $settings->video_rate,
            'night_rate' => (float) $settings->night_rate,
            'long_case_rate' => (float) $settings->long_case_rate,
            'courtesy_rate' => (float) 0.00,
        ];

        $thresholds = [
            'long_case_threshold_minutes' => (int) $settings->long_case_threshold_minutes,
            'night_start' => (string) $settings->night_start,
            'night_end' => (string) $settings->night_end,
        ];

        $candidates = [
            'default_rate' => $base,
        ];

        // Regla 1: Video
        if ($isVideosurgery) {
            $candidates['video_rate'] = (float) $settings->video_rate;
        }

        // Regla 2: largo
        $isLong = $durationMinutes >= (int) $settings->long_case_threshold_minutes;
        if ($isLong) {
            $candidates['long_case_rate'] = (float) $settings->long_case_rate;
        }

        // Regla 3: madrugada
        $isNight = TimeHelper::isWithinTimeWindow(
            $startTimeHHMM,
            (string) $settings->night_start,
            (string) $settings->night_end
        );
        if ($isNight) {
            $candidates['night_rate'] = (float) $settings->night_rate;
        }

        // Regla 4: cortesía
        if ($isCourtesy) {
            $candidates['courtesy_rate'] = (float) 0.00;
        }

        // Determinar monto y regla a aplicar
        if ($usePayScheme) {
            // Escoger el candidato con el mayor monto
            if ($isCourtesy) {
                $amount = 0.00;
                $rule = 'courtesy_rate';
            } else {
                foreach ($candidates as $candidateRule => $candidateAmount) {
                    if ($candidateAmount > $amount) {
                        $amount = $candidateAmount;
                        $rule = $candidateRule;
                    }
                }
            }
        } else {
            // Si no usa esquema de pagos, siempre es el monto base
            $amount = $isCourtesy ? (float) 0.00 : $base;
            $rule = $isCourtesy ? 'courtesy_rate' : 'default_rate';
        }

        $snapshot = [
            'version' => 4,
            'rule' => $rule,
            'amount' => $amount,
            'use_pay_scheme' => $usePayScheme,
            'rates' => $rates,
            'thresholds' => $thresholds,
            'is_videosurgery' => $isVideosurgery,
            'is_courtesy' => $isCourtesy,
            'duration_minutes' => $durationMinutes,
            'start_time' => $startTimeHHMM,
            'end_time' => $endTimeHHMM,
            'candidates' => $candidates,
            'flags' => [
                'is_night' => $isNight,
                'is_long' => $isLong,
                'is_courtesy' => $isCourtesy,
            ],
            'user_data' => [
                'name' => $instrumentist->name,
                'phone' => $instrumentist->phone,
                'role' => $instrumentist->role,
            ],
        ];

        // Default (especial pero no cae en reglas)
        return compact('amount', 'snapshot');
    }

    // BASURA
    private function calculateBeforeScheme(
        User $instrumentist,
        bool $isVideosurgery,
        int $durationMinutes,
        string $startTimeHHMM, // HH:MM
        string $endTimeHHMM, // HH:MM
    ): array {
        // Todos 200
        $amount = (float) config('qxlog.default_rate', 200.00);

        $snapshot = [
            'version' => config('qxlog.version'),
            'rule' => 'default_rate',
            'default_rate' => $amount,
            'use_pay_scheme' => (bool) ($instrumentist->use_pay_scheme ?? false),
            'is_videosurgery' => $isVideosurgery,
            'duration_minutes' => $durationMinutes,
            'start_time' => $startTimeHHMM,
            'end_time' => $endTimeHHMM,
        ];

        return compact('amount', 'snapshot');

        // Regla 1: Video
        if ($isVideosurgery) {
            $amount = (float) config('qxlog.special.rates.video', 300.00);
            $snapshot['rule'] = 'video';
            $snapshot['video_rate'] = $amount;
        }
    }
}
