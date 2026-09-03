<?php

use App\Models\Hospital;
use App\Models\User;

test('users are scoped to the authenticated user hospital', function () {
    $hospitalA = Hospital::factory()->create();
    $hospitalB = Hospital::factory()->create();

    User::factory()->create(['hospital_id' => $hospitalA->id, 'role' => 'doctor']);
    User::factory()->create(['hospital_id' => $hospitalB->id, 'role' => 'doctor']);

    $adminA = User::factory()->create(['hospital_id' => $hospitalA->id, 'role' => 'admin']);
    $this->actingAs($adminA);

    expect(User::where('role', 'doctor')->count())->toBe(1);
});

test('super admin never appears in a hospital-scoped user listing', function () {
    $hospital = Hospital::factory()->create();
    $superAdmin = User::factory()->create(['hospital_id' => null, 'is_platform_admin' => true]);
    User::factory()->create(['hospital_id' => $hospital->id]);

    $admin = User::factory()->create(['hospital_id' => $hospital->id]);
    $this->actingAs($admin);

    $ids = User::pluck('id');
    expect($ids)->not->toContain($superAdmin->id);
});

test('session user retrieval does not recurse through the tenant scope', function () {
    $hospital = Hospital::factory()->create();
    $user = User::factory()->create(['hospital_id' => $hospital->id]);

    $this->actingAs($user);
    auth()->guard('web')->forgetUser();

    $retrieved = auth()->guard('web')->getProvider()->retrieveById($user->id);

    expect($retrieved)->not->toBeNull()
        ->and($retrieved->id)->toBe($user->id);
});
