<?php

use App\Models\Patient;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

use function Livewire\Volt\{state, mount, computed};

state(['q' => '']);

mount(function () {
    abort_unless(Auth::check(), 401);
    abort_unless((bool) (Auth::user()->hasRole('admin') || Auth::user()->is_platform_admin), 403);
});

$patients = computed(function () {
    return Patient::query()
        ->when($this->q, fn ($query) => $query
            ->where('primer_apellido', 'like', "%{$this->q}%")
            ->orWhere('primer_nombre', 'like', "%{$this->q}%"))
        ->orderBy('primer_apellido')
        ->limit(50)
        ->get();
});

$total = computed(fn () => Patient::query()->count());

$initials = function (\App\Models\Patient $patient): string {
    return Str::of($patient->primer_nombre.' '.$patient->primer_apellido)
        ->explode(' ')
        ->filter()
        ->map(fn ($word) => mb_substr($word, 0, 1))
        ->take(2)
        ->join('');
};

?>

<div class="max-w-5xl mx-auto p-4 space-y-4">
    <div class="flex items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Pacientes') }}</flux:heading>
            <div class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">{{ __(':count expedientes', ['count' => number_format($this->total)]) }}</div>
        </div>
        <flux:button href="{{ route('patients.create') }}" variant="primary" icon="plus">{{ __('Nuevo') }}</flux:button>
    </div>

    <flux:input wire:model.live.debounce.300ms="q" placeholder="{{ __('Buscar por nombre o apellido...') }}" icon="magnifying-glass" />

    <!-- Mobile View (Cards) -->
    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 divide-y dark:divide-zinc-700 sm:hidden">
        @forelse($this->patients as $patient)
            <div class="px-4 py-3 flex items-center gap-3">
                <span class="flex-none size-9 rounded-full bg-accent/10 text-accent flex items-center justify-center text-xs font-semibold">{{ $this->initials($patient) }}</span>
                <span class="font-medium flex-1 min-w-0 truncate">{{ $patient->nombreCompleto() }}</span>
                <flux:dropdown>
                    <flux:button size="sm" variant="ghost" icon="ellipsis-vertical" />
                    <flux:menu>
                        <flux:menu.item href="{{ route('patients.show', $patient) }}" icon="eye">
                            {{ __('Ver') }}
                        </flux:menu.item>
                    </flux:menu>
                </flux:dropdown>
            </div>
        @empty
            <div class="px-4 py-6 text-center text-sm text-zinc-500">{{ __('Sin pacientes.') }}</div>
        @endforelse
    </div>

    <!-- Desktop View (Table) -->
    <div class="hidden sm:block overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700 dark:bg-zinc-900">
        <table class="min-w-full">
            <thead>
                <tr class="qx-table-head">
                    <th scope="col" class="px-4 py-3 text-left font-semibold tracking-wider">
                        {{ __('Nombre') }}
                    </th>
                    <th scope="col" class="px-4 py-3 text-left font-semibold tracking-wider">
                        {{ __('DPI') }}
                    </th>
                    <th scope="col" class="px-4 py-3 text-left font-semibold tracking-wider">
                        {{ __('Teléfono') }}
                    </th>
                    <th scope="col" class="px-4 py-3 text-center font-semibold tracking-wider">
                        {{ __('Acciones') }}
                    </th>
                </tr>
            </thead>
            <tbody>
                @forelse($this->patients as $patient)
                    <tr class="qx-table-row">
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-zinc-900 dark:text-zinc-100">
                            <div class="flex items-center gap-3">
                                <span class="flex-none size-8 rounded-full bg-accent/10 text-accent flex items-center justify-center text-xs font-semibold">{{ $this->initials($patient) }}</span>
                                {{ $patient->nombreCompleto() }}
                            </div>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-zinc-500 dark:text-zinc-400 tabular-nums">
                            {{ $patient->dpi ?: '—' }}
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-zinc-500 dark:text-zinc-400">
                            {{ $patient->telefono ?: '—' }}
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-center">
                            <flux:dropdown>
                                <flux:button size="sm" variant="ghost" icon="ellipsis-vertical" />
                                <flux:menu>
                                    <flux:menu.item href="{{ route('patients.show', $patient) }}" icon="eye">
                                        {{ __('Ver') }}
                                    </flux:menu.item>
                                    <flux:menu.item href="{{ route('patients.edit', $patient) }}" icon="pencil">
                                        {{ __('Editar') }}
                                    </flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-6 py-8 text-center text-sm text-zinc-500 dark:text-zinc-400 italic">
                            {{ __('Sin pacientes.') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
