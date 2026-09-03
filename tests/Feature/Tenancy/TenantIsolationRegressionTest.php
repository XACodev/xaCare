<?php

use App\Models\Hospital;
use App\Modules\QxLog\Models\RateModifier;
use App\Modules\QxLog\Models\RoleRate;
use App\Modules\QxLog\Models\SurgicalAssignment;
use App\Modules\QxLog\Models\SurgicalCase;
use App\Modules\QxLog\Models\SurgicalRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    foreach (['procedures.edit', 'procedures.view', 'payouts.view', 'pricing.manage'] as $perm) {
        Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
    }
    $adminRole->givePermissionTo(['procedures.edit', 'procedures.view', 'payouts.view', 'pricing.manage']);

    $this->hospitalA = Hospital::factory()->create();
    $this->hospitalB = Hospital::factory()->create();

    $this->adminA = User::factory()->create([
        'role' => 'admin',
        'hospital_id' => $this->hospitalA->id,
    ]);
    $this->adminA->assignRole('admin');

    $this->adminB = User::factory()->create([
        'role' => 'admin',
        'hospital_id' => $this->hospitalB->id,
    ]);
    $this->adminB->assignRole('admin');

    $this->instrumentistA = User::factory()->create([
        'role' => 'instrumentist',
        'hospital_id' => $this->hospitalA->id,
    ]);

    $this->instrumentistB = User::factory()->create([
        'role' => 'instrumentist',
        'hospital_id' => $this->hospitalB->id,
    ]);

    $this->roleA = SurgicalRole::factory()->create([
        'hospital_id' => $this->hospitalA->id,
        'name' => 'Instrumentista',
        'active' => true,
    ]);

    $this->roleB = SurgicalRole::factory()->create([
        'hospital_id' => $this->hospitalB->id,
        'name' => 'Instrumentista',
        'active' => true,
    ]);
});

test('procedures.index only lists instrumentists from the same hospital', function () {
    $this->actingAs($this->adminA);

    $component = Volt::test('procedures.index');
    $instrumentists = $component->instance()->instrumentists;

    expect($instrumentists->pluck('id'))->toContain($this->instrumentistA->id);
    expect($instrumentists->pluck('id'))->not->toContain($this->instrumentistB->id);
});

test('procedures.edit only shows users from the same hospital in assignment selector', function () {
    $case = SurgicalCase::factory()->create([
        'hospital_id' => $this->hospitalA->id,
        'status' => 'pending',
    ]);

    SurgicalAssignment::factory()->create([
        'hospital_id' => $this->hospitalA->id,
        'surgical_case_id' => $case->id,
        'surgical_role_id' => $this->roleA->id,
        'user_id' => $this->instrumentistA->id,
    ]);

    $this->actingAs($this->adminA);

    $response = $this->get(route('procedures.edit', $case));
    $response->assertOk();

    // El selector de personas no debe incluir al instrumentista del otro hospital.
    $response->assertDontSee($this->instrumentistB->name);
    $response->assertSee($this->instrumentistA->name);
});

test('pricing.settings rejects user_id from another hospital when saving base rate', function () {
    $this->actingAs($this->adminA);

    Volt::actingAs($this->adminA)
        ->test('pricing.settings')
        ->set('selected_role_id', $this->roleA->id)
        ->set('user_id', $this->instrumentistB->id)
        ->set('base_rate', 1500)
        ->call('saveBaseRate')
        ->assertForbidden();

    expect(RoleRate::withoutGlobalScopes()->count())->toBe(0);
});

test('pricing.settings accepts user_id from the same hospital when saving base rate', function () {
    $this->actingAs($this->adminA);

    Volt::actingAs($this->adminA)
        ->test('pricing.settings')
        ->set('selected_role_id', $this->roleA->id)
        ->set('user_id', $this->instrumentistA->id)
        ->set('base_rate', 1500)
        ->call('saveBaseRate')
        ->assertOk();

    $rate = RoleRate::withoutGlobalScopes()->first();
    expect($rate)->not->toBeNull();
    expect($rate->hospital_id)->toBe($this->hospitalA->id);
    expect($rate->user_id)->toBe($this->instrumentistA->id);
});

test('pricing.settings removeModifier cannot delete modifiers from another role rate', function () {
    $this->actingAs($this->adminA);

    $roleRateA = RoleRate::create([
        'hospital_id' => $this->hospitalA->id,
        'surgical_role_id' => $this->roleA->id,
        'base_rate' => 1000,
        'active' => true,
    ]);

    $roleRateB = RoleRate::withoutGlobalScopes()->create([
        'hospital_id' => $this->hospitalB->id,
        'surgical_role_id' => $this->roleB->id,
        'base_rate' => 2000,
        'active' => true,
    ]);

    $modifierB = RateModifier::create([
        'role_rate_id' => $roleRateB->id,
        'name' => 'Modifier B',
        'trigger_type' => RateModifier::TRIGGER_MANUAL_TOGGLE,
        'rate_type' => RateModifier::RATE_FIXED_AMOUNT,
        'amount' => 50,
        'active' => true,
    ]);

    Volt::actingAs($this->adminA)
        ->test('pricing.settings')
        ->set('selected_role_id', $this->roleA->id)
        ->call('removeModifier', $modifierB->id)
        ->assertForbidden();

    expect(RateModifier::withoutGlobalScopes()->find($modifierB->id))->not->toBeNull();
});

test('platform admin cannot delete a procedure', function () {
    $case = SurgicalCase::factory()->create([
        'hospital_id' => $this->hospitalA->id,
        'status' => 'pending',
    ]);

    $platformAdmin = User::factory()->create([
        'is_platform_admin' => true,
        'hospital_id' => null,
    ]);
    $platformAdmin->assignRole('admin');

    $this->actingAs($platformAdmin);

    Volt::test('procedures.index')
        ->set('procedure_to_delete', $case->id)
        ->call('delete')
        ->assertForbidden();

    expect(SurgicalCase::withoutGlobalScopes()->find($case->id))->not->toBeNull();
});

test('platform admin cannot save an edited procedure', function () {
    $case = SurgicalCase::factory()->create([
        'hospital_id' => $this->hospitalA->id,
        'status' => 'pending',
    ]);

    SurgicalAssignment::factory()->create([
        'hospital_id' => $this->hospitalA->id,
        'surgical_case_id' => $case->id,
        'surgical_role_id' => $this->roleA->id,
        'user_id' => $this->instrumentistA->id,
    ]);

    $platformAdmin = User::factory()->create([
        'is_platform_admin' => true,
        'hospital_id' => null,
    ]);
    $platformAdmin->assignRole('admin');

    $this->actingAs($platformAdmin);

    Volt::test('procedures.edit', ['procedure' => $case])
        ->set('patient_name', 'Tampered')
        ->call('save')
        ->assertForbidden();

    expect($case->fresh()->patient_name)->not->toBe('Tampered');
});
