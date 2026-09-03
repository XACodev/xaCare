<?php

use App\Models\Hospital;
use App\Models\User;
use Livewire\Volt\Volt;

test('hospital admin can view the procedures index', function () {
    $hospital = Hospital::factory()->create();
    $admin = User::factory()->create(['role' => 'admin', 'hospital_id' => $hospital->id]);

    $this->actingAs($admin);

    Volt::test('qxlog.procedures.index')->assertOk();
});

test('super admin can view the procedures index', function () {
    $superAdmin = User::factory()->create(['is_platform_admin' => true, 'hospital_id' => null]);

    $this->actingAs($superAdmin);

    Volt::test('qxlog.procedures.index')->assertOk();
});

test('instrumentist cannot view the procedures index', function () {
    $hospital = Hospital::factory()->create();
    $instrumentist = User::factory()->create(['role' => 'instrumentist', 'hospital_id' => $hospital->id]);

    $this->actingAs($instrumentist);

    Volt::test('qxlog.procedures.index')->assertForbidden();
});
