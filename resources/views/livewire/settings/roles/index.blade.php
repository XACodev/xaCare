<?php

use App\Models\Hospital;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Livewire\Volt\{state, mount};

state([
    // Create role
    'new_role' => '',

    // Lists
    'roles' => [],
    'permissions' => [],

    // Edit role
    'selected_role_id' => null,
    'selected_role_name' => '',
    'selected_permissions' => [],

    'success' => null,
]);

mount(function () {
    abort_unless(Auth::check(), 401);
    abort_if((bool) Auth::user()->is_platform_admin, 403);
    abort_unless((bool) Auth::user()->hasRole('admin'), 403);

    $this->refreshRoles();

    $this->permissions = Permission::query()
        ->where('guard_name', 'web')
        ->orderBy('name')
        ->get()
        ->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])
        ->toArray();
});

$refreshRoles = function () {
    $this->roles = Role::query()
        ->where('team_id', Auth::user()->hospital_id)
        ->where('guard_name', 'web')
        ->orderBy('name')
        ->get()
        ->map(fn ($r) => ['id' => $r->id, 'name' => $r->name])
        ->toArray();
};

$normalizeName = function (string $name): string {
    return strtolower(preg_replace('/\s+/', '_', trim($name)));
};

$assertNameIsAvailable = function (string $name, string $field, ?int $ignoreRoleId = null) {
    if ($name === '') {
        throw ValidationException::withMessages([$field => 'El nombre del rol es obligatorio.']);
    }

    if (in_array($name, Hospital::CORE_ROLES, true)) {
        throw ValidationException::withMessages([$field => 'Ese nombre está reservado para un rol del sistema.']);
    }

    $isGlobal = Role::query()
        ->where('name', $name)
        ->where('guard_name', 'web')
        ->whereNull('team_id')
        ->exists();

    if ($isGlobal) {
        throw ValidationException::withMessages([$field => 'Ese nombre ya existe como rol global.']);
    }

    $duplicateInHospital = Role::query()
        ->where('name', $name)
        ->where('guard_name', 'web')
        ->where('team_id', Auth::user()->hospital_id)
        ->when($ignoreRoleId, fn ($q) => $q->where('id', '!=', $ignoreRoleId))
        ->exists();

    if ($duplicateInHospital) {
        throw ValidationException::withMessages([$field => 'Ya existe un rol con ese nombre en tu hospital.']);
    }
};

$createRole = function () {
    abort_unless((bool) Auth::user()?->hasRole('admin') && ! Auth::user()->is_platform_admin, 403);

    $name = $this->normalizeName((string) $this->new_role);

    $this->assertNameIsAvailable($name, 'new_role');

    $role = new Role(['name' => $name, 'guard_name' => 'web']);
    $role->team_id = Auth::user()->hospital_id;
    $role->save();

    $this->new_role = '';
    $this->success = 'Rol creado.';
    $this->refreshRoles();
};

$findOwnRole = function (int $roleId): Role {
    return Role::query()
        ->where('guard_name', 'web')
        ->where('team_id', Auth::user()->hospital_id)
        ->with('permissions:id,name')
        ->findOrFail($roleId);
};

$selectRole = function (int $roleId) {
    if ($this->selected_role_id === $roleId) {
        return;
    }

    $role = $this->findOwnRole($roleId);

    $this->selected_role_id = $role->id;
    $this->selected_role_name = $role->name;
    $this->selected_permissions = $role->permissions->pluck('name')->sort()->values()->toArray();

    $this->success = null;
};

$togglePermission = function (string $name) {
    if (in_array($name, $this->selected_permissions)) {
        $this->selected_permissions = array_values(array_diff($this->selected_permissions, [$name]));
    } else {
        $this->selected_permissions[] = $name;
        sort($this->selected_permissions);
    }
};

$saveRole = function () {
    abort_unless((bool) Auth::user()?->hasRole('admin') && ! Auth::user()->is_platform_admin, 403);

    if (! $this->selected_role_id) {
        return;
    }

    $role = $this->findOwnRole((int) $this->selected_role_id);

    $newName = $this->normalizeName((string) $this->selected_role_name);

    if ($role->name !== $newName) {
        $this->assertNameIsAvailable($newName, 'selected_role_name', $role->id);
        $role->name = $newName;
        $role->save();
        $this->refreshRoles();
    }

    $allowedPermissionNames = collect($this->permissions)->pluck('name')->toArray();
    $permissionsToSync = array_values(array_intersect($this->selected_permissions, $allowedPermissionNames));

    $role->syncPermissions($permissionsToSync);

    $this->selected_role_name = $newName;
    $this->success = 'Rol actualizado.';
};

$deleteRole = function () {
    abort_unless((bool) Auth::user()?->hasRole('admin') && ! Auth::user()->is_platform_admin, 403);

    if (! $this->selected_role_id) {
        return;
    }

    $role = $this->findOwnRole((int) $this->selected_role_id);

    if ($role->users()->count() > 0) {
        throw ValidationException::withMessages(['delete' => 'No puedes eliminar un rol que tiene usuarios asignados.']);
    }

    $role->delete();

    $this->selected_role_id = null;
    $this->selected_role_name = '';
    $this->selected_permissions = [];
    $this->success = 'Rol eliminado.';
    $this->refreshRoles();
};

?>

<div class="max-w-7xl mx-auto p-4 space-y-6">
    <div class="flex flex-col gap-1">
        <flux:heading size="xl">{{ __('Roles Custom') }}</flux:heading>
        <flux:subheading>{{ __('Crea roles propios de tu hospital y asigna sus permisos.') }}</flux:subheading>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        {{-- Left Panel: Roles List & Create --}}
        <div class="lg:col-span-4 flex flex-col gap-6">
            {{-- Create Role Card --}}
            <div class="p-4 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl space-y-4">
                <flux:heading size="lg">{{ __('Crear Rol') }}</flux:heading>
                <div class="flex gap-2">
                    <flux:input wire:model="new_role" placeholder="e.g. supervisor_turno" class="flex-1" />
                    <flux:button wire:click="createRole" variant="primary">{{ __('Agregar') }}</flux:button>
                </div>
                @error('new_role') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
            </div>

            {{-- Roles List Card --}}
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl overflow-hidden flex flex-col flex-1 min-h-[300px]">
                <div class="p-4 border-b border-zinc-200 dark:border-zinc-700">
                    <flux:heading size="lg">{{ __('Roles') }}</flux:heading>
                </div>

                <div class="flex-1 overflow-y-auto p-2 space-y-1">
                    @forelse($this->roles as $r)
                        <button wire:click="selectRole({{ $r['id'] }})"
                            class="w-full text-left px-4 py-3 rounded-lg flex items-center justify-between transition group
                            {{ $selected_role_id === $r['id']
                                ? 'bg-zinc-100 dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 font-medium'
                                : 'text-zinc-600 dark:text-zinc-400 hover:bg-zinc-50 dark:hover:bg-zinc-800/50 hover:text-zinc-900 dark:hover:text-zinc-200'
                            }}">
                            <span class="font-mono text-sm truncate">{{ $r['name'] }}</span>
                            <flux:icon name="chevron-right" size="sm" class="opacity-0 group-hover:opacity-100 {{ $selected_role_id === $r['id'] ? 'opacity-100' : '' }}" />
                        </button>
                    @empty
                        <div class="p-4 text-center text-zinc-500 text-sm">{{ __('Aún no tienes roles custom.') }}</div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Right Panel: Editor --}}
        <div class="lg:col-span-8">
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl min-h-[600px] flex flex-col relative">
                @if(!$selected_role_id)
                    <div class="flex-1 flex flex-col items-center justify-center text-zinc-400 p-8">
                        <flux:icon name="shield-check" size="xl" class="mb-4 opacity-50" />
                        <p>{{ __('Selecciona un rol de la lista para editar sus permisos.') }}</p>
                    </div>
                @else
                    {{-- Header --}}
                    <div class="p-6 border-b border-zinc-200 dark:border-zinc-700 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div>
                            <flux:heading size="lg">{{ __('Editar Rol') }}: <span class="font-mono text-indigo-600 dark:text-indigo-400">{{ $selected_role_name }}</span></flux:heading>
                            <flux:subheading>{{ __('Administra el nombre y los permisos asignados.') }}</flux:subheading>
                        </div>
                        @if($success)
                            <div class="px-3 py-1 rounded-full bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400 text-xs font-medium border border-green-200 dark:border-green-800">
                                {{ $success }}
                            </div>
                        @endif
                    </div>

                    {{-- Body --}}
                    <div class="p-6 space-y-6 flex-1 overflow-y-auto mb-20">
                        <div class="max-w-md">
                            <flux:input wire:model="selected_role_name" label="{{ __('Nombre del Rol') }}" />
                            @error('selected_role_name') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                        </div>

                        <flux:separator />

                        <div>
                            <div class="flex items-center justify-between mb-4">
                                <flux:heading size="md">{{ __('Permisos') }}</flux:heading>
                                <span class="text-xs text-zinc-500">{{ count($selected_permissions) }} {{ __('seleccionados') }}</span>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                                @foreach($this->permissions as $p)
                                    @php
                                        $isSelected = in_array($p['name'], $selected_permissions);
                                    @endphp
                                    <button type="button"
                                        wire:click="togglePermission('{{ $p['name'] }}')"
                                        class="flex items-center justify-between gap-4 px-4 py-3 rounded-lg border transition text-left h-full
                                        {{ $isSelected
                                            ? 'border-zinc-900 dark:border-zinc-100 bg-zinc-50 dark:bg-zinc-800 shadow-sm'
                                            : 'border-zinc-200 dark:border-zinc-700 hover:bg-zinc-50 dark:hover:bg-zinc-800/50'
                                        }}">
                                        <span class="font-mono text-sm truncate flex-1 leading-relaxed {{ $isSelected ? 'text-zinc-900 dark:text-zinc-100 font-medium' : 'text-zinc-600 dark:text-zinc-400' }}">
                                            {{ $p['name'] }}
                                        </span>
                                        <div class="flex flex-col items-center justify-center">
                                            <span class="text-xs font-bold uppercase tracking-wider {{ $isSelected ? 'text-green-600 dark:text-green-400' : 'text-zinc-400 opacity-50' }}">
                                                {{ $isSelected ? 'ON' : 'OFF' }}
                                            </span>
                                        </div>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    {{-- Footer Actions --}}
                    <div class="absolute bottom-0 left-0 right-0 p-6 bg-white dark:bg-zinc-900 border-t border-zinc-200 dark:border-zinc-700 rounded-b-xl flex items-center justify-between gap-4">
                        <div class="flex flex-col gap-1">
                            <flux:button variant="danger" wire:click="deleteRole" icon="trash">
                                {{ __('Eliminar Rol') }}
                            </flux:button>
                            @error('delete') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                        </div>

                        <div class="flex gap-3">
                             <flux:button variant="ghost" wire:click="selectRole({{ $selected_role_id }})">
                                {{ __('Restablecer') }}
                             </flux:button>
                             <flux:button wire:click="saveRole" variant="primary">
                                {{ __('Guardar Cambios') }}
                             </flux:button>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
