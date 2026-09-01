<?php

namespace App\Console\Commands;

use App\Models\Hospital;
use App\Models\Patient;
use App\Models\Procedure;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillPatients extends Command
{
    protected $signature = 'xacare:backfill-patients {--hospital=hnsc}';

    protected $description = 'Assign default hospital to existing rows and create Patient records from procedures.patient_name';

    public function handle(): int
    {
        $hospital = Hospital::where('slug', $this->option('hospital'))->first();
        if (! $hospital) {
            $this->error("Hospital '{$this->option('hospital')}' not found. Run the HospitalSeeder first.");
            return self::FAILURE;
        }

        // 1) Tenant backfill for users and procedures without hospital_id.
        User::whereNull('hospital_id')->where('is_super_admin', false)->update(['hospital_id' => $hospital->id]);
        Procedure::withoutGlobalScopes()->whereNull('hospital_id')->update(['hospital_id' => $hospital->id]);

        // 2) One Patient per distinct patient_name; link procedures.
        $names = Procedure::withoutGlobalScopes()
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

                Procedure::withoutGlobalScopes()
                    ->whereNull('patient_id')
                    ->where('patient_name', $name)
                    ->update(['patient_id' => $patient->id]);

                $created++;
            }
        });

        $this->info("Backfill done. Patients created: {$created}.");
        return self::SUCCESS;
    }
}
