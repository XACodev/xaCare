<?php

use App\Models\PlatformAdminInvitation;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Volt;

test('a platform admin can generate an invitation for a new platform admin', function () {
    $admin = User::factory()->create(['hospital_id' => null, 'is_platform_admin' => true]);

    Volt::actingAs($admin)->test('platform.admins.index')
        ->set('invitation_note', 'Soporte L2')
        ->call('generateInvitation')
        ->assertHasNoErrors()
        ->assertSet('generated_link', fn ($link) => filled($link));

    $invitation = PlatformAdminInvitation::first();

    expect($invitation)->not->toBeNull()
        ->and($invitation->note)->toBe('Soporte L2')
        ->and($invitation->invited_by)->toBe($admin->id);
});

test('a platform admin can revoke a pending invitation', function () {
    $admin = User::factory()->create(['hospital_id' => null, 'is_platform_admin' => true]);
    $invitation = PlatformAdminInvitation::factory()->create(['invited_by' => $admin->id]);

    Volt::actingAs($admin)->test('platform.admins.index')
        ->call('revokeInvitation', $invitation->id)
        ->assertHasNoErrors();

    expect(PlatformAdminInvitation::find($invitation->id))->toBeNull();
});

test('accepting a valid platform admin invitation creates a platform admin and logs them in', function () {
    $inviter = User::factory()->create(['hospital_id' => null, 'is_platform_admin' => true]);
    [$invitation, $plainToken] = PlatformAdminInvitation::generateFor($inviter->id, 'Test invite');

    Volt::test('platform.admin-invitations.accept', ['token' => $plainToken])
        ->assertSet('valid', true)
        ->set('name', 'New Platform Admin')
        ->set('username', 'newplatformadmin')
        ->set('email', 'newplatformadmin@example.com')
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('accept')
        ->assertHasNoErrors();

    $user = User::where('email', 'newplatformadmin@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->hospital_id)->toBeNull()
        ->and($user->is_platform_admin)->toBeTrue()
        ->and($user->hasRole('admin'))->toBeFalse();

    $invitation->refresh();
    expect($invitation->accepted_at)->not->toBeNull()
        ->and($invitation->accepted_by)->toBe($user->id);

    expect(Auth::id())->toBe($user->id);
});

test('an expired platform admin invitation token is rejected with the generic message', function () {
    $invitation = PlatformAdminInvitation::factory()->expired()->create();
    $plainToken = 'expired-platform-token';
    $invitation->update(['token' => hash('sha256', $plainToken)]);

    Volt::test('platform.admin-invitations.accept', ['token' => $plainToken])
        ->assertSet('valid', false)
        ->assertSee('no es válido o ya expiró');
});

test('accept is rejected when the platform admin invitation was used after the page loaded', function () {
    $inviter = User::factory()->create(['hospital_id' => null, 'is_platform_admin' => true]);
    [$invitation, $plainToken] = PlatformAdminInvitation::generateFor($inviter->id, 'Test invite');

    $component = Volt::test('platform.admin-invitations.accept', ['token' => $plainToken])
        ->assertSet('valid', true)
        ->set('name', 'Second Platform Admin')
        ->set('username', 'secondplatformadmin')
        ->set('email', 'secondplatformadmin@example.com')
        ->set('password', 'password')
        ->set('password_confirmation', 'password');

    // Simulate another process accepting the invitation while the form was open.
    $invitation->update(['accepted_at' => now(), 'accepted_by' => null]);

    $component->call('accept')
        ->assertSet('valid', false)
        ->assertSee('no es válido o ya expiró');

    expect(User::where('email', 'secondplatformadmin@example.com')->count())->toBe(0);
});

test('accepting the same platform admin invitation a second time with different data does not create a second user', function () {
    $inviter = User::factory()->create(['hospital_id' => null, 'is_platform_admin' => true]);
    [$invitation, $plainToken] = PlatformAdminInvitation::generateFor($inviter->id, 'Test invite');

    Volt::test('platform.admin-invitations.accept', ['token' => $plainToken])
        ->set('name', 'First Platform Admin')
        ->set('username', 'firstplatformadmin')
        ->set('email', 'firstplatformadmin@example.com')
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('accept')
        ->assertHasNoErrors();

    expect(User::where('email', 'firstplatformadmin@example.com')->count())->toBe(1);

    Volt::test('platform.admin-invitations.accept', ['token' => $plainToken])
        ->assertSet('valid', false)
        ->set('name', 'Second Attempt')
        ->set('username', 'secondattemptpa')
        ->set('email', 'secondattemptpa@example.com')
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('accept')
        ->assertSet('valid', false)
        ->assertSee('no es válido o ya expiró');

    expect(User::count())->toBe(2); // inviter + first platform admin
    expect(User::where('email', 'secondattemptpa@example.com')->count())->toBe(0);
});

test('accepting a platform admin invitation with a username or email already used by another user fails validation without marking the invitation used', function () {
    $inviter = User::factory()->create(['hospital_id' => null, 'is_platform_admin' => true]);
    User::factory()->create(['username' => 'takenpauser', 'email' => 'takenpa@example.com']);

    [$invitationUsername, $plainTokenUsername] = PlatformAdminInvitation::generateFor($inviter->id, 'Test invite');

    Volt::test('platform.admin-invitations.accept', ['token' => $plainTokenUsername])
        ->set('name', 'Duplicate Username')
        ->set('username', 'takenpauser')
        ->set('email', 'newemailpa@example.com')
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('accept')
        ->assertHasErrors(['username']);

    $invitationUsername->refresh();
    expect($invitationUsername->accepted_at)->toBeNull();

    [$invitationEmail, $plainTokenEmail] = PlatformAdminInvitation::generateFor($inviter->id, 'Test invite');

    Volt::test('platform.admin-invitations.accept', ['token' => $plainTokenEmail])
        ->set('name', 'Duplicate Email')
        ->set('username', 'newusernamepa')
        ->set('email', 'takenpa@example.com')
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('accept')
        ->assertHasErrors(['email']);

    $invitationEmail->refresh();
    expect($invitationEmail->accepted_at)->toBeNull();
});

test('a platform admin invitation token that never existed is rejected with the same generic message', function () {
    Volt::test('platform.admin-invitations.accept', ['token' => 'never-existed'])
        ->assertSet('valid', false)
        ->assertSee('no es válido o ya expiró');
});
