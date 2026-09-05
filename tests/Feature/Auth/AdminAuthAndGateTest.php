<?php

use App\Models\Hospital;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware(['auth', 'admin'])
        ->get('/_test/admin-auth', fn () => response('ok'))
        ->name('_test.admin-auth');
});

test('admin auth allows hospital admin', function () {
    $hospital = Hospital::factory()->create();
    $admin = User::factory()->create(['role' => 'admin', 'hospital_id' => $hospital->id]);

    $this->actingAs($admin)
        ->get('/_test/admin-auth')
        ->assertSuccessful()
        ->assertSee('ok');
});

test('admin auth allows platform admin', function () {
    $platformAdmin = User::factory()->create([
        'is_platform_admin' => true,
        'hospital_id' => null,
        'role' => 'admin',
    ]);

    $this->actingAs($platformAdmin)
        ->get('/_test/admin-auth')
        ->assertSuccessful()
        ->assertSee('ok');
});

test('admin auth rejects non admin hospital user', function () {
    $hospital = Hospital::factory()->create();
    $user = User::factory()->create(['role' => 'doctor', 'hospital_id' => $hospital->id]);

    $this->actingAs($user)
        ->get('/_test/admin-auth')
        ->assertUnauthorized();
});

test('admin auth rejects guest', function () {
    $this->get('/_test/admin-auth')
        ->assertRedirect(route('login'));
});

test('gate before grants all abilities to platform admin', function () {
    $platformAdmin = User::factory()->create([
        'is_platform_admin' => true,
        'hospital_id' => null,
        'role' => 'admin',
    ]);

    expect(Gate::forUser($platformAdmin)->allows('nonexistent.ability'))->toBeTrue();
});

test('gate before does not auto grant abilities to hospital users', function () {
    $hospital = Hospital::factory()->create();
    $user = User::factory()->create(['role' => 'admin', 'hospital_id' => $hospital->id]);

    expect(Gate::forUser($user)->allows('nonexistent.ability'))->toBeFalse();
});
