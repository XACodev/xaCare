<?php

use App\Models\Hospital;
use App\Models\OrganizationSetting;
use App\Models\User;
use Livewire\Volt\Volt;

test('super admin can create a hospital and it gets its own organization settings', function () {
    $superAdmin = User::factory()->create(['hospital_id' => null, 'is_platform_admin' => true]);
    $this->actingAs($superAdmin);

    Volt::test('hospitals.create')
        ->set('name', 'Hospital Nuevo')
        ->set('plan', 'basic')
        ->call('save')
        ->assertHasNoErrors();

    $hospital = Hospital::where('name', 'Hospital Nuevo')->first();
    expect($hospital)->not->toBeNull();
    expect($hospital->slug)->toBe('hospital-nuevo');
    expect($hospital->subscription_status->value)->toBe('trialing');
    expect(OrganizationSetting::where('hospital_id', $hospital->id)->exists())->toBeTrue();
});

test('non super admin cannot create a hospital', function () {
    $user = User::factory()->create(['hospital_id' => Hospital::factory()->create()->id]);
    $this->actingAs($user);

    $this->get(route('hospitals.create'))->assertForbidden();
});
