<?php

namespace App\Console\Commands;

use App\Models\Hospital;
use App\Models\User;
use Illuminate\Console\Command;

class BackfillUsers extends Command
{
    protected $signature = 'xacare:backfill-users {--hospital= : Slug del hospital a asignar; si se omite, itera todos los hospitales}';

    protected $description = 'Assign hospital_id to non-platform users that are missing it.';

    public function handle(): int
    {
        $hospitalSlug = $this->option('hospital');

        if ($hospitalSlug) {
            $hospital = Hospital::where('slug', $hospitalSlug)->first();
            if (! $hospital) {
                $this->error("Hospital '{$hospitalSlug}' not found.");

                return self::FAILURE;
            }

            $updated = $this->backfillHospital($hospital);
            $this->info("Backfill done. Users updated: {$updated}.");

            return self::SUCCESS;
        }

        $hospitals = Hospital::all();
        if ($hospitals->isEmpty()) {
            $this->error('No hospitals found.');

            return self::FAILURE;
        }

        $totalUpdated = 0;
        foreach ($hospitals as $hospital) {
            $totalUpdated += $this->backfillHospital($hospital);
        }

        $this->info("Backfill done. Users updated: {$totalUpdated}.");

        return self::SUCCESS;
    }

    private function backfillHospital(Hospital $hospital): int
    {
        // Solo usuarios no-plataforma sin hospital. El comando corre sin usuario
        // autenticado, así que no confiamos en TenantScope.
        $updated = User::withoutGlobalScopes()
            ->whereNull('hospital_id')
            ->where('is_platform_admin', false)
            ->update(['hospital_id' => $hospital->id]);

        $this->info("[{$hospital->slug}] users updated: {$updated}");

        return $updated;
    }
}
