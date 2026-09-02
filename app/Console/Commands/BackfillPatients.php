<?php

namespace App\Console\Commands;

use App\Models\Hospital;
use App\Models\Patient;
use App\Models\SurgicalCase;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillPatients extends Command
{
    protected $signature = 'xacare:backfill-patients {--hospital= : Slug del hospital a procesar; si se omite, itera todos los hospitales, uno a la vez}';

    protected $description = 'Assign default hospital to existing rows and create Patient records from procedures.patient_name';

    public function handle(): int
    {
        $hospitalSlug = $this->option('hospital');

        if ($hospitalSlug) {
            $hospital = Hospital::where('slug', $hospitalSlug)->first();
            if (! $hospital) {
                $this->error("Hospital '{$hospitalSlug}' not found. Run the HospitalSeeder first.");

                return self::FAILURE;
            }
            $hospitals = collect([$hospital]);
        } else {
            $hospitals = Hospital::all();
            if ($hospitals->isEmpty()) {
                $this->error('No hospitals found. Run the HospitalSeeder first.');

                return self::FAILURE;
            }
        }

        $totalCreated = 0;
        $assignOrphanRows = filled($hospitalSlug);

        foreach ($hospitals as $hospital) {
            $totalCreated += $this->backfillHospital($hospital, $assignOrphanRows);
        }

        $this->info("Backfill done. Patients created: {$totalCreated}.");

        return self::SUCCESS;
    }

    /**
     * Procesa un solo hospital: todas las queries (SurgicalCase/Patient/update de matching)
     * van explícitamente filtradas por hospital_id para no cruzar datos entre hospitales.
     * Este comando corre sin usuario autenticado, así que no puede confiar en el
     * TenantScope automático (que solo filtra cuando hay un Auth::user() con hospital_id).
     */
    private function backfillHospital(Hospital $hospital, bool $assignOrphanRows): int
    {
        // 1) Tenant backfill for users and procedures without hospital_id. Solo cuando se
        // invoca con --hospital (corrida legacy de un único hospital "default"); si se
        // iteran todos, asignar huérfanos en cada vuelta cruzaría o se los quedaría el primero.
        if ($assignOrphanRows) {
            User::whereNull('hospital_id')->where('is_super_admin', false)->update(['hospital_id' => $hospital->id]);
            SurgicalCase::withoutGlobalScopes()
                ->whereNull('hospital_id')
                ->update(['hospital_id' => $hospital->id]);
        }

        // 2) One Patient per distinct patient_name (dentro de ESTE hospital); link procedures
        // (también scopeadas a este hospital).
        $names = SurgicalCase::withoutGlobalScopes()
            ->where('hospital_id', $hospital->id)
            ->whereNull('patient_id')
            ->whereNotNull('patient_name')
            ->distinct()
            ->pluck('patient_name');

        $created = 0;
        DB::transaction(function () use ($names, $hospital, &$created) {
            foreach ($names as $name) {
                $parts = preg_split('/\s+/', trim((string) $name));
                $patient = Patient::create([
                    'hospital_id' => $hospital->id,
                    'primer_nombre' => $parts[0] ?? $name,
                    'primer_apellido' => $parts[count($parts) - 1] ?? '—',
                ]);

                SurgicalCase::withoutGlobalScopes()
                    ->where('hospital_id', $hospital->id)
                    ->whereNull('patient_id')
                    ->where('patient_name', $name)
                    ->update(['patient_id' => $patient->id]);

                $created++;
            }
        });

        return $created;
    }
}
