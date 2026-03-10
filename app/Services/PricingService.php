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

        //Settings usados (auditoria)
        $rates = [
            'default_rate' => (float) $base,
            'video_rate' => (float) $settings->video_rate,
            'night_rate' => (float) $settings->night_rate,
            'long_case_rate' => (float) $settings->long_case_rate,
        ];

        $thresholds = [
            'long_case_threshold_minutes' => (int) $settings->long_case_threshold_minutes,
            'night_start' => (string) $settings->night_start,
            'night_end' => (string) $settings->night_end,
        ];

        $candidates = [
            'default_rate' => $base
        ];

        // Si NO es especial: siempre base
        if (!$usePayScheme) {
            $amount = $base;
            $rule = 'default_rate';
        }

        // Regla 1: Video
        if ($isVideosurgery && $usePayScheme) {
            $amount = (float) $settings->video_rate;
            $rule = 'video_rate';
            $candidates['video_rate'] = $amount;
        }

        // Regla 2: largo
        $isLong = $durationMinutes >= (int) $settings->long_case_threshold_minutes;

        if ($isLong && $usePayScheme) {
            $amount = (float) $settings->long_case_rate;
            $rule = 'long_case_rate';
            $candidates['long_case_rate'] = $amount;
        }

        // Regla 3: madrugada
        $isNight = TimeHelper::isWithinTimeWindow(
            $startTimeHHMM,
            (string) $settings->night_start,
            (string) $settings->night_end
        );

        if ($isNight && $usePayScheme) {
            $amount = (float) $settings->night_rate;
            $rule = 'night_rate';
            $candidates['night_rate'] = $amount;
        }

        foreach ($candidates as $candidateRule => $candidateAmount) {
            if ($candidateAmount > $amount) {
                $amount = $candidateAmount;
                $rule = $candidateRule;
            }
        }

        $snapshot = [
            'version' => 3,
            'rule' => $rule,
            'amount' => $amount,
            'use_pay_scheme' => $usePayScheme,
            'rates' => $rates,
            'thresholds' => $thresholds,
            'is_videosurgery' => $isVideosurgery,
            'duration_minutes' => $durationMinutes,
            'start_time' => $startTimeHHMM,
            'end_time' => $endTimeHHMM,
            'candidates' => $candidates,
            'flags' => [
                'is_night' => $isNight,
                'is_long' => $isLong,
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


    private function calculateBeforeScheme(
        User $instrumentist,
        bool $isVideosurgery,
        int $durationMinutes,
        string $startTimeHHMM, // HH:MM
        string $endTimeHHMM, // HH:MM
    ): array {
        //Todos 200
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

        //Regla 1: Video
        if ($isVideosurgery) {
            $amount = (float) config('qxlog.special.rates.video', 300.00);
            $snapshot['rule'] = 'video';
            $snapshot['video_rate'] = $amount;
        }
    }
}