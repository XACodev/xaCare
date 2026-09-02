<?php

use App\Models\HospitalInvitation;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

use function Livewire\Volt\{state, mount, rules};

// This route/component has a single purpose: let someone WITHOUT an account
// use a one-time invitation link to create their own admin account for the
// hospital named on the invitation. It must stay isolated from every other
// business flow, and must never reveal *why* a token didn't work — "doesn't
// exist", "expired" and "already used" all look identical to the caller.

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
    $this->valid = HospitalInvitation::findValidByPlainTextToken($token) !== null;
});

rules(fn () => [
    'name' => ['required', 'string', 'max:255'],
    'username' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('users', 'username')],
    'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
    'password' => ['required', 'string', 'min:6', 'confirmed'],
]);

$accept = function () {
    // Re-check validity at submit time too (the link may have been used or
    // expired between page load and form submission).
    $invitation = HospitalInvitation::findValidByPlainTextToken($this->token);

    if (! $invitation) {
        $this->valid = false;

        return;
    }

    $data = $this->validate();

    $user = User::create([
        'name' => $data['name'],
        'username' => $data['username'],
        'email' => $data['email'],
        'password' => Hash::make($data['password']),
        // hospital_id ALWAYS comes from the invitation record, never from
        // the submitted form — this is what makes it impossible for
        // whoever accepts the link to choose which hospital they join.
        'hospital_id' => $invitation->hospital_id,
        'is_super_admin' => false,
        'role' => 'admin',
    ]);

    $user->assignRole('admin');

    $invitation->forceFill([
        'accepted_at' => now(),
        'accepted_by' => $user->id,
    ])->save();

    Auth::login($user);

    $this->accepted = true;

    $this->redirect(route('dashboard'), navigate: true);
};

?>

<x-layouts.auth>
    <div class="flex flex-col gap-6">
        @if(! $valid)
            {{-- Deliberately the SAME literal message for all 3 failure modes
                 (token never existed / expired / already used) so the wording
                 can never be used to enumerate valid-but-unused invitations. --}}
            <x-auth-header :title="__('Invalid invitation')"
                description="Este enlace de invitación no es válido o ya expiró." />

            <flux:callout variant="danger" icon="exclamation-triangle"
                heading="Este enlace de invitación no es válido o ya expiró." />

            <div class="text-center text-sm">
                <flux:link :href="route('login')" wire:navigate>{{ __('Go to login') }}</flux:link>
            </div>
        @else
            <x-auth-header :title="__('Create your account')"
                :description="__('You were invited to set up the admin account for your hospital.')" />

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
