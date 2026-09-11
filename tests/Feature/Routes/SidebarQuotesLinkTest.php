<?php

use App\Models\Hospital;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::firstOrCreate(['name' => 'surgeries.budget.view_total', 'guard_name' => 'web']);
});

test('el link de cotizaciones aparece si el hospital tiene el feature y el usuario tiene permiso', function () {
    $hospital = Hospital::factory()->create(['features' => ['qxlog', 'qxlog_quotes']]);
    $admin = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'admin']);
    $admin->givePermissionTo('surgeries.budget.view_total');

    $this->actingAs($admin)->get('/dashboard')->assertSee(route('quotes.index'), false);
});

test('el link no aparece sin el feature qxlog_quotes aunque el usuario tenga el permiso', function () {
    $hospital = Hospital::factory()->create(['features' => ['qxlog']]);
    $admin = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'admin']);
    $admin->givePermissionTo('surgeries.budget.view_total');

    $this->actingAs($admin)->get('/dashboard')->assertDontSee(route('quotes.index'), false);
});
