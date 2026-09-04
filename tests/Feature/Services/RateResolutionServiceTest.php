<?php

// tests/Feature/Services/RateResolutionServiceTest.php
use App\Models\Hospital;
use App\Models\User;
use App\Modules\QxLog\Models\RateModifier;
use App\Modules\QxLog\Models\RoleRate;
use App\Modules\QxLog\Models\SurgicalRole;
use App\Modules\QxLog\Services\RateResolutionService;

beforeEach(function () {
    $this->hospital = Hospital::factory()->create();
    $this->actingAs(User::factory()->create(['hospital_id' => $this->hospital->id]));
    $this->role = SurgicalRole::factory()->for($this->hospital, 'hospital')->create(['name' => 'Cirujano']);
    $this->service = app(RateResolutionService::class);
});

test('resuelve la tarifa especifica de usuario+procedimiento antes que cualquier default', function () {
    $doctor = User::factory()->create(['hospital_id' => $this->hospital->id]);

    RoleRate::factory()->for($this->role, 'surgicalRole')->create(['base_rate' => 200]); // default hospital
    RoleRate::factory()->for($this->role, 'surgicalRole')->create(['user_id' => $doctor->id, 'base_rate' => 500]); // base del médico
    $specific = RoleRate::factory()->for($this->role, 'surgicalRole')
        ->create(['user_id' => $doctor->id, 'procedure_type' => 'Cesárea', 'base_rate' => 2000]);

    $result = $this->service->resolve(
        role: $this->role,
        user: $doctor,
        procedureType: 'Cesárea',
        procedureDate: '2026-09-02',
        startTimeHHMM: '10:00',
        durationMinutes: 60,
        isCourtesy: false,
    );

    expect($result['amount'])->toBe(2000.0)
        ->and($result['snapshot']['role_rate_id'])->toBe($specific->id)
        ->and($result['snapshot']['rule'])->toBe('base_rate');
});

test('cae al default del hospital cuando el medico no tiene tarifa propia', function () {
    RoleRate::factory()->for($this->role, 'surgicalRole')->create(['base_rate' => 200]);
    $otroMedico = User::factory()->create(['hospital_id' => $this->hospital->id]);

    $result = $this->service->resolve(
        role: $this->role,
        user: $otroMedico,
        procedureType: 'Apendicectomía',
        procedureDate: '2026-09-02',
        startTimeHHMM: '10:00',
        durationMinutes: 60,
        isCourtesy: false,
    );

    expect($result['amount'])->toBe(200.0)
        ->and($result['snapshot']['rule'])->toBe('base_rate');
});

test('sin ninguna tarifa aplicable devuelve monto 0 sin regla', function () {
    $medico = User::factory()->create(['hospital_id' => $this->hospital->id]);

    $result = $this->service->resolve(
        role: $this->role,
        user: $medico,
        procedureType: 'Algo raro',
        procedureDate: '2026-09-02',
        startTimeHHMM: '10:00',
        durationMinutes: 60,
        isCourtesy: false,
    );

    expect($result['amount'])->toBe(0.0)
        ->and($result['snapshot']['rule'])->toBeNull();
});

test('aplica el modificador automatico de mayor monto entre los que califican', function () {
    $rate = RoleRate::factory()->for($this->role, 'surgicalRole')->create(['base_rate' => 200]);
    RateModifier::factory()->for($rate, 'roleRate')->create([
        'name' => 'Nocturno', 'amount' => 350,
        'trigger_type' => 'time_window', 'trigger_config' => ['start' => '22:00', 'end' => '06:00'],
    ]);
    RateModifier::factory()->for($rate, 'roleRate')->durationGte(120)->create(['amount' => 300]);

    // 23:00, 180 min: califican nocturno (350) y caso largo (300) -> gana nocturno
    $result = $this->service->resolve(
        role: $this->role, user: null, procedureType: null,
        procedureDate: '2026-09-02', startTimeHHMM: '23:00', durationMinutes: 180, isCourtesy: false,
    );

    expect($result['amount'])->toBe(350.0)
        ->and($result['snapshot']['rule'])->toBe('Nocturno');
});

test('un modificador manual solo aplica si se marca explicitamente', function () {
    $rate = RoleRate::factory()->for($this->role, 'surgicalRole')->create(['base_rate' => 200]);
    $video = RateModifier::factory()->for($rate, 'roleRate')->manualToggle('Video')->create(['amount' => 300]);

    $sinMarcar = $this->service->resolve(
        role: $this->role, user: null, procedureType: null,
        procedureDate: '2026-09-02', startTimeHHMM: '10:00', durationMinutes: 60, isCourtesy: false,
    );
    $marcado = $this->service->resolve(
        role: $this->role, user: null, procedureType: null,
        procedureDate: '2026-09-02', startTimeHHMM: '10:00', durationMinutes: 60, isCourtesy: false,
        manualToggleIds: [$video->id],
    );

    expect($sinMarcar['amount'])->toBe(200.0)
        ->and($marcado['amount'])->toBe(300.0)
        ->and($marcado['snapshot']['rule'])->toBe('Video');
});

test('cortesia siempre fuerza el monto a cero sin importar otras reglas', function () {
    $rate = RoleRate::factory()->for($this->role, 'surgicalRole')->create(['base_rate' => 200]);
    RateModifier::factory()->for($rate, 'roleRate')->create(['amount' => 999]);

    $result = $this->service->resolve(
        role: $this->role, user: null, procedureType: null,
        procedureDate: '2026-09-02', startTimeHHMM: '23:30', durationMinutes: 300, isCourtesy: true,
    );

    expect($result['amount'])->toBe(0.0)
        ->and($result['snapshot']['rule'])->toBe('courtesy');
});

test('multiplicador se calcula sobre la tarifa base', function () {
    $rate = RoleRate::factory()->for($this->role, 'surgicalRole')->create(['base_rate' => 200]);
    RateModifier::factory()->for($rate, 'roleRate')->create([
        'name' => 'Doble',
        'rate_type' => RateModifier::RATE_MULTIPLIER,
        'amount' => 2,
        'trigger_type' => RateModifier::TRIGGER_MANUAL_TOGGLE,
        'trigger_config' => [],
    ]);

    $result = $this->service->resolve(
        role: $this->role, user: null, procedureType: null,
        procedureDate: '2026-09-02', startTimeHHMM: '10:00', durationMinutes: 60, isCourtesy: false,
        manualToggleIds: RateModifier::pluck('id')->all(),
    );

    expect($result['amount'])->toBe(400.0)
        ->and($result['snapshot']['rule'])->toBe('Doble')
        ->and($result['snapshot']['base_rate'])->toBe(200.0);
});

test('entre multiplicador y monto fijo gana el mayor resultado sobre la base', function () {
    $rate = RoleRate::factory()->for($this->role, 'surgicalRole')->create(['base_rate' => 200]);
    RateModifier::factory()->for($rate, 'roleRate')->create([
        'name' => 'Monto fijo alto',
        'rate_type' => RateModifier::RATE_FIXED_AMOUNT,
        'amount' => 500,
        'trigger_type' => RateModifier::TRIGGER_MANUAL_TOGGLE,
        'trigger_config' => [],
    ]);
    RateModifier::factory()->for($rate, 'roleRate')->create([
        'name' => 'Multiplicador bajo',
        'rate_type' => RateModifier::RATE_MULTIPLIER,
        'amount' => 1.5,
        'trigger_type' => RateModifier::TRIGGER_MANUAL_TOGGLE,
        'trigger_config' => [],
    ]);

    $result = $this->service->resolve(
        role: $this->role, user: null, procedureType: null,
        procedureDate: '2026-09-02', startTimeHHMM: '10:00', durationMinutes: 60, isCourtesy: false,
        manualToggleIds: RateModifier::pluck('id')->all(),
    );

    expect($result['amount'])->toBe(500.0)
        ->and($result['snapshot']['rule'])->toBe('Monto fijo alto')
        ->and($result['snapshot']['candidates']['Multiplicador bajo'])->toBe(300.0);
});
