<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;

use function Livewire\Volt\{state, mount};

state(['user' => null]);

mount(function (string $user) {
    $me = Auth::user();
    abort_unless($me && ($me->is_platform_admin || $me->hasRole('admin')), 403);

    // Se busca por `slug` con withTrashed(): el binding implicito por defecto excluye
    // usuarios con soft delete y devolvia 404 al ver el perfil de un usuario eliminado.
    // TenantScope ya restringe esta consulta al hospital del admin logueado: un admin de
    // hospital que intente ver un usuario ajeno recibe 404, nunca los datos de otro tenant.
    $u = User::withTrashed()->where('slug', $user)->firstOrFail();
    abort_if($u->is_platform_admin, 404);

    $this->user = $u;
});

?>

<div class="max-w-xl mx-auto p-4 space-y-6">
    <div class="flex items-center justify-between">
        <flux:button href="{{ route('users.index') }}" variant="primary" size="sm" icon="arrow-left">
            {{ __('Back') }}
        </flux:button>

        <flux:button href="{{ route('users.edit', $user) }}" variant="primary" size="sm" icon="pencil">
            {{ __('Edit') }}
        </flux:button>
    </div>

    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-6 space-y-6">
        <div class="flex items-center justify-between gap-2">
            <flux:heading size="xl">{{ $user->name }}</flux:heading>
            <flux:badge size="sm" color="{{ $user->deleted_at ? 'red' : 'green' }}">
                {{ $user->deleted_at ? __('Deleted') : __('Active') }}
            </flux:badge>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <flux:label>{{ __('Username') }}</flux:label>
                <p class="text-sm text-zinc-900 dark:text-zinc-100 font-mono">{{ $user->username }}</p>
            </div>
            <div>
                <flux:label>{{ __('Email') }}</flux:label>
                <p class="text-sm text-zinc-900 dark:text-zinc-100">{{ $user->email }}</p>
            </div>
            <div>
                <flux:label>{{ __('Phone') }}</flux:label>
                <p class="text-sm text-zinc-900 dark:text-zinc-100">{{ $user->phone ?: '—' }}</p>
            </div>
            <div>
                <flux:label>{{ __('Role') }}</flux:label>
                <p class="text-sm text-zinc-900 dark:text-zinc-100 capitalize">{{ $user->getRoleNames()->first() ?? '—' }}</p>
            </div>
        </div>
    </div>
</div>
