<?php

use App\Models\Hospital;
use App\Models\HospitalInvitation;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
});

test('a super admin can generate an invitation for a hospital', function () {
    $superAdmin = User::factory()->create(['hospital_id' => null, 'is_super_admin' => true]);
    $hospital = Hospital::factory()->create();

    $this->actingAs($superAdmin);

    Volt::test('hospitals.edit', ['hospital' => $hospital->id])
        ->set('invitation_note', 'Dr. Juan Pérez, Hospital San Rafael')
        ->call('generateInvitation')
        ->assertHasNoErrors()
        ->assertSet('generated_link', fn ($link) => filled($link));

    $invitation = HospitalInvitation::withoutGlobalScopes()->where('hospital_id', $hospital->id)->first();

    expect($invitation)->not->toBeNull()
        ->and($invitation->note)->toBe('Dr. Juan Pérez, Hospital San Rafael')
        ->and($invitation->invited_by)->toBe($superAdmin->id)
        ->and($invitation->accepted_at)->toBeNull()
        ->and($invitation->expires_at->isFuture())->toBeTrue();
});

test('a non super admin cannot generate invitations', function () {
    $hospital = Hospital::factory()->create();
    $admin = User::factory()->create(['hospital_id' => $hospital->id, 'is_super_admin' => false]);

    $this->actingAs($admin);

    $this->get(route('hospitals.edit', $hospital->id))->assertForbidden();
});

test('accepting a valid invitation creates an admin user for the correct hospital and logs them in', function () {
    $hospital = Hospital::factory()->create();
    [$invitation, $plainToken] = HospitalInvitation::generateFor($hospital->id, null, 'Test invite');

    Volt::test('hospital-invitations.accept', ['token' => $plainToken])
        ->assertSet('valid', true)
        ->set('name', 'New Admin')
        ->set('username', 'newadmin')
        ->set('email', 'newadmin@example.com')
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('accept')
        ->assertHasNoErrors();

    $user = User::withoutGlobalScopes()->where('email', 'newadmin@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->hospital_id)->toBe($hospital->id)
        ->and($user->is_super_admin)->toBeFalse()
        ->and($user->hasRole('admin'))->toBeTrue();

    $invitation->refresh();
    expect($invitation->accepted_at)->not->toBeNull()
        ->and($invitation->accepted_by)->toBe($user->id);

    expect(Auth::id())->toBe($user->id);
});

test('an expired invitation token is rejected with the generic message', function () {
    $hospital = Hospital::factory()->create();
    $invitation = HospitalInvitation::factory()->expired()->create(['hospital_id' => $hospital->id]);

    $plainToken = 'expired-plain-token';
    $invitation->update(['token' => hash('sha256', $plainToken)]);

    Volt::test('hospital-invitations.accept', ['token' => $plainToken])
        ->assertSet('valid', false)
        ->assertSee('no es válido o ya expiró');
});

test('an already used invitation token is rejected with the generic message', function () {
    $hospital = Hospital::factory()->create();
    $invitation = HospitalInvitation::factory()->accepted()->create(['hospital_id' => $hospital->id]);

    $plainToken = 'used-plain-token';
    $invitation->update(['token' => hash('sha256', $plainToken)]);

    Volt::test('hospital-invitations.accept', ['token' => $plainToken])
        ->assertSet('valid', false)
        ->assertSee('no es válido o ya expiró');
});

test('a token that never existed is rejected with the exact same generic message', function () {
    $hospital = Hospital::factory()->create();
    $expired = HospitalInvitation::factory()->expired()->create(['hospital_id' => $hospital->id]);
    $expiredPlainToken = 'expired-plain-token-2';
    $expired->update(['token' => hash('sha256', $expiredPlainToken)]);

    $used = HospitalInvitation::factory()->accepted()->create(['hospital_id' => $hospital->id]);
    $usedPlainToken = 'used-plain-token-2';
    $used->update(['token' => hash('sha256', $usedPlainToken)]);

    $nonExistentComponent = Volt::test('hospital-invitations.accept', ['token' => 'this-token-never-existed']);
    $expiredComponent = Volt::test('hospital-invitations.accept', ['token' => $expiredPlainToken]);
    $usedComponent = Volt::test('hospital-invitations.accept', ['token' => $usedPlainToken]);

    // All three failure modes must be indistinguishable from each other.
    expect($nonExistentComponent->get('valid'))->toBeFalse()
        ->and($expiredComponent->get('valid'))->toBeFalse()
        ->and($usedComponent->get('valid'))->toBeFalse();

    $nonExistentComponent->assertSee('no es válido o ya expiró');
    $expiredComponent->assertSee('no es válido o ya expiró');
    $usedComponent->assertSee('no es válido o ya expiró');
});

test('accepting an invitation isolates the new user to their hospital only', function () {
    $hospitalA = Hospital::factory()->create();
    $hospitalB = Hospital::factory()->create();

    Patient::factory()->create(['hospital_id' => $hospitalA->id]);
    Patient::factory()->create(['hospital_id' => $hospitalB->id]);
    Patient::factory()->create(['hospital_id' => $hospitalB->id]);

    [$invitation, $plainToken] = HospitalInvitation::generateFor($hospitalA->id);

    Volt::test('hospital-invitations.accept', ['token' => $plainToken])
        ->set('name', 'Hospital A Admin')
        ->set('username', 'hospitalaadmin')
        ->set('email', 'hospitalaadmin@example.com')
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('accept')
        ->assertHasNoErrors();

    $user = User::withoutGlobalScopes()->where('email', 'hospitalaadmin@example.com')->first();

    expect($user->hospital_id)->toBe($hospitalA->id)
        ->and($user->hospital_id)->not->toBe($hospitalB->id);

    $this->actingAs($user);

    expect(Patient::count())->toBe(1);
    expect(Patient::first()->hospital_id)->toBe($hospitalA->id);
});
