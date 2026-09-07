<?php

use App\Models\Patient;
use Illuminate\Support\Facades\Auth;

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

?>

<div class="max-w-5xl mx-auto p-4 space-y-4">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">{{ __('Pacientes') }}</flux:heading>
        <flux:button href="{{ route('patients.create') }}" variant="primary" icon="plus">{{ __('Nuevo') }}</flux:button>
    </div>

    <flux:input wire:model.live.debounce.300ms="q" placeholder="{{ __('Buscar por nombre o apellido...') }}" icon="magnifying-glass" />

    <!-- Mobile View (Cards) -->
    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 divide-y dark:divide-zinc-700 sm:hidden">
        @forelse($this->patients as $patient)
            <div class="px-4 py-3 flex items-center justify-between">
                <span class="font-medium">{{ $patient->nombreCompleto() }}</span>
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
    <div class="hidden sm:block overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
        <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
            <thead class="bg-zinc-50 dark:bg-zinc-800/50">
                <tr>
                    <th scope="col" class="px-4 py-4 text-left text-xs font-semibold text-zinc-500 tracking-wider">
                        <flux:label> {{ __('Nombre') }} </flux:label>
                    </th>
                    <th scope="col" class="px-4 py-4 text-left text-xs font-semibold text-zinc-500 tracking-wider">
                        <flux:label> {{ __('DPI') }} </flux:label>
                    </th>
                    <th scope="col" class="px-4 py-4 text-left text-xs font-semibold text-zinc-500 tracking-wider">
                        <flux:label> {{ __('Teléfono') }} </flux:label>
                    </th>
                    <th scope="col" class="px-4 py-4 text-center text-xs font-semibold text-zinc-500 tracking-wider">
                        <flux:label> {{ __('Acciones') }} </flux:label>
                    </th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-zinc-900 divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse($this->patients as $patient)
                    <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors">
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-zinc-900 dark:text-zinc-100">
                            {{ $patient->nombreCompleto() }}
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-zinc-500 dark:text-zinc-400">
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
