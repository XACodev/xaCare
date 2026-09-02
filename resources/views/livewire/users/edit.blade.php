<?php

use App\Models\Hospital;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

use function Livewire\Volt\{state, mount, rules};

state([
    'user' => null,

    'name' => '',
    'username' => '',
    'email' => '',
    'role' => '',
    'hospital_id' => '',
    'is_super_admin' => false,
    'use_pay_scheme' => false,

    'password' => '',
    'password_confirmation' => '',

    'success_message' => null,

    'availableRoles' => [],
    'availableHospitals' => [],
]);

mount(function (string|int $user) {
    $me = Auth::user();
    abort_unless($me && ($me->is_super_admin || $me->role === 'admin'), 403);

    // TenantScope ya restringe esta consulta al hospital del admin logueado: un admin de
    // hospital que intente editar un usuario ajeno (incluido cualquier super admin, cuyo
    // hospital_id es null) recibe 404, nunca los datos de otro tenant.
    $u = User::withTrashed()->findOrFail($user);

    $this->availableRoles = Role::pluck('name', 'id')->toArray();
    $this->availableHospitals = $me->is_super_admin
        ? Hospital::orderBy('name')->pluck('name', 'id')->toArray()
        : [];

    $this->user = $u;
    $this->name = $u->name;
    $this->username = $u->username;
    $this->email = $u->email;
    $this->role = $u->getRoleNames()->first() ?? '';
    $this->hospital_id = $u->hospital_id ?? '';
    $this->is_super_admin = $u->is_super_admin;
    $this->use_pay_scheme = $u->use_pay_scheme;
});

rules(fn() => [
    'name' => ['required', 'string', 'max:255'],
    'username' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('users', 'username')->ignore($this->user->id)],
    'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->user->id)],
    'role' => ['required', 'string', 'max:50'],
    'hospital_id' => [Rule::requiredIf(! $this->is_super_admin), 'nullable', 'integer', 'exists:hospitals,id'],
    'password' => ['nullable', 'string', 'min:6', 'confirmed'],
    'is_super_admin' => ['boolean'],
    'use_pay_scheme' => ['boolean'],
    'availableRoles' => ['required', 'array'],
]);

$save = function () {
    $me = Auth::user();
    abort_unless($me && ($me->is_super_admin || $me->role === 'admin'), 403);

    // Un admin de hospital nunca puede convertir a nadie en super admin ni reasignarlo a
    // otro hospital, sin importar qué haya llegado del formulario (defensa en profundidad,
    // la vista ya oculta esos campos). También protege contra escalar el propio usuario.
    if (! $me->is_super_admin) {
        $this->is_super_admin = false;
        $this->hospital_id = $me->hospital_id;
    }

    $data = $this->validate();

    // No permite quitarse el rol admin o super admin a si mismo
    if ($me->id === $this->user->id) {
        if ($data['role'] !== 'admin') {
            abort(403, 'No puedes quitarte el rol admin.');
        }

        if ($me->is_super_admin && !$data['is_super_admin']) {
            abort(403, 'No puedes quitarte el rol super_admin.');
        }
    }

    $this->user->update([
        'name' => $data['name'],
        'username' => $data['username'],
        'email' => $data['email'],
        'role' => $data['role'],
        'hospital_id' => $data['is_super_admin'] ? null : $data['hospital_id'],
        'is_super_admin' => $data['is_super_admin'],
        'use_pay_scheme' => $data['use_pay_scheme'],
    ]);

    if (!$this->user->hasRole($data['role'])) {
        if (count($this->user->getRoleNames()) > 0) {
            foreach ($this->user->getRoleNames() as $role) {
                if ($role === 'admin') {
                    continue;
                }
                $this->user->removeRole($role);
            }
        }
        $this->user->assignRole($data['role']);
    }

    if (!empty($data['password'])) {
        $this->user->update([
            'password' => Hash::make($data['password']),
        ]);
    }

    $this->success_message = 'Usuario actualizado.';
    $this->reset(['password', 'password_confirmation']);
};

$toggleDelete = function () {
    $me = Auth::user();
    abort_unless($me && ($me->is_super_admin || $me->role === 'admin'), 403);

    if ($me->id === $this->user->id) {
        abort(403, 'No puedes desactivar tu propio usuario.');
    }

    if ($this->user->deleted_at) {
        $this->user->restore();
    } else {
        $this->user->delete();
    }

    $this->user->refresh();
};

?>

<div class="max-w-xl mx-auto p-4 space-y-6">
    <flux:button href="{{ route('users.index') }}" variant="primary" size="sm" icon="arrow-left">
        {{ __('Back') }}
    </flux:button>

    @if($success_message)
        <flux:callout variant="success" icon="check-circle" heading="{{ $success_message }}" />
    @endif

    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-6 space-y-6">
        <div class="flex items-center justify-between gap-2">
            <flux:heading size="xl">{{ __('Edit User') }}</flux:heading>
            <flux:text variant="subtle">
                {{ __('ID') }}: {{ $this->user->id ?? '' }}
            </flux:text>
        </div>
        <div class="flex items-center justify-between p-4 rounded-lg bg-zinc-50 dark:bg-zinc-800/50">
            <div class="flex items-center gap-2">
                <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('State') }}:</span>
                <flux:badge size="sm" color="{{ $this->user->deleted_at ? 'red' : 'green' }}">
                    {{ $this->user->deleted_at ? __('Deleted') : __('Active') }}
                </flux:badge>
            </div>

            <flux:button wire:click="toggleDelete" wire:confirm="{{ __('Are you sure?') }}" size="sm"
                variant="{{ $this->user->deleted_at ? 'filled' : 'danger' }}">
                {{ $this->user->deleted_at ? __('Restore') : __('Delete') }}
            </flux:button>
        </div>

        <flux:input wire:model="name" label="{{ __('Name') }}" />

        <flux:input wire:model="username" label="{{ __('Username') }}" />

        <flux:input wire:model="email" type="email" label="{{ __('Email') }}" />

        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">{{ __('Role') }}</label>
        <select wire:model="role"
            class="w-full rounded-lg border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-zinc-900 dark:text-zinc-200 focus:ring-0 focus:border-zinc-500 p-2.5">
            <option value="">-- {{ __('Select') }} --</option>
            @foreach($availableRoles as $id => $role)
                <option value="{{ $role }}">{{ $role }}</option>
            @endforeach
        </select>

        @if(Auth::user()->is_super_admin && !$is_super_admin)
            <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">{{ __('Hospital') }}</label>
            <select wire:model="hospital_id"
                class="w-full rounded-lg border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-zinc-900 dark:text-zinc-200 focus:ring-0 focus:border-zinc-500 p-2.5">
                <option value="">-- {{ __('Select') }} --</option>
                @foreach($availableHospitals as $id => $hospitalName)
                    <option value="{{ $id }}">{{ $hospitalName }}</option>
                @endforeach
            </select>
            @error('hospital_id') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
        @endif

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            @if(Auth::user()->is_super_admin)
                <flux:checkbox wire:model.live="is_super_admin" label="{{ __('Super Admin (platform-only, read-only across hospitals)') }}" />
            @endif
            <flux:checkbox wire:model.live="use_pay_scheme" label="{{ __('Use Pay Scheme') }}" />
        </div>

        <flux:separator />

        <div>
            <flux:heading size="lg" class="mb-4">{{ __('Change Password (optional)') }}</flux:heading>
            <div class="space-y-4">
                <flux:input wire:model.live="password" type="password" label="{{ __('New Password') }}" />
                <flux:input wire:model.live="password_confirmation" type="password"
                    label="{{ __('Confirm Password') }}" />
            </div>
        </div>

        <div class="pt-2 flex justify-end">
            <flux:button variant="primary" wire:click="save" class="w-full sm:w-auto">
                {{ __('Save Changes') }}
            </flux:button>
        </div>
    </div>
</div>