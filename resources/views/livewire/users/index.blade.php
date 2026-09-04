<?php

use App\Models\Hospital;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;

use function Livewire\Volt\{state, computed, mount};

state([
    'q' => '',
    'role' => '',
    'show_deleted' => false,
    'rolesAvailable' => [],
]);

mount(function () {
    // Solo admin de hospital: el super admin gestiona el staff desde la ficha de cada
    // hospital (Hospitales > editar > Staff), no desde una lista global — evita mezclar
    // el personal de todos los hospitales en una sola tabla.
    $u = Auth::user();
    abort_unless($u && ! $u->is_platform_admin && $u->role === 'admin', 403);
    $this->rolesAvailable = Role::whereIn('name', $u->hospital?->visibleRoleNames() ?? Hospital::CORE_ROLES)
        ->orderBy('name')
        ->pluck('name', 'id')
        ->toArray();
});

$users = computed(function () {
    // TenantScope filtra automaticamente a "solo mi hospital". Nunca incluye cuentas
    // super admin (hospital_id null no coincide con el filtro).
    $query = User::query()->where('is_platform_admin', false)->orderBy('name');

    if ($this->show_deleted) {
        $query->onlyTrashed();
    }

    if ($this->q) {
        $q = trim($this->q);
        $query->where(function ($sub) use ($q) {
            $sub->where('name', 'like', "%{$q}%")
                ->orWhere('username', 'like', "%{$q}%")
                ->orWhere('email', 'like', "%{$q}%");
        });
    }

    if ($this->role) {
        $query->role($this->role);
    }

    return $query->limit(150)->get(['id', 'name', 'username', 'email', 'role', 'deleted_at']);
});

$groupedUsers = computed(function () {
    $available = $this->rolesAvailable;
    if (! is_array($available) || empty($available)) {
        $available = Hospital::CORE_ROLES;
    }

    // Inicializar un grupo por cada rol disponible, incluso si está vacío.
    $groups = [];
    foreach ($available as $roleName) {
        $groups[$roleName] = [];
    }

    foreach ($this->users as $u) {
        $r = $u->getRoleNames()->first() ?? 'unknown';
        if (! array_key_exists($r, $groups)) {
            $groups[$r] = [];
        }
        $groups[$r][] = $u;
    }

    // Ordenar: admin primero, luego el resto alfabéticamente.
    $ordered = [];
    if (array_key_exists('admin', $groups)) {
        $ordered['admin'] = $groups['admin'];
        unset($groups['admin']);
    }
    ksort($groups);

    return array_merge($ordered, $groups);
});

$visibleGroups = computed(function () {
    if ($this->role === '') {
        return $this->groupedUsers;
    }

    return array_filter($this->groupedUsers, fn ($roleName) => $roleName === $this->role, ARRAY_FILTER_USE_KEY);
});

$roleCount = function (string $roleName) {
    return count($this->groupedUsers[$roleName] ?? []);
};

$deleteUser = function (int $id) {
    $me = Auth::user();
    abort_unless(! $me->is_platform_admin && $me->role === 'admin', 403);

    if ($me->id === $id) {
        abort(403, 'No puedes eliminar tu propio usuario.');
    }

    // TenantScope ya limita esta consulta al hospital del admin logueado — no puede
    // alcanzar un usuario ajeno por id.
    $u = User::findOrFail($id);
    abort_if($u->is_platform_admin, 404);
    $u->delete();
};

$restoreUser = function (int $id) {
    $me = Auth::user();
    abort_unless(! $me->is_platform_admin && $me->role === 'admin', 403);

    $u = User::onlyTrashed()->findOrFail($id);
    abort_if($u->is_platform_admin, 404);
    $u->restore();
};

$roleColor = function (?string $role) {
    return match ($role) {
        'admin' => 'indigo',
        'doctor' => 'emerald',
        'instrumentist' => 'violet',
        'circulating' => 'amber',
        default => 'zinc',
    };
};

?>

<div class="max-w-5xl mx-auto p-4 space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">
                {{ __('Mi Staff') }}
            </flux:heading>
            <flux:subheading>
                {{ __('Usuarios de tu hospital') }}
            </flux:subheading>
        </div>

        <flux:button href="{{ route('users.create') }}" icon="plus" class="w-full sm:w-auto" variant="primary">
            {{ __('New User') }}
        </flux:button>
    </div>

    <div class="space-y-4">
        {{-- Search + show deleted --}}
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="md:col-span-2">
                <flux:input icon="magnifying-glass" wire:model.live="q"
                    placeholder="{{ __('Search name, username or email...') }}" />
            </div>

            <div class="flex items-center md:col-span-2">
                <flux:checkbox wire:model.live="show_deleted" label="{{ __('Show deleted') }}" />
            </div>
        </div>

        {{-- Role chips --}}
        <div class="flex flex-wrap gap-2">
            <button type="button" wire:click="$set('role', '')"
                class="px-3 py-1.5 rounded-full text-sm font-medium transition border
                    {{ $role === ''
                        ? 'bg-indigo-600 text-white border-indigo-600'
                        : 'bg-white dark:bg-zinc-900 text-zinc-700 dark:text-zinc-300 border-zinc-200 dark:border-zinc-700 hover:border-zinc-400 dark:hover:border-zinc-500'
                    }}">
                {{ __('All') }}
                <span class="ml-1 opacity-80">({{ count($this->users) }})</span>
            </button>

            @foreach($this->rolesAvailable as $roleName)
                <button type="button" wire:click="$set('role', '{{ $roleName }}')"
                    class="px-3 py-1.5 rounded-full text-sm font-medium transition border capitalize
                        {{ $role === $roleName
                            ? 'bg-' . $this->roleColor($roleName) . '-600 text-white border-' . $this->roleColor($roleName) . '-600'
                            : 'bg-white dark:bg-zinc-900 text-zinc-700 dark:text-zinc-300 border-zinc-200 dark:border-zinc-700 hover:border-zinc-400 dark:hover:border-zinc-500'
                        }}">
                    {{ ucfirst(__($roleName)) }}
                    <span class="ml-1 opacity-80">({{ $this->roleCount($roleName) }})</span>
                </button>
            @endforeach
        </div>

        {{-- Grouped user lists --}}
        @forelse($this->visibleGroups as $groupRole => $groupUsers)
            <div class="space-y-3">
                <div class="flex items-center gap-3">
                    <flux:badge size="lg" color="{{ $this->roleColor($groupRole) }}" class="capitalize">
                        {{ ucfirst(__($groupRole)) }}
                    </flux:badge>
                    <span class="text-sm text-zinc-500 dark:text-zinc-400">
                        {{ count($groupUsers) }} {{ count($groupUsers) === 1 ? __('user') : __('users') }}
                    </span>
                </div>

                <!-- Mobile View (Cards) -->
                <div class="grid grid-cols-1 gap-4 sm:hidden">
                    @forelse($groupUsers as $u)
                        <div
                            class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 shadow-sm space-y-3">
                            <div class="flex justify-between items-start">
                                <div>
                                    <div class="font-medium text-zinc-900 dark:text-zinc-100 capitalize">{{ $u->name }}</div>
                                    <div class="text-sm text-zinc-500">{{ $u->email }}</div>
                                    <div class="text-xs text-zinc-400 font-mono mt-0.5">{{ $u->username }}</div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <flux:dropdown>
                                        <flux:button size="sm" variant="primary" icon="ellipsis-vertical" />
                                        <flux:menu>
                                            @if (!$u->deleted_at)
                                                <flux:menu.item href="{{ route('users.edit', $u->id) }}" icon="pencil">
                                                    {{ __('Edit') }}
                                                </flux:menu.item>
                                                <flux:menu.separator />
                                                <flux:menu.item wire:click="deleteUser({{ $u->id }})"
                                                    wire:confirm="{{ __('Delete this user? (can be restored)') }}" variant="danger"
                                                    icon="trash">
                                                    {{ __('Delete') }}
                                                </flux:menu.item>
                                            @endif
                                            @if ($u->deleted_at)
                                                <flux:menu.item wire:click="restoreUser({{ $u->id }})"
                                                    wire:confirm="{{ __('Restore this user?') }}" icon="arrow-uturn-left">
                                                    {{ __('Restore') }}
                                                </flux:menu.item>
                                            @endif
                                        </flux:menu>
                                    </flux:dropdown>
                                </div>
                            </div>

                            <div class="flex items-center justify-between gap-2 text-sm text-zinc-600 dark:text-zinc-400">
                                <flux:badge size="sm" color="{{ $u->deleted_at ? 'red' : 'green' }}" class="capitalize">
                                    {{ $u->deleted_at ? __('Deleted') : __('Active') }}
                                </flux:badge>
                            </div>
                        </div>
                    @empty
                        <div class="p-4 text-center text-zinc-500 dark:text-zinc-400 italic">
                            {{ __('No users in this role.') }}
                        </div>
                    @endforelse
                </div>

                <!-- Desktop View (Table) -->
                <div class="hidden sm:block overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
                    <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                        <thead class="bg-zinc-50 dark:bg-zinc-800/50">
                            <tr>
                                <th scope="col" class="px-4 py-4 text-left text-xs font-semibold text-zinc-500 tracking-wider">
                                    <flux:label> {{ __('Name') }} </flux:label>
                                </th>
                                <th scope="col" class="px-4 py-4 text-left text-xs font-semibold text-zinc-500 tracking-wider">
                                    <flux:label> {{ __('Username') }} </flux:label>
                                </th>
                                <th scope="col" class="px-4 py-4 text-left text-xs font-semibold text-zinc-500 tracking-wider">
                                    <flux:label> {{ __('Email') }} </flux:label>
                                </th>
                                <th scope="col"
                                    class="px-4 py-4 text-center text-xs font-semibold text-zinc-500 tracking-wider">
                                    <flux:label> {{ __('Status') }} </flux:label>
                                </th>
                                <th scope="col"
                                    class="px-4 py-4 text-center text-xs font-semibold text-zinc-500 tracking-wider">
                                    <flux:label> {{ __('Actions') }} </flux:label>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-zinc-900 divide-y divide-zinc-200 dark:divide-zinc-700">
                            @forelse($groupUsers as $u)
                                <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors">
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-zinc-900 dark:text-zinc-100">
                                        {{ $u->name }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-zinc-500 dark:text-zinc-400">
                                        {{ $u->username }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-zinc-500 dark:text-zinc-400">{{ $u->email }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-center">
                                        <flux:badge size="sm" color="{{ $u->deleted_at ? 'red' : 'green' }}">
                                            {{ $u->deleted_at ? __('Deleted') : __('Active') }}
                                        </flux:badge>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-center text-sm space-x-2">
                                        @if(!$u->deleted_at)
                                            <flux:button href="{{ route('users.edit', $u->id) }}" size="sm" variant="primary"
                                                icon="pencil" color="indigo" />
                                            <flux:button wire:click="deleteUser({{ $u->id }})"
                                                wire:confirm="{{ __('Delete this user? (can be restored)') }}" size="sm"
                                                variant="danger" icon="trash" class="cursor-pointer" />
                                        @else
                                            <flux:button wire:click="restoreUser({{ $u->id }})" size="sm" variant="primary"
                                                icon="arrow-uturn-left" tooltip="{{ __('Restore') }}" color="green"
                                                class="cursor-pointer" />
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-8 text-center text-sm text-zinc-500 dark:text-zinc-400 italic">
                                        {{ __('No users in this role.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @empty
            <div class="p-8 text-center rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900">
                <p class="text-zinc-500 dark:text-zinc-400 italic">{{ __('No users.') }}</p>
            </div>
        @endforelse

        <div class="px-4 text-xs text-zinc-500 dark:text-zinc-400 text-center sm:text-left">
            {{ __('Maximum 150 records.') }}
        </div>
    </div>
</div>
