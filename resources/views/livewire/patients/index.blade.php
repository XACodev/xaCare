<?php

use App\Models\Patient;

use function Livewire\Volt\{state, computed};

state(['q' => '']);

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

    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 divide-y dark:divide-zinc-700">
        @forelse($this->patients as $patient)
            <div class="px-4 py-3 flex items-center justify-between">
                <span class="font-medium">{{ $patient->nombreCompleto() }}</span>
                <span class="text-sm text-zinc-500">{{ $patient->dpi }}</span>
            </div>
        @empty
            <div class="px-4 py-6 text-center text-sm text-zinc-500">{{ __('Sin pacientes.') }}</div>
        @endforelse
    </div>
</div>
