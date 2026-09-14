<?php

use App\Models\Admission;
use App\Models\AdmissionType;
use Illuminate\Support\Facades\Auth;

use function Livewire\Volt\{state, mount, computed};

state(['period' => 'month', 'typeFilter' => null]);

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

    if ($this->typeFilter) {
        $query->where('admission_type_id', $this->typeFilter);
    }

    return $query->limit(200)->get();
});

$typeCounts = computed(function () {
    return Admission::query()
        ->where('fecha_ingreso', '>=', match ($this->period) {
            'day' => now()->startOfDay(),
            'week' => now()->startOfWeek(),
            default => now()->startOfMonth(),
        })
        ->selectRaw('admission_type_id, count(*) as c')
        ->groupBy('admission_type_id')
        ->pluck('c', 'admission_type_id');
});

$admissionTypes = computed(fn () => AdmissionType::query()->where('active', true)->orderBy('sort_order')->get());

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

    <div class="flex gap-2 flex-wrap items-center">
        <button type="button" wire:click="$set('typeFilter', null)"
            class="h-9 px-3.5 rounded-lg text-sm font-semibold border {{ !$typeFilter ? 'border-accent bg-mist text-accent-content dark:bg-accent/20 dark:text-accent' : 'border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-zinc-600 dark:text-zinc-400' }}">
            {{ __('Todos') }} · {{ $this->typeCounts->sum() }}
        </button>
        @foreach ($this->admissionTypes as $type)
            <button type="button" wire:click="$set('typeFilter', {{ $type->id }})"
                class="h-9 px-3.5 rounded-lg text-sm border flex items-center gap-2 {{ $typeFilter === $type->id ? 'border-accent bg-mist text-accent-content dark:bg-accent/20 dark:text-accent font-semibold' : 'border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-zinc-600 dark:text-zinc-400' }}">
                <span class="size-2 rounded-sm {{ $type->colorBarClass() }}"></span>
                {{ $type->name }} · {{ $this->typeCounts[$type->id] ?? 0 }}
            </button>
        @endforeach
    </div>

    <!-- Mobile View (Cards) -->
    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 divide-y dark:divide-zinc-700 sm:hidden">
        @forelse ($this->admissions as $admission)
            <flux:link href="{{ route('admissions.show', ['admission' => $admission, 'token' => $admission->qr_token]) }}"
                class="flex items-stretch gap-3 px-4 py-3 hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                <span class="flex-none w-1.5 rounded-full {{ $admission->admissionType?->colorBarClass() ?? 'bg-zinc-300' }}"></span>
                <div class="flex-1 min-w-0 flex items-center justify-between gap-4">
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

    <!-- Desktop View (Table) -->
    <div class="hidden sm:block overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700 dark:bg-zinc-900">
        <table class="min-w-full">
            <thead>
                <tr class="qx-table-head">
                    <th class="w-2"></th>
                    <th scope="col" class="px-4 py-3 text-left font-semibold tracking-wider">{{ __('Paciente') }}</th>
                    <th scope="col" class="px-4 py-3 text-left font-semibold tracking-wider">{{ __('Tipo') }}</th>
                    <th scope="col" class="px-4 py-3 text-left font-semibold tracking-wider">{{ __('Ingreso') }}</th>
                    <th scope="col" class="px-4 py-3 text-left font-semibold tracking-wider">{{ __('Ubicación') }}</th>
                    <th scope="col" class="px-4 py-3 text-left font-semibold tracking-wider">{{ __('Médico') }}</th>
                    <th scope="col" class="px-4 py-3 text-left font-semibold tracking-wider">{{ __('Días') }}</th>
                    <th scope="col" class="px-4 py-3 text-center font-semibold tracking-wider">{{ __('Estado') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->admissions as $admission)
                    <tr wire:key="admission-{{ $admission->id }}" class="qx-table-row">
                        <td class="pl-4"><span class="block w-1.5 h-8 rounded {{ $admission->admissionType?->colorBarClass() ?? 'bg-zinc-300' }}"></span></td>
                        <td class="px-4 py-3">
                            <flux:link href="{{ route('admissions.show', ['admission' => $admission, 'token' => $admission->qr_token]) }}" class="font-medium">
                                {{ $admission->patient->nombreCompleto() ?: __('Recién nacido/a') }}
                            </flux:link>
                        </td>
                        <td class="px-4 py-3 text-sm text-zinc-700 dark:text-zinc-300">{{ $admission->admissionType?->name ?: '—' }}</td>
                        <td class="px-4 py-3 text-sm text-zinc-700 dark:text-zinc-300 tabular-nums">{{ $admission->fecha_ingreso?->format('d/m/Y') }}</td>
                        <td class="px-4 py-3 text-sm text-zinc-700 dark:text-zinc-300">{{ $admission->sala_ingreso ?: '—' }}</td>
                        <td class="px-4 py-3 text-sm text-zinc-700 dark:text-zinc-300 truncate max-w-40">{{ $admission->medico_responsable ?: '—' }}</td>
                        <td class="px-4 py-3 text-sm text-zinc-700 dark:text-zinc-300 tabular-nums">{{ $admission->total_dias ?? '—' }}</td>
                        <td class="px-4 py-3 text-center">
                            <flux:badge :variant="$admission->completo ? 'success' : 'warning'">
                                {{ $admission->completo ? __('Completo') : __('Pendiente') }}
                            </flux:badge>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-6 py-8 text-center text-sm text-zinc-500 dark:text-zinc-400 italic">
                            {{ __('Sin ingresos en este periodo.') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
