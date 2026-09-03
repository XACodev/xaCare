<?php

use App\Models\PlatformAdminInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('generateFor creates an invitation and returns the one-time plaintext token', function () {
    $inviter = User::factory()->create(['hospital_id' => null, 'is_platform_admin' => true]);

    [$invitation, $plainTextToken] = PlatformAdminInvitation::generateFor($inviter->id, 'Nueva admin de soporte');

    expect($invitation->exists)->toBeTrue()
        ->and($invitation->note)->toBe('Nueva admin de soporte')
        ->and($invitation->invited_by)->toBe($inviter->id)
        ->and($invitation->accepted_at)->toBeNull()
        ->and($invitation->expires_at->isFuture())->toBeTrue()
        ->and(strlen($plainTextToken))->toBe(64)
        ->and($invitation->token)->toBe(hash('sha256', $plainTextToken))
        ->and($invitation->token)->not->toBe($plainTextToken);
});

test('findValidByPlainTextToken finds a pending, unexpired invitation', function () {
    $inviter = User::factory()->create(['hospital_id' => null, 'is_platform_admin' => true]);
    [$invitation, $plainTextToken] = PlatformAdminInvitation::generateFor($inviter->id);

    $found = PlatformAdminInvitation::findValidByPlainTextToken($plainTextToken);

    expect($found)->not->toBeNull()
        ->and($found->id)->toBe($invitation->id);
});

test('findValidByPlainTextToken returns null for a token that never existed', function () {
    expect(PlatformAdminInvitation::findValidByPlainTextToken('never-existed'))->toBeNull();
});

test('findValidByPlainTextToken returns null for an expired invitation', function () {
    $invitation = PlatformAdminInvitation::factory()->expired()->create();
    $plainTextToken = 'expired-plain-token';
    $invitation->update(['token' => hash('sha256', $plainTextToken)]);

    expect(PlatformAdminInvitation::findValidByPlainTextToken($plainTextToken))->toBeNull();
});

test('findValidByPlainTextToken returns null for an already accepted invitation', function () {
    $invitation = PlatformAdminInvitation::factory()->accepted()->create();
    $plainTextToken = 'used-plain-token';
    $invitation->update(['token' => hash('sha256', $plainTextToken)]);

    expect(PlatformAdminInvitation::findValidByPlainTextToken($plainTextToken))->toBeNull();
});

test('status reflects pending, accepted and expired states', function () {
    $pending = PlatformAdminInvitation::factory()->create();
    $accepted = PlatformAdminInvitation::factory()->accepted()->create();
    $expired = PlatformAdminInvitation::factory()->expired()->create();

    expect($pending->status())->toBe('pending')
        ->and($accepted->status())->toBe('accepted')
        ->and($expired->status())->toBe('expired');
});
