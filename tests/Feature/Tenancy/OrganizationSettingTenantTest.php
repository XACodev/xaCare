<?php

use App\Models\Hospital;
use App\Models\OrganizationSetting;
use App\Models\User;

test('creating a hospital auto-creates its own organization settings row', function () {
    $hospital = Hospital::factory()->create(['name' => 'Hospital de Prueba']);

    $settings = OrganizationSetting::where('hospital_id', $hospital->id)->first();

    expect($settings)->not->toBeNull();
    expect($settings->org_name)->toBe('Hospital de Prueba');
});

test('organization settings are isolated per hospital', function () {
    $hospitalA = Hospital::factory()->create();
    $hospitalB = Hospital::factory()->create();

    $userA = User::factory()->create(['hospital_id' => $hospitalA->id]);
    $this->actingAs($userA);
    OrganizationSetting::current()->update(['org_name' => 'Nombre A']);

    $userB = User::factory()->create(['hospital_id' => $hospitalB->id]);
    $this->actingAs($userB);

    expect(OrganizationSetting::current()->org_name)->not->toBe('Nombre A');
});

test('forHospital resolves settings for a specific hospital regardless of the viewer', function () {
    $hospitalA = Hospital::factory()->create();
    $hospitalB = Hospital::factory()->create();

    $superAdmin = User::factory()->create(['hospital_id' => null, 'is_platform_admin' => true]);
    $this->actingAs($superAdmin);

    $settingsA = OrganizationSetting::forHospital($hospitalA->id);
    $settingsB = OrganizationSetting::forHospital($hospitalB->id);

    expect($settingsA->hospital_id)->toBe($hospitalA->id);
    expect($settingsB->hospital_id)->toBe($hospitalB->id);
});
