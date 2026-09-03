<?php

use App\Models\Hospital;
use App\Models\User;

test('backfill-users assigns hospital to users without hospital_id', function () {
    $hospital = Hospital::factory()->create(['slug' => 'hnsc']);

    $orphan = User::withoutEvents(fn () => User::factory()->create([
        'hospital_id' => null,
        'is_platform_admin' => false,
        'role' => 'instrumentist',
    ]));

    $platformAdmin = User::factory()->create([
        'hospital_id' => null,
        'is_platform_admin' => true,
        'role' => 'admin',
    ]);

    $this->artisan('xacare:backfill-users --hospital=hnsc')->assertSuccessful();

    expect($orphan->fresh()->hospital_id)->toBe($hospital->id);
    expect($platformAdmin->fresh()->hospital_id)->toBeNull();
});

test('backfill-users does not assign hospital when slug is missing', function () {
    $this->artisan('xacare:backfill-users --hospital=missing')
        ->assertFailed()
        ->expectsOutput("Hospital 'missing' not found.");
});

test('backfill-users iterates all hospitals when no slug is given', function () {
    Hospital::factory()->create(['slug' => 'a']);
    Hospital::factory()->create(['slug' => 'b']);

    User::withoutEvents(fn () => User::factory()->create([
        'hospital_id' => null,
        'is_platform_admin' => false,
        'role' => 'admin',
    ]));

    $this->artisan('xacare:backfill-users')->assertSuccessful();

    expect(User::withoutGlobalScopes()->whereNull('hospital_id')->where('is_platform_admin', false)->count())->toBe(0);
});
