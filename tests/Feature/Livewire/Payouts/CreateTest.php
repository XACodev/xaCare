<?php

use App\Models\Hospital;
use App\Models\User;
use App\Modules\QxLog\Models\PayoutBatch;
use App\Modules\QxLog\Models\SurgicalAssignment;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'instrumentist', 'guard_name' => 'web']);

    Permission::create(['name' => 'payouts.create', 'guard_name' => 'web']);
    $adminRole->givePermissionTo('payouts.create');
});

test('shows pending and selected procedure counts', function () {
    $hospital = Hospital::factory()->create();

    $admin = User::factory()->create(['hospital_id' => $hospital->id]);
    $admin->assignRole('admin');

    $instrumentist = User::factory()->create(['hospital_id' => $hospital->id]);
    $instrumentist->assignRole('instrumentist');

    $this->actingAs($admin);

    $assignments = SurgicalAssignment::factory()->count(3)->create([
        'user_id' => $instrumentist->id,
        'status' => 'pending',
        'calculated_amount' => 100,
    ]);

    Volt::test('qxlog.payouts.create')
        ->set('payee_id', $instrumentist->id)
        ->assertSee(__(':count procedures', ['count' => 3]))
        ->set('selected', [$assignments->first()->id])
        ->assertSee(__(':count selected', ['count' => 1]));
});

test('liquidating redirects to the payout voucher', function () {
    $hospital = Hospital::factory()->create();

    $admin = User::factory()->create(['hospital_id' => $hospital->id]);
    $admin->assignRole('admin');

    $instrumentist = User::factory()->create(['hospital_id' => $hospital->id]);
    $instrumentist->assignRole('instrumentist');

    $this->actingAs($admin);

    $assignments = SurgicalAssignment::factory()->count(2)->create([
        'user_id' => $instrumentist->id,
        'status' => 'pending',
        'calculated_amount' => 150,
    ]);

    $component = Volt::test('qxlog.payouts.create')
        ->set('payee_id', $instrumentist->id)
        ->set('selected', $assignments->pluck('id')->all())
        ->call('liquidate');

    $batch = \App\Modules\QxLog\Models\PayoutBatch::firstOrFail();

    $component->assertRedirect(route('payouts.voucher', $batch->id));

    expect($assignments->first()->fresh()->status)->toBe('paid');
});

test('preselects instrumentist from query parameter', function () {
    $hospital = Hospital::factory()->create();

    $admin = User::factory()->create(['hospital_id' => $hospital->id]);
    $admin->assignRole('admin');

    $instrumentist = User::factory()->create(['hospital_id' => $hospital->id]);
    $instrumentist->assignRole('instrumentist');

    $this->actingAs($admin);

    SurgicalAssignment::factory()->create([
        'user_id' => $instrumentist->id,
        'status' => 'pending',
        'calculated_amount' => 100,
    ]);

    $this->get(route('payouts.create', ['payee_id' => $instrumentist->id]))
        ->assertSuccessful()
        ->assertSee(__(':count procedures', ['count' => 1]));
});

test('does not show payees or assignments from another hospital', function () {
    $hospitalA = Hospital::factory()->create();
    $hospitalB = Hospital::factory()->create();

    $admin = User::factory()->create(['hospital_id' => $hospitalA->id]);
    $admin->assignRole('admin');

    $instrumentistA = User::factory()->create(['hospital_id' => $hospitalA->id]);
    $instrumentistA->assignRole('instrumentist');

    $instrumentistB = User::factory()->create(['hospital_id' => $hospitalB->id]);
    $instrumentistB->assignRole('instrumentist');

    $roleA = \App\Modules\QxLog\Models\SurgicalRole::factory()->create(['hospital_id' => $hospitalA->id]);
    $roleB = \App\Modules\QxLog\Models\SurgicalRole::factory()->create(['hospital_id' => $hospitalB->id]);

    $caseA = \App\Modules\QxLog\Models\SurgicalCase::factory()->create(['hospital_id' => $hospitalA->id]);
    $caseB = \App\Modules\QxLog\Models\SurgicalCase::factory()->create(['hospital_id' => $hospitalB->id]);

    $assignmentA = SurgicalAssignment::factory()->create([
        'hospital_id' => $hospitalA->id,
        'surgical_case_id' => $caseA->id,
        'surgical_role_id' => $roleA->id,
        'user_id' => $instrumentistA->id,
        'status' => 'pending',
        'calculated_amount' => 100,
    ]);

    $assignmentB = SurgicalAssignment::factory()->create([
        'hospital_id' => $hospitalB->id,
        'surgical_case_id' => $caseB->id,
        'surgical_role_id' => $roleB->id,
        'user_id' => $instrumentistB->id,
        'status' => 'pending',
        'calculated_amount' => 200,
    ]);

    $component = Volt::actingAs($admin)->test('qxlog.payouts.create');

    // Solo se listan beneficiarios del hospital A.
    expect($component->get('payees')->pluck('id')->all())->toContain($instrumentistA->id)
        ->and($component->get('payees')->pluck('id')->all())->not->toContain($instrumentistB->id);

    // Al seleccionar al beneficiario A, solo aparecen sus assignments del hospital A.
    $component->set('payee_id', $instrumentistA->id);
    expect($component->get('pending_assignments')->pluck('id')->all())->toContain($assignmentA->id)
        ->and($component->get('pending_assignments')->pluck('id')->all())->not->toContain($assignmentB->id);

    // No se puede liquidar un assignment de otro hospital aunque se fuerce en selected.
    $component->set('selected', [$assignmentB->id]);
    $component->call('liquidate');
    $component->assertHasErrors(['selected.0']);

    expect($assignmentB->fresh()->status)->toBe('pending');
});

test('cannot liquidate the same assignments twice', function () {
    $hospital = Hospital::factory()->create();

    $admin = User::factory()->create(['hospital_id' => $hospital->id]);
    $admin->assignRole('admin');

    $instrumentist = User::factory()->create(['hospital_id' => $hospital->id]);
    $instrumentist->assignRole('instrumentist');

    $this->actingAs($admin);

    $assignments = SurgicalAssignment::factory()->count(2)->create([
        'user_id' => $instrumentist->id,
        'status' => 'pending',
        'calculated_amount' => 150,
    ]);

    $ids = $assignments->pluck('id')->all();

    Volt::test('qxlog.payouts.create')
        ->set('payee_id', $instrumentist->id)
        ->set('selected', $ids)
        ->call('liquidate')
        ->assertHasNoErrors();

    expect(PayoutBatch::count())->toBe(1);

    Volt::test('qxlog.payouts.create')
        ->set('payee_id', $instrumentist->id)
        ->set('selected', $ids)
        ->call('liquidate')
        ->assertHasErrors(['selected']);

    expect(PayoutBatch::count())->toBe(1);
    expect($assignments->first()->fresh()->status)->toBe('paid');
});
