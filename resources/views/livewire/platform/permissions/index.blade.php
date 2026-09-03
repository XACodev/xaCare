<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;

use function Livewire\Volt\{state, mount, computed, layout};

layout('components.layouts.platform');

state([
    'name' => '',
    'permissions' => [],
    'confirm_delete' => null, // id del permiso a borrar (opcional)
]);

mount(function () {
    $this->permissions = Permission::query()
        ->where('guard_name', 'web')
        ->orderBy('name')
        ->get()
        ->toArray();
});

$permissionsList = computed(function () {
    return Permission::query()
        ->where('guard_name', 'web')
        ->orderBy('name')
        ->get()
        ->toArray();
});

$create = function () {
    $name = trim((string) $this->name);
    
    if ($name === '') {
        throw ValidationException::withMessages(['name' => 'Permission name is required.']);
    }

    // Normaliza: lowercase + sin espacios raros
    $name = strtolower(preg_replace('/\s+/', '_', $name));

    Permission::findOrCreate($name, 'web');

    $this->name = '';
    $this->dispatch('permission-created'); 
    
    // Refresh list
    $this->permissions = $this->permissionsList();
};

$delete = function (int $id) {
    if ($this->confirm_delete !== $id) {
        $this->confirm_delete = $id;
        return;
    }

    $perm = Permission::query()->where('guard_name', 'web')->findOrFail($id);

    // Evitar borrar permisos en uso
    $rolesCount = method_exists($perm, 'roles') ? $perm->roles()->count() : 0;

    if ($rolesCount > 0) {
        throw ValidationException::withMessages([
            'delete' => "Can't delete: assigned to {$rolesCount} role(s). Remove it from roles first.",
        ]);
    }

    $perm->delete();

    $this->confirm_delete = null;
    $this->permissions = $this->permissionsList();
    $this->dispatch('permission-deleted');
};

$cancelDelete = function () {
    $this->confirm_delete = null;
};

?>

<div class="max-w-5xl mx-auto p-4 space-y-6">
    <div class="flex flex-col gap-1">
        <flux:heading size="xl">Access Permissions</flux:heading>
        <flux:subheading>Create and manage system permissions.</flux:subheading>
    </div>

    <div class="grid gap-6">
        <div class="p-4 sm:p-6 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl">
            <div class="flex flex-col sm:flex-row items-start sm:items-end gap-3">
                <flux:input wire:model="name" label="New Permission" placeholder="e.g. payouts.manage" class="w-full sm:flex-1" />
                
                <flux:button wire:click="create" variant="primary" class="w-full sm:w-auto">
                    Create Permission
                </flux:button>
            </div>
        </div>

        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl overflow-hidden">
            <div class="p-4 border-b border-zinc-200 dark:border-zinc-700 flex justify-between items-center">
                <flux:heading size="lg">All Permissions</flux:heading>
                <flux:subheading>{{ count($this->permissions) }} total</flux:subheading>
            </div>
            
            @error('delete')
                <div class="p-4 bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400 text-sm">
                    {{ $message }}
                </div>
            @enderror

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm text-left">
                    <thead class="bg-zinc-50 dark:bg-zinc-800/50 text-zinc-500 dark:text-zinc-400">
                        <tr>
                            <th class="px-6 py-3 font-medium">Name</th>
                            <th class="px-6 py-3 font-medium text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @forelse($this->permissions as $p)
                            <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition">
                                <td class="px-6 py-3 font-mono text-zinc-900 dark:text-zinc-100">
                                    {{ $p['name'] }}
                                </td>
                                <td class="px-6 py-3 text-right">
                                    <div class="flex justify-end items-center gap-2">
                                        @if($confirm_delete === (int) $p['id'])
                                            <span class="text-xs text-red-500 font-medium">Are you sure?</span>
                                            <flux:button size="sm" variant="danger" wire:click="delete({{ (int) $p['id'] }})">
                                                Yes, Delete
                                            </flux:button>
                                            <flux:button size="sm" variant="ghost" wire:click="cancelDelete">
                                                Cancel
                                            </flux:button>
                                        @else
                                            <flux:button size="sm" variant="subtle" wire:click="delete({{ (int) $p['id'] }})" icon="trash">
                                                Delete
                                            </flux:button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" class="px-6 py-8 text-center text-zinc-500 dark:text-zinc-400">
                                    No permissions found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>