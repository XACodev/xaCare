<?php

use App\Models\Hospital;
use App\Models\User;
use Livewire\Volt\Volt;

test('super admin can list and toggle hospitals', function () {
    $hospital = Hospital::factory()->create(['is_active' => true]);
    $superAdmin = User::factory()->create(['hospital_id' => null, 'is_platform_admin' => true]);
    $this->actingAs($superAdmin);

    Volt::test('hospitals.index')
        ->call('toggleActive', $hospital->id)
        ->assertHasNoErrors();

    expect($hospital->fresh()->is_active)->toBeFalse();
});

test('non super admin cannot list hospitals', function () {
    $user = User::factory()->create(['hospital_id' => Hospital::factory()->create()->id]);
    $this->actingAs($user);

    $this->get(route('hospitals.index'))->assertForbidden();
});
