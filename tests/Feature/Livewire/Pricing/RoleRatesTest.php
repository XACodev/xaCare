<?php
// tests/Feature/Livewire/Pricing/RoleRatesTest.php
use App\Models\Hospital;
use App\Modules\QxLog\Models\RoleRate;
use App\Modules\QxLog\Models\SurgicalRole;
use App\Models\User;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    Permission::create(['name' => 'pricing.manage', 'guard_name' => 'web']);
});

test('un admin puede configurar la tarifa base default de un rol', function () {
    $hospital = Hospital::factory()->create();
    $admin = User::factory()->create(['hospital_id' => $hospital->id]);
    $admin->givePermissionTo('pricing.manage');
    $role = SurgicalRole::factory()->for($hospital, 'hospital')->create(['name' => 'Cirujano']);

    Volt::actingAs($admin)->test('qxlog.pricing.settings')
        ->set('selected_role_id', $role->id)
        ->set('base_rate', 1500)
        ->call('saveBaseRate');

    expect((float) RoleRate::where('surgical_role_id', $role->id)->whereNull('user_id')->first()->base_rate)->toBe(1500.0);
});

test('un admin puede configurar tarifa base con override individual por instrumentista', function () {
    $hospital = Hospital::factory()->create();
    $admin = User::factory()->create(['hospital_id' => $hospital->id]);
    $admin->givePermissionTo('pricing.manage');
    $role = SurgicalRole::factory()->for($hospital, 'hospital')->create(['name' => 'Instrumentista']);
    $instrumentist = User::factory()->create(['hospital_id' => $hospital->id]);

    Volt::actingAs($admin)->test('qxlog.pricing.settings')
        ->set('selected_role_id', $role->id)
        ->set('user_id', $instrumentist->id)
        ->set('base_rate', 1750)
        ->call('saveBaseRate');

    $rate = RoleRate::where('surgical_role_id', $role->id)
        ->where('user_id', $instrumentist->id)
        ->whereNull('procedure_type')
        ->first();

    expect($rate)->not->toBeNull();
    expect((float) $rate->base_rate)->toBe(1750.0);
    expect($rate->hospital_id)->toBe($hospital->id);
});
