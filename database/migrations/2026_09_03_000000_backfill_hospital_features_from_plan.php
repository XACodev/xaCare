<?php

use App\Models\Hospital;
use App\Services\HospitalPlanService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $planService = app(HospitalPlanService::class);

        Hospital::query()->chunkById(100, function ($hospitals) use ($planService) {
            foreach ($hospitals as $hospital) {
                $current = $hospital->features ?? [];
                $planFeatures = $planService->featuresForPlan($hospital->plan ?? 'basic');

                $merged = array_values(array_unique([
                    ...$current,
                    ...$planFeatures,
                ]));

                if ($merged !== $current) {
                    $hospital->forceFill(['features' => $merged])->save();
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Los datos backfill no se revierten de forma automática porque
        // no se puede distinguir qué features eran custom vs. derivadas del plan.
    }
};
