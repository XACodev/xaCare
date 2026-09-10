<?php

use App\Models\Hospital;
use App\Support\CoreRoleProvisioner;
use Spatie\Permission\Models\Role;

test('creating a hospital provisions doctor, instrumentist and circulating scoped to it', function () {
    $hospital = Hospital::factory()->create();

    foreach (['doctor', 'instrumentist', 'circulating'] as $roleName) {
        $role = Role::where('name', $roleName)->where('guard_name', 'web')->where('team_id', $hospital->id)->first();

        expect($role)->not->toBeNull()
            ->and($role->permissions()->pluck('name')->sort()->values()->all())->toBe(['procedures.create', 'procedures.view']);
    }

    expect(Role::where('name', 'admin')->where('team_id', $hospital->id)->exists())->toBeFalse();
});

test('provisioning is idempotent and does not overwrite a hospital customized role', function () {
    $hospital = Hospital::factory()->create();

    $doctorRole = Role::where('name', 'doctor')->where('team_id', $hospital->id)->first();
    $doctorRole->syncPermissions(['procedures.create']);

    CoreRoleProvisioner::provisionFor($hospital);

    $doctorRole->refresh();

    expect($doctorRole->permissions()->pluck('name')->all())->toBe(['procedures.create'])
        ->and(Role::where('name', 'doctor')->where('team_id', $hospital->id)->count())->toBe(1);
});
