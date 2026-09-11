<?php

use App\Models\Hospital;
use App\Models\OrganizationSetting;
use App\Models\User;
use App\Modules\QxLog\Models\OperatingRoom;
use App\Modules\QxLog\Models\SurgeryStatus;
use Livewire\Volt\Volt;

test('super admin can create a hospital and it gets its own organization settings', function () {
    $superAdmin = User::factory()->create(['hospital_id' => null, 'is_platform_admin' => true]);
    $this->actingAs($superAdmin);

    Volt::test('platform.hospitals.create')
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

test('creating a hospital seeds a default operating room and surgery status catalog', function () {
    // Regresion: sin esto, un hospital nuevo se queda con operating_room_id/surgery_status_id
    // null para siempre, y la validacion de choque de horario del tablero de cirugias
    // (OperatingRoomAvailabilityService) se salta en silencio porque nunca hay un quirofano
    // que comparar.
    $superAdmin = User::factory()->create(['hospital_id' => null, 'is_platform_admin' => true]);
    $this->actingAs($superAdmin);

    Volt::test('platform.hospitals.create')
        ->set('name', 'Hospital Con Quirofano')
        ->set('plan', 'basic')
        ->call('save')
        ->assertHasNoErrors();

    $hospital = Hospital::where('name', 'Hospital Con Quirofano')->first();

    $room = OperatingRoom::withoutGlobalScopes()->where('hospital_id', $hospital->id)->first();
    expect($room)->not->toBeNull();
    expect($room->is_default)->toBeTrue();
    expect($room->active)->toBeTrue();

    $statuses = SurgeryStatus::withoutGlobalScopes()->where('hospital_id', $hospital->id)->pluck('slug');
    expect($statuses->sort()->values()->all())->toBe(['cancelada', 'completada', 'en-curso', 'programada']);
});

test('non super admin cannot create a hospital', function () {
    $user = User::factory()->create(['hospital_id' => Hospital::factory()->create()->id]);
    $this->actingAs($user);

    $this->get(route('platform.hospitals.create'))->assertForbidden();
});
