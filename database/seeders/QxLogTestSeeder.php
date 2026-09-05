<?php

namespace Database\Seeders;

use App\Models\Hospital;
use App\Models\PricingSetting;
use App\Models\User;
use App\Modules\QxLog\Models\PayoutBatch;
use App\Modules\QxLog\Models\PayoutItem;
use App\Modules\QxLog\Models\SurgicalAssignment;
use App\Modules\QxLog\Models\SurgicalCase;
use App\Modules\QxLog\Models\SurgicalRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class QxLogTestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // ======================
        // ADMINS
        // ======================
        $super = User::firstOrCreate(
            ['username' => 'thealejandro'],
            [
                'name' => 'Alejandro',
                'email' => 'thealejandro7w7@gmail.com',
                'phone' => '30683865',
                'role' => 'admin',
                'is_platform_admin' => true,
                'use_pay_scheme' => false,
                'password' => Hash::make('9977'),
            ]
        );

        $hospital = Hospital::firstOrCreate(
            ['slug' => 'hnsc'],
            ['name' => 'Centro Médico y Hospital Nuestra Señora del Carmen', 'plan' => 'basic', 'features' => config('billing.plans.basic.features', []), 'is_active' => true],
        );

        $admin = User::firstOrCreate(
            ['username' => 'hospital'],
            [
                'name' => 'Administrador Hospital',
                'email' => 'hospitalcoban@gmail.com',
                'phone' => '77903000',
                'role' => 'admin',
                'is_platform_admin' => false,
                'use_pay_scheme' => false,
                'hospital_id' => $hospital->id,
                'password' => Hash::make('1981'),
            ]
        );

        // ======================
        // INSTRUMENTISTAS
        // ======================
        $inst1 = User::firstOrCreate(
            ['email' => 'ana@qxlog.test'],
            [
                'name' => 'Ana Instrumentista',
                'username' => 'ana',
                'password' => Hash::make('123456'),
                'role' => 'instrumentist',
                'hospital_id' => $hospital->id,
                'use_pay_scheme' => true,
            ]
        );

        $inst2 = User::firstOrCreate(
            ['email' => 'carlos@qxlog.test'],
            [
                'name' => 'Carlos Instrumentista',
                'username' => 'carlos',
                'password' => Hash::make('123456'),
                'role' => 'instrumentist',
                'hospital_id' => $hospital->id,
                'use_pay_scheme' => false,
            ]
        );

        $inst3 = User::firstOrCreate(
            ['email' => 'sofia@qxlog.test'],
            [
                'name' => 'Sofía Instrumentista',
                'username' => 'sofia',
                'password' => Hash::make('123456'),
                'role' => 'instrumentist',
                'hospital_id' => $hospital->id,
                'use_pay_scheme' => true,
            ]
        );

        // ======================
        // MÉDICOS
        // ======================
        $doc1 = User::firstOrCreate(
            ['email' => 'jperez@qxlog.test'],
            [
                'name' => 'Dr. Juan Pérez',
                'username' => 'juan',
                'password' => Hash::make('123456'),
                'role' => 'doctor',
                'hospital_id' => $hospital->id,
            ]
        );

        $doc2 = User::firstOrCreate(
            ['email' => 'mlopez@qxlog.test'],
            [
                'name' => 'Dra. María López',
                'username' => 'maria',
                'password' => Hash::make('123456'),
                'role' => 'doctor',
                'hospital_id' => $hospital->id,
            ]
        );

        $doc3 = User::firstOrCreate(
            ['email' => 'crodero@qxlog.test'],
            [
                'name' => 'Dr. Carlos Rodero',
                'username' => 'crodero',
                'password' => Hash::make('123456'),
                'role' => 'doctor',
                'hospital_id' => $hospital->id,
            ]
        );

        // ======================
        // CIRCULANTES
        // ======================
        $circ1 = User::firstOrCreate(
            ['email' => 'pedro@qxlog.test'],
            [
                'name' => 'Pedro Circulante',
                'username' => 'pedro',
                'password' => Hash::make('123456'),
                'role' => 'circulating',
                'hospital_id' => $hospital->id,
            ]
        );

        $circ2 = User::firstOrCreate(
            ['email' => 'lucia@qxlog.test'],
            [
                'name' => 'Lucia Circulante',
                'username' => 'lucia',
                'password' => Hash::make('123456'),
                'role' => 'circulating',
                'hospital_id' => $hospital->id,
            ]
        );

        foreach ([$super, $admin, $inst1, $inst2, $inst3, $doc1, $doc2, $doc3, $circ1, $circ2] as $user) {
            $this->assignSpatieRole($user);
        }

        // ======================
        // PRICING SETTINGS (deben existir antes)
        // ======================
        $settings = PricingSetting::withoutGlobalScopes()->firstOrCreate(['id' => 1], [
            'hospital_id' => $hospital->id,
            'default_rate' => 200,
            'video_rate' => 300,
            'night_rate' => 350,
            'long_case_rate' => 350,
            'long_case_threshold_minutes' => 120,
            'night_start' => '22:00',
            'night_end' => '06:00',
        ]);

        // ======================
        // ROLES QUIRÚRGICOS (Instrumentista/Cirujano/Circulante)
        // ======================
        $roles = [];
        foreach (['Instrumentista', 'Cirujano', 'Circulante'] as $roleName) {
            $roles[$roleName] = SurgicalRole::withoutGlobalScopes()->firstOrCreate(
                ['hospital_id' => $hospital->id, 'slug' => Str::slug($roleName)],
                ['name' => $roleName, 'is_payable' => true, 'active' => true, 'sort_order' => 0],
            );
        }

        // ======================
        // PROCEDIMIENTOS
        // ======================
        $instrumentists = [$inst1, $inst2, $inst3];
        $doctors = [$doc1, $doc2, $doc3];
        $circulators = [$circ1, $circ2];

        $procedureTypes = [
            'Cesárea',
            'Apendicectomía',
            'Histerectomía',
            'Colecistectomía',
            'Hernioplastia',
            'Amigdalectomía',
            'Rinoplastia',
            'Artroscopia',
        ];

        // Instanciar el servicio real para obtener los mismos datos de guardado
        $pricingService = app(\App\Modules\QxLog\Services\PricingService::class);

        // Asignaciones de instrumentista creadas, para poder liquidar algunas más abajo.
        $instrumentistAssignments = collect();

        for ($i = 1; $i <= 50; $i++) {
            $inst = fake()->randomElement($instrumentists);
            $doc = fake()->randomElement($doctors);
            $circ = fake()->randomElement($circulators);

            $startHour = rand(6, 22);
            $durationMinutes = fake()->randomElement([45, 60, 90, 120, 150, 180]);

            $startTime = sprintf('%02d:00', $startHour);
            $endHour = $startHour + floor($durationMinutes / 60);
            $endMin = $durationMinutes % 60;

            // Fix overflow over 24 hours
            if ($endHour >= 24) {
                $endHour = $endHour % 24;
            }

            $endTime = sprintf('%02d:%02d', $endHour, $endMin);

            $date = now()->subDays(rand(1, 60))->toDateString();
            $isVideosurgery = fake()->boolean(30);
            $isCourtesy = fake()->boolean(10);

            // Calcular monto y snapshot usando el servicio real
            $pricingResult = $pricingService->calculate(
                $inst,
                $isVideosurgery,
                $isCourtesy,
                $durationMinutes,
                $startTime,
                $endTime
            );

            $case = SurgicalCase::create([
                'hospital_id' => $hospital->id,
                'procedure_date' => $date,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'duration_minutes' => $durationMinutes,
                'patient_name' => fake()->name(),
                'procedure_type' => fake()->randomElement($procedureTypes),
                'is_videosurgery' => $isVideosurgery,
                'calculated_amount' => $pricingResult['amount'],
                'pricing_snapshot' => $pricingResult['snapshot'],
                'status' => 'pending',
            ]);

            // Participantes del caso, migrados como SurgicalAssignment en vez de las columnas
            // legacy instrumentist_id/doctor_id/circulating_id (retiradas de surgical_cases).
            $instrumentistAssignment = SurgicalAssignment::withoutGlobalScopes()->create([
                'hospital_id' => $hospital->id,
                'surgical_case_id' => $case->id,
                'surgical_role_id' => $roles['Instrumentista']->id,
                'user_id' => $inst->id,
                'calculated_amount' => $pricingResult['amount'],
                'pricing_snapshot' => $pricingResult['snapshot'],
                'is_courtesy' => $isCourtesy,
                'status' => 'pending',
            ]);
            $instrumentistAssignments->push($instrumentistAssignment);

            SurgicalAssignment::withoutGlobalScopes()->create([
                'hospital_id' => $hospital->id,
                'surgical_case_id' => $case->id,
                'surgical_role_id' => $roles['Cirujano']->id,
                'user_id' => $doc->id,
                'calculated_amount' => 0,
                'is_courtesy' => false,
                'status' => 'paid',
            ]);

            SurgicalAssignment::withoutGlobalScopes()->create([
                'hospital_id' => $hospital->id,
                'surgical_case_id' => $case->id,
                'surgical_role_id' => $roles['Circulante']->id,
                'user_id' => $circ->id,
                'calculated_amount' => 0,
                'is_courtesy' => false,
                'status' => 'paid',
            ]);
        }

        // ======================
        // PAGOS YA REALIZADOS
        // ======================

        // Pago a Ana (inst1)
        $batch1 = PayoutBatch::create([
            'hospital_id' => $hospital->id,
            'payee_id' => $inst1->id,
            'paid_by_id' => $admin->id,
            'paid_at' => now()->subDays(5),
            'total_amount' => 0,
            'status' => 'active',
        ]);

        $paidAssignments1 = $instrumentistAssignments
            ->filter(fn ($a) => $a->user_id === $inst1->id)
            ->take(5);

        $total1 = 0;
        foreach ($paidAssignments1 as $a) {
            $case = $a->surgicalCase;

            $item = PayoutItem::create([
                'hospital_id' => $hospital->id,
                'payout_batch_id' => $batch1->id,
                'surgical_assignment_id' => $a->id,
                'amount' => $a->calculated_amount,
                'snapshot' => [
                    'procedure_id' => $case->id,
                    'patient_name' => $case->patient_name,
                    'procedure_type' => $case->procedure_type,
                    'procedure_date' => $case->procedure_date->toDateString(),
                    'calculated_amount' => $a->calculated_amount,
                    'pricing_snapshot' => $a->pricing_snapshot,
                ],
            ]);

            $a->update(['status' => 'paid', 'payout_item_id' => $item->id]);
            $total1 += $a->calculated_amount;
        }

        $batch1->update(['total_amount' => $total1]);

        // Pago a Sofía (inst3)
        $batch2 = PayoutBatch::create([
            'hospital_id' => $hospital->id,
            'payee_id' => $inst3->id,
            'paid_by_id' => $super->id,
            'paid_at' => now()->subDays(2),
            'total_amount' => 0,
            'status' => 'active',
        ]);

        $paidAssignments2 = $instrumentistAssignments
            ->filter(fn ($a) => $a->user_id === $inst3->id)
            ->take(3);

        $total2 = 0;
        foreach ($paidAssignments2 as $a) {
            $case = $a->surgicalCase;

            $item = PayoutItem::create([
                'hospital_id' => $hospital->id,
                'payout_batch_id' => $batch2->id,
                'surgical_assignment_id' => $a->id,
                'amount' => $a->calculated_amount,
                'snapshot' => [
                    'procedure_id' => $case->id,
                    'patient_name' => $case->patient_name,
                    'procedure_type' => $case->procedure_type,
                    'procedure_date' => $case->procedure_date->toDateString(),
                    'calculated_amount' => $a->calculated_amount,
                    'pricing_snapshot' => $a->pricing_snapshot,
                ],
            ]);

            $a->update(['status' => 'paid', 'payout_item_id' => $item->id]);
            $total2 += $a->calculated_amount;
        }

        $batch2->update(['total_amount' => $total2]);
    }

    private function assignSpatieRole(User $user): void
    {
        $roleName = $user->role;

        if (! is_string($roleName) || $roleName === '') {
            return;
        }

        $teamId = in_array($roleName, Hospital::CORE_ROLES, true) ? null : $user->hospital_id;

        Role::firstOrCreate([
            'name' => $roleName,
            'guard_name' => 'web',
            'team_id' => $teamId,
        ]);

        $user->assignRole($roleName);
    }
}
