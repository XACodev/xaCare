<?php

use App\Models\Hospital;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

use function Livewire\Volt\{state, mount, rules};

state([
    'name' => '',
    'username' => '',
    'email' => '',
    'role' => '',
    'hospital_id' => '',
    'password' => '',
    'password_confirmation' => '',
    'is_super_admin' => false,
    'use_pay_scheme' => false,
    'phone' => '',
    'success_message' => null,
    'availableRoles' => [],
    'availableHospitals' => [],
]);

mount(function () {
    $u = Auth::user();
    if (!$u || !$u->is_super_admin)
        abort(403);

    $this->availableRoles = Role::pluck('name', 'id')->toArray();
    $this->availableHospitals = Hospital::orderBy('name')->pluck('name', 'id')->toArray();
});

rules(fn () => [
    'name' => ['required', 'string', 'max:255'],
    'username' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('users', 'username')],
    'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
    'role' => ['required', 'string', 'max:50'],
    'hospital_id' => [Rule::requiredIf(! $this->is_super_admin), 'nullable', 'integer', 'exists:hospitals,id'],
    'availableRoles' => ['required', 'array'],
    'password' => ['required', 'string', 'min:6', 'confirmed'],
    'is_super_admin' => ['boolean'],
    'use_pay_scheme' => ['boolean'],
    'phone' => ['nullable', 'string', 'max:8'],
]);


$save = function () {
    $me = Auth::user();
    if (!$me && !$me->is_super_admin)
        abort(403);

    $data = $this->validate();

    $u = User::create([
        'name' => $data['name'],
        'username' => $data['username'],
        'email' => $data['email'],
        'password' => Hash::make($data['password']),
        'hospital_id' => $data['is_super_admin'] ? null : $data['hospital_id'],
        'is_super_admin' => $data['is_super_admin'],
        'use_pay_scheme' => $data['use_pay_scheme'],
        'phone' => $data['phone'],
        'role' => $data['role']
    ]);

    $u->assignRole($data['role']);

    $this->success_message = __('User created.');

    $this->reset([
        'name',
        'username',
        'email',
        'phone',
        'role',
        'hospital_id',
        'availableRoles',
        'password',
        'password_confirmation',
        'is_super_admin',
        'use_pay_scheme'
    ]);
};
?>

<div class="max-w-2xl mx-auto p-4 space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('New User') }}</flux:heading>
            <flux:subheading>{{ __('Only Super Admin') }}</flux:subheading>
        </div>
        <flux:link href="{{ route('users.index') }}" class="text-sm">{{ __('Back') }}</flux:link>
    </div>

    @if($success_message)
        <flux:callout variant="success" icon="check-circle" heading="{{ $success_message }}" />
    @endif

    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-6 space-y-6">
        <flux:input wire:model.live="name" label="{{ __('Name') }}" placeholder="{{ __('Full Name') }}" />

        <flux:input wire:model.live="username" label="{{ __('Username') }}" placeholder="{{ __('Username') }}" />

        <flux:input wire:model.live="email" type="email" label="{{ __('Email') }}" placeholder="{{ __('Email') }}" />

        <flux:input wire:model.live="phone" label="{{ __('Phone') }}" placeholder="{{ __('Phone') }}" />

        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">{{ __('Role') }}</label>
        <select wire:model.live="role"
            class="w-full rounded-lg border-zinc-200 dark:border-zinc-800 bg-indigo-50 dark:bg-zinc-700/60 text-zinc-900 dark:text-zinc-100 focus:ring-0 focus:border-zinc-500 p-2.5">
            <option value="">-- {{ __('Select') }} --</option>
            @if($availableRoles)
                @forelse($availableRoles as $id => $role)
                    <option value="{{ $role }}">{{ $role }}</option>
                @empty
                    <option value="">{{ __('No roles found.') }}</option>
                @endforelse
            @endif
        </select>

        @unless($is_super_admin)
            <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">{{ __('Hospital') }}</label>
            <select wire:model.live="hospital_id"
                class="w-full rounded-lg border-zinc-200 dark:border-zinc-800 bg-indigo-50 dark:bg-zinc-700/60 text-zinc-900 dark:text-zinc-100 focus:ring-0 focus:border-zinc-500 p-2.5">
                <option value="">-- {{ __('Select') }} --</option>
                @forelse($availableHospitals as $id => $hospitalName)
                    <option value="{{ $id }}">{{ $hospitalName }}</option>
                @empty
                    <option value="">{{ __('No hospitals found.') }}</option>
                @endforelse
            </select>
            @error('hospital_id') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
        @endunless

        <flux:input wire:model.live="password" type="password" label="{{ __('Password') }}"
            placeholder="{{ __('Password') }}" />

        <flux:input wire:model.live="password_confirmation" type="password" label="{{ __('Confirm Password') }}"
            placeholder="{{ __('Confirm Password') }}" />

        <flux:checkbox wire:model.live="is_super_admin" label="{{ __('Super Admin (platform-only, read-only across hospitals)') }}" />

        <flux:checkbox wire:model.live="use_pay_scheme" label="{{ __('Use Pay Scheme') }}" />

        <div class="pt-2 flex justify-end">
            <flux:button variant="primary" wire:click="save" class="w-full sm:w-auto">
                {{ __('Save') }}
            </flux:button>
        </div>
    </div>
</div>