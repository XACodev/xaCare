<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Livewire\Volt\{state, mount, computed, layout};

layout('components.layouts.platform');

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
    abort_unless(Auth::check() && Auth::user()->is_platform_admin, 403);

    $this->roles = Role::query()
        ->where('guard_name', 'web')
        ->whereNull('team_id')
        ->orderBy('name')
        ->get()
        ->map(fn($r) => ['id' => $r->id, 'name' => $r->name])
        ->toArray();

    $this->permissions = Permission::query()
        ->where('guard_name', 'web')
        ->orderBy('name')
        ->get()
        ->map(fn($p) => ['id' => $p->id, 'name' => $p->name])
        ->toArray();
});

// Refresh roles list
$refreshRoles = function () {
    $this->roles = Role::query()
        ->where('guard_name', 'web')
        ->whereNull('team_id')
        ->orderBy('name')
        ->get()
        ->map(fn($r) => ['id' => $r->id, 'name' => $r->name])
        ->toArray();
};

$createRole = function () {
    abort_unless((bool) Auth::user()?->is_platform_admin, 403);

    $name = trim((string) $this->new_role);
    if ($name === '') {
        throw ValidationException::withMessages(['new_role' => 'Role name is required.']);
    }

    $name = strtolower(preg_replace('/\s+/', '_', $name));

    Role::findOrCreate($name, 'web');

    $this->new_role = '';
    $this->success = 'Role created.';
    $this->refreshRoles();
};

$selectRole = function (int $roleId) {
    if ($this->selected_role_id === $roleId) {
        return; // Already selected
    }

    $role = Role::query()
        ->where('guard_name', 'web')
        ->whereNull('team_id')
        ->with('permissions:id,name')
        ->findOrFail($roleId);

    $this->selected_role_id = $role->id;
    $this->selected_role_name = $role->name;
    // Sort permissions so they are consistent
    $this->selected_permissions = $role->permissions->pluck('name')->sort()->values()->toArray();

    $this->success = null;
};

$togglePermission = function (string $name) {
    if (in_array($name, $this->selected_permissions)) {
        // Remove
        $this->selected_permissions = array_values(array_diff($this->selected_permissions, [$name]));
    } else {
        // Add
        $this->selected_permissions[] = $name;
        sort($this->selected_permissions);
    }
};

$saveRole = function () {
    abort_unless((bool) Auth::user()?->is_platform_admin, 403);

    if (!$this->selected_role_id) {
        return;
    }

    $role = Role::query()
        ->where('guard_name', 'web')
        ->whereNull('team_id')
        ->findOrFail((int) $this->selected_role_id);

    // Rename
    $newName = trim((string) $this->selected_role_name);
    if ($newName === '') {
        throw ValidationException::withMessages(['selected_role_name' => 'Role name is required.']);
    }
    $newName = strtolower(preg_replace('/\s+/', '_', $newName));
    
    if ($role->name !== $newName) {
        $role->name = $newName;
        $role->save();
        $this->refreshRoles();
    }

    // Sync permissions
    $role->syncPermissions($this->selected_permissions);

    $this->success = 'Role updated.';
};

$deleteRole = function () {
    abort_unless((bool) Auth::user()?->is_platform_admin, 403);

    if (!$this->selected_role_id) return;

    $role = Role::query()
        ->where('guard_name', 'web')
        ->whereNull('team_id')
        ->findOrFail((int) $this->selected_role_id);

    $role->delete();

    $this->selected_role_id = null;
    $this->selected_role_name = '';
    $this->selected_permissions = [];
    $this->success = 'Role deleted.';
    $this->refreshRoles();
};

?>

<div class="max-w-7xl mx-auto p-4 space-y-6">
    <div class="flex flex-col gap-1">
        <flux:heading size="xl">Access Roles</flux:heading>
        <flux:subheading>Create roles and assign permissions.</flux:subheading>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        {{-- Left Panel: Roles List & Create --}}
        <div class="lg:col-span-4 flex flex-col gap-6">
            {{-- Create Role Card --}}
            <div class="p-4 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl space-y-4">
                <flux:heading size="lg">Create Role</flux:heading>
                <div class="flex gap-2">
                    <flux:input wire:model="new_role" placeholder="e.g. manager" class="flex-1" />
                    <flux:button wire:click="createRole" variant="primary">Add</flux:button>
                </div>
                @error('new_role') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
            </div>

            {{-- Roles List Card --}}
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl overflow-hidden flex flex-col flex-1 min-h-[300px]">
                <div class="p-4 border-b border-zinc-200 dark:border-zinc-700">
                    <flux:heading size="lg">Roles List</flux:heading>
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
                        <div class="p-4 text-center text-zinc-500 text-sm">No roles found.</div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Right Panel: Editor --}}
        <div class="lg:col-span-8">
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl min-h-[600px] flex flex-col relative">
                @if(!$selected_role_id)
                    <div class="flex-1 flex flex-col items-center justify-center text-zinc-400 p-8">
                        <flux:icon name="user-group" size="xl" class="mb-4 opacity-50" />
                        <p>Select a role from the list to edit permissions.</p>
                    </div>
                @else
                    {{-- Header --}}
                    <div class="p-6 border-b border-zinc-200 dark:border-zinc-700 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div>
                            <flux:heading size="lg">Edit Role: <span class="font-mono text-indigo-600 dark:text-indigo-400">{{ $selected_role_name }}</span></flux:heading>
                            <flux:subheading>Manage name and assigned permissions.</flux:subheading>
                        </div>
                        @if($success)
                            <div class="px-3 py-1 rounded-full bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400 text-xs font-medium border border-green-200 dark:border-green-800">
                                {{ $success }}
                            </div>
                        @endif
                    </div>

                    {{-- Body --}}
                    <div class="p-6 space-y-6 flex-1 overflow-y-auto mb-20"> {{-- mb-20 ensures space for footer --}}
                        <div class="max-w-md">
                            <flux:input wire:model="selected_role_name" label="Role Name" />
                        </div>

                        <flux:separator />

                        <div>
                            <div class="flex items-center justify-between mb-4">
                                <flux:heading size="md">Permissions</flux:heading>
                                <span class="text-xs text-zinc-500">{{ count($selected_permissions) }} selected</span>
                            </div>
                            
                            {{-- Permissions Grid (Button Style) --}}
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
                        <flux:button variant="danger" wire:click="deleteRole" icon="trash">
                            Delete Role
                        </flux:button>
                        
                        <div class="flex gap-3">
                             <flux:button variant="ghost" wire:click="selectRole({{ $selected_role_id }})">
                                Reset
                             </flux:button>
                             <flux:button wire:click="saveRole" variant="primary">
                                Save Changes
                             </flux:button>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>