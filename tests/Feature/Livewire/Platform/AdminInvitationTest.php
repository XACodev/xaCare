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

test('a platform admin invitation token that never existed is rejected with the same generic message', function () {
    Volt::test('platform.admin-invitations.accept', ['token' => 'never-existed'])
        ->assertSet('valid', false)
        ->assertSee('no es válido o ya expiró');
});
