<?php

use App\Models\Hospital;
use App\Modules\QxLog\Models\RoleRate;
use App\Modules\QxLog\Models\SurgicalRole;
use App\Models\User;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::create(['name' => 'pricing.manage', 'guard_name' => 'web']);
});

test('reloading the page with ?selected_role_id keeps showing the saved rate for that role', function () {
    $hospital = Hospital::factory()->create();
    $admin = User::factory()->create(['role' => 'admin', 'hospital_id' => $hospital->id]);
    Role::create(['name' => 'admin', 'guard_name' => 'web']);
    $admin->assignRole('admin');
    $admin->givePermissionTo('pricing.manage');

    $firstRole = SurgicalRole::factory()->for($hospital, 'hospital')->create(['name' => 'Circulante', 'sort_order' => 1]);
    $secondRole = SurgicalRole::factory()->for($hospital, 'hospital')->create(['name' => 'Cirujano', 'sort_order' => 2]);

    RoleRate::factory()->create([
        'hospital_id' => $hospital->id,
        'surgical_role_id' => $secondRole->id,
        'user_id' => null,
        'procedure_type' => null,
        'base_rate' => 350,
    ]);

    $this->actingAs($admin)
        ->get('/pricing/settings?selected_role_id='.$secondRole->id)
        // Sin el fix, mount() ignoraba el query param y volvía siempre al primer rol
        // (Circulante), mostrando 0 aunque la tarifa de Cirujano sí estaba guardada.
        ->assertSee('350');
});

test('rules reject a role from another hospital before saveBaseRate runs', function () {
    $hospitalA = Hospital::factory()->create();
    $hospitalB = Hospital::factory()->create();

    $admin = User::factory()->create(['role' => 'admin', 'hospital_id' => $hospitalA->id]);
    Role::create(['name' => 'admin', 'guard_name' => 'web']);
    $admin->assignRole('admin');
    $admin->givePermissionTo('pricing.manage');

    SurgicalRole::factory()->for($hospitalA, 'hospital')->create(['name' => 'Circulante', 'sort_order' => 1]);
    $foreignRole = SurgicalRole::factory()->for($hospitalB, 'hospital')->create(['name' => 'Cirujano', 'sort_order' => 1]);

    $this->actingAs($admin);

    Volt::test('qxlog.pricing.settings')
        ->set('selected_role_id', $foreignRole->id)
        ->set('base_rate', 999)
        ->call('saveBaseRate')
        ->assertHasErrors(['selected_role_id']);

    expect(RoleRate::where('surgical_role_id', $foreignRole->id)->where('base_rate', 999)->exists())->toBeFalse();
});

test('saveBaseRate rejects a per-user override for a user from another hospital', function () {
    $hospitalA = Hospital::factory()->create();
    $hospitalB = Hospital::factory()->create();

    $admin = User::factory()->create(['role' => 'admin', 'hospital_id' => $hospitalA->id]);
    Role::create(['name' => 'admin', 'guard_name' => 'web']);
    $admin->assignRole('admin');
    $admin->givePermissionTo('pricing.manage');

    $role = SurgicalRole::factory()->for($hospitalA, 'hospital')->create(['name' => 'Circulante', 'sort_order' => 1]);
    $foreignUser = User::factory()->create(['hospital_id' => $hospitalB->id]);

    $this->actingAs($admin);

    $component = Volt::test('qxlog.pricing.settings');
    $component->set('selected_role_id', $role->id);
    $component->set('user_id', $foreignUser->id);
    $component->set('base_rate', 999);
    $component->call('saveBaseRate')
        ->assertStatus(403);

    expect(RoleRate::where('user_id', $foreignUser->id)->exists())->toBeFalse();
});

test('a user without hospital_id is rejected from pricing settings', function () {
    $admin = User::withoutEvents(fn () => User::factory()->create([
        'role' => 'admin',
        'hospital_id' => null,
    ]));
    Role::create(['name' => 'admin', 'guard_name' => 'web']);
    $admin->assignRole('admin');
    $admin->givePermissionTo('pricing.manage');

    $this->actingAs($admin);

    Volt::test('qxlog.pricing.settings')
        ->assertStatus(422);
});
