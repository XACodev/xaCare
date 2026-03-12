<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Procedure;
use App\Models\PayoutBatch;
use App\Models\PayoutItem;
use App\Models\PricingSetting;
use Illuminate\Support\Facades\Hash;

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
                'is_super_admin' => true,
                'use_pay_scheme' => false,
                'password' => Hash::make('9977'),
            ]
        );

        $admin = User::firstOrCreate(
            ['username' => 'hospital'],
            [
                'name' => 'Administrador Hospital',
                'email' => 'hospitalcoban@gmail.com',
                'phone' => '77903000',
                'role' => 'admin',
                'is_super_admin' => false,
                'use_pay_scheme' => false,
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
            ]
        );

        $doc2 = User::firstOrCreate(
            ['email' => 'mlopez@qxlog.test'],
            [
                'name' => 'Dra. María López',
                'username' => 'maria',
                'password' => Hash::make('123456'),
                'role' => 'doctor',
            ]
        );

        $doc3 = User::firstOrCreate(
            ['email' => 'crodero@qxlog.test'],
            [
                'name' => 'Dr. Carlos Rodero',
                'username' => 'crodero',
                'password' => Hash::make('123456'),
                'role' => 'doctor',
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
            ]
        );

        $circ2 = User::firstOrCreate(
            ['email' => 'lucia@qxlog.test'],
            [
                'name' => 'Lucia Circulante',
                'username' => 'lucia',
                'password' => Hash::make('123456'),
                'role' => 'circulating',
            ]
        );

        // ======================
        // PRICING SETTINGS (deben existir antes)
        // ======================
        $settings = PricingSetting::firstOrCreate(['id' => 1], [
            'default_rate' => 200,
            'video_rate' => 300,
            'night_rate' => 350,
            'long_case_rate' => 350,
            'long_case_threshold_minutes' => 120,
            'night_start' => '22:00',
            'night_end' => '06:00',
        ]);

        // ======================
        // PROCEDIMIENTOS
        // ======================
        $procedures = [];
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
        $pricingService = app(\App\Services\PricingService::class);

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

            $procedures[] = Procedure::create([
                'procedure_date' => $date,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'duration_minutes' => $durationMinutes,
                'patient_name' => fake()->name(),
                'procedure_type' => fake()->randomElement($procedureTypes),
                'is_videosurgery' => $isVideosurgery,
                'instrumentist_id' => $inst->id,
                'instrumentist_name' => $inst->name,

                'doctor_id' => $doc->id,
                'doctor_name' => $doc->name,

                'circulating_id' => $circ->id,
                'circulating_name' => $circ->name,

                'calculated_amount' => $pricingResult['amount'],
                'pricing_snapshot' => $pricingResult['snapshot'],

                'status' => 'pending',
            ]);
        }

        // ======================
        // PAGOS YA REALIZADOS
        // ======================

        // Pago a Ana (inst1)
        $batch1 = PayoutBatch::create([
            'instrumentist_id' => $inst1->id,
            'paid_by_id' => $admin->id,
            'paid_at' => now()->subDays(5),
            'total_amount' => 0,
            'status' => 'active',
        ]);

        $paidProcedures1 = collect($procedures)
            ->filter(fn($p) => $p->instrumentist_id === $inst1->id)
            ->take(5);

        $total1 = 0;
        foreach ($paidProcedures1 as $p) {
            PayoutItem::create([
                'payout_batch_id' => $batch1->id,
                'procedure_id' => $p->id,
                'amount' => $p->calculated_amount,
                'snapshot' => [
                    'procedure_id' => $p->id,
                    'patient_name' => $p->patient_name,
                    'procedure_type' => $p->procedure_type,
                    'procedure_date' => $p->procedure_date->toDateString(),
                    'calculated_amount' => $p->calculated_amount,
                    'pricing_snapshot' => $p->pricing_snapshot,
                ],
            ]);

            $p->update([
                'status' => 'paid',
                'paid_at' => $batch1->paid_at,
                'payout_batch_id' => $batch1->id,
            ]);
            $total1 += $p->calculated_amount;
        }

        $batch1->update(['total_amount' => $total1]);

        // Pago a Sofía (inst3)
        $batch2 = PayoutBatch::create([
            'instrumentist_id' => $inst3->id,
            'paid_by_id' => $super->id,
            'paid_at' => now()->subDays(2),
            'total_amount' => 0,
            'status' => 'active',
        ]);

        $paidProcedures2 = collect($procedures)
            ->filter(fn($p) => $p->instrumentist_id === $inst3->id)
            ->take(3);

        $total2 = 0;
        foreach ($paidProcedures2 as $p) {
            PayoutItem::create([
                'payout_batch_id' => $batch2->id,
                'procedure_id' => $p->id,
                'amount' => $p->calculated_amount,
                'snapshot' => [
                    'procedure_id' => $p->id,
                    'patient_name' => $p->patient_name,
                    'procedure_type' => $p->procedure_type,
                    'procedure_date' => $p->procedure_date->toDateString(),
                    'calculated_amount' => $p->calculated_amount,
                    'pricing_snapshot' => $p->pricing_snapshot,
                ],
            ]);

            $p->update([
                'status' => 'paid',
                'paid_at' => $batch2->paid_at,
                'payout_batch_id' => $batch2->id,
            ]);
            $total2 += $p->calculated_amount;
        }

        $batch2->update(['total_amount' => $total2]);

    }
}
