<?php

use App\Models\Admission;
use Illuminate\Support\Facades\Auth;

use function Livewire\Volt\{state, mount, computed};

state(['period' => 'month']);

mount(function () {
    abort_unless(Auth::check(), 401);
    abort_unless((bool) (Auth::user()->hasRole('admin') || Auth::user()->is_platform_admin), 403);
});

// El personal reingresa pacientes varias veces al mes; este listado agrupa
// los ingresos (no los pacientes) por dia/semana/mes para poder ver los
// reingresos del periodo en vez de una fila unica por paciente.
$admissions = computed(function () {
    $query = Admission::query()->with(['patient', 'admissionType'])->orderByDesc('fecha_ingreso')->orderByDesc('id');

    $query->where('fecha_ingreso', '>=', match ($this->period) {
        'day' => now()->startOfDay(),
        'week' => now()->startOfWeek(),
        default => now()->startOfMonth(),
    });

    return $query->limit(200)->get();
});

?>

<div class="max-w-5xl mx-auto p-4 space-y-4">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">{{ __('Ingresos') }}</flux:heading>
        <flux:button href="{{ route('admissions.create') }}" variant="primary" icon="plus">{{ __('Nuevo ingreso') }}</flux:button>
    </div>

    <div class="flex gap-2">
        @foreach (['day' => __('Hoy'), 'week' => __('Esta semana'), 'month' => __('Este mes')] as $value => $label)
            <button type="button" wire:click="$set('period', '{{ $value }}')"
                class="px-4 h-9 rounded-lg text-sm border {{ $period === $value ? 'bg-mist border-accent text-accent-content dark:bg-accent/20 dark:text-accent font-semibold' : 'bg-white dark:bg-zinc-900 border-zinc-200 dark:border-zinc-700 text-zinc-600 dark:text-zinc-400' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 divide-y dark:divide-zinc-700">
        @forelse ($this->admissions as $admission)
            <flux:link href="{{ route('admissions.show', ['admission' => $admission, 'token' => $admission->qr_token]) }}"
                class="block px-4 py-3 hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                <div class="flex items-center justify-between gap-4">
                    <div class="min-w-0">
                        <div class="font-medium truncate">{{ $admission->patient->nombreCompleto() ?: __('Recién nacido/a') }}</div>
                        <div class="text-sm text-zinc-500 dark:text-zinc-400">
                            {{ $admission->fecha_ingreso?->format('d/m/Y') }}
                            · {{ $admission->admissionType?->name }}
                            @if ($admission->sala_ingreso)
                                · {{ $admission->sala_ingreso }}
                            @endif
                        </div>
                    </div>
                    <flux:badge :variant="$admission->completo ? 'success' : 'warning'">
                        {{ $admission->completo ? __('Completo') : __('Pendiente') }}
                    </flux:badge>
                </div>
            </flux:link>
        @empty
            <div class="px-6 py-8 text-center text-sm text-zinc-500 dark:text-zinc-400 italic">
                {{ __('Sin ingresos en este periodo.') }}
            </div>
        @endforelse
    </div>
</div>
