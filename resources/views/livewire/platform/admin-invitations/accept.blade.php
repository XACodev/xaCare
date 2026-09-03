<?php

use App\Models\PlatformAdminInvitation;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

use function Livewire\Volt\{state, mount, rules};

// Mirrors hospital-invitations.accept: single purpose, isolated flow, and the
// three failure modes (never existed / expired / already used) must stay
// indistinguishable to the caller.

state([
    'token' => '',
    'valid' => false,
    'name' => '',
    'username' => '',
    'email' => '',
    'password' => '',
    'password_confirmation' => '',
    'accepted' => false,
]);

mount(function (string $token) {
    $this->token = $token;
    $this->valid = PlatformAdminInvitation::findValidByPlainTextToken($token) !== null;
});

rules(fn () => [
    'name' => ['required', 'string', 'max:255'],
    'username' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('users', 'username')],
    'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
    'password' => ['required', 'string', 'min:6', 'confirmed'],
]);

$accept = function () {
    $invitation = PlatformAdminInvitation::findValidByPlainTextToken($this->token);

    if (! $invitation) {
        $this->valid = false;

        return;
    }

    $data = $this->validate();

    $user = null;

    DB::transaction(function () use ($invitation, $data, &$user) {
        $locked = PlatformAdminInvitation::where('id', $invitation->id)
            ->lockForUpdate()
            ->first();

        if (! $locked || $locked->accepted_at !== null || $locked->expires_at->isPast()) {
            return;
        }

        $user = User::create([
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'hospital_id' => null,
            'is_platform_admin' => true,
            'role' => 'admin',
        ]);

        $locked->forceFill([
            'accepted_at' => now(),
            'accepted_by' => $user->id,
        ])->save();
    });

    if (! $user) {
        $this->valid = false;

        return;
    }

    Auth::login($user);

    $this->accepted = true;

    $this->redirect(route('platform.dashboard'), navigate: true);
};

?>

<x-layouts.auth>
    <div class="flex flex-col gap-6">
        @if(! $valid)
            <x-auth-header :title="__('Invalid invitation')"
                description="Este enlace de invitación no es válido o ya expiró." />

            <flux:callout variant="danger" icon="exclamation-triangle"
                heading="Este enlace de invitación no es válido o ya expiró." />

            <div class="text-center text-sm">
                <flux:link :href="route('login')" wire:navigate>{{ __('Go to login') }}</flux:link>
            </div>
        @else
            <x-auth-header :title="__('Create your account')"
                :description="__('You were invited to become an Administrador de plataforma.')" />

            <form wire:submit="accept" class="flex flex-col gap-6">
                <flux:input wire:model="name" :label="__('Name')" type="text" required autofocus
                    autocomplete="name" :placeholder="__('Full Name')" />

                <flux:input wire:model="username" :label="__('Username')" type="text" required
                    autocomplete="username" :placeholder="__('Username')" />

                <flux:input wire:model="email" :label="__('Email')" type="email" required
                    autocomplete="email" placeholder="email@example.com" />

                <flux:input wire:model="password" :label="__('Password')" type="password" required
                    autocomplete="new-password" :placeholder="__('Password')" viewable />

                <flux:input wire:model="password_confirmation" :label="__('Confirm Password')" type="password"
                    required autocomplete="new-password" :placeholder="__('Confirm Password')" viewable />

                <flux:button type="submit" variant="primary" class="w-full">
                    {{ __('Create account') }}
                </flux:button>
            </form>
        @endif
    </div>
</x-layouts.auth>
