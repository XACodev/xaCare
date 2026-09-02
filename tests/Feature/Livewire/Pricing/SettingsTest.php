<?php

use App\Models\Hospital;
use App\Models\RoleRate;
use App\Models\SurgicalRole;
use App\Models\User;
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
