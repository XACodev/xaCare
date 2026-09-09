<?php

use App\Models\User;
use Laravel\Fortify\Features;
use Livewire\Volt\Volt;
use PragmaRX\Google2FA\Google2FA;

beforeEach(function () {
    if (! Features::canManageTwoFactorAuthentication()) {
        $this->markTestSkipped('Two-factor authentication is not enabled.');
    }
});

function confirmTwoFactorWithCurrentOtp(\Livewire\Features\SupportTesting\Testable $component, User $user): \Livewire\Features\SupportTesting\Testable
{
    $user->refresh();
    $code = app(Google2FA::class)->getCurrentOtp(decrypt($user->two_factor_secret));

    return $component->set('code', $code)->call('confirmTwoFactor');
}

test('confirmar 2FA redirige a platform.dashboard cuando el usuario es admin de plataforma', function () {
    $admin = User::factory()->create(['hospital_id' => null, 'is_platform_admin' => true]);

    $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()]);

    $component = Volt::test('settings.two-factor')->call('enable');

    confirmTwoFactorWithCurrentOtp($component, $admin)
        ->assertRedirect(route('platform.dashboard'));

    expect($admin->refresh()->two_factor_confirmed_at)->not->toBeNull();
});

test('confirmar 2FA no redirige para un usuario que no es admin de plataforma', function () {
    $hospitalAdmin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($hospitalAdmin)->withSession(['auth.password_confirmed_at' => time()]);

    $component = Volt::test('settings.two-factor')->call('enable');

    confirmTwoFactorWithCurrentOtp($component, $hospitalAdmin)
        ->assertNoRedirect();
});
