<?php

use App\Models\Admission;
use App\Models\AdmissionType;
use Illuminate\Support\Facades\Auth;

use function Livewire\Volt\{state, mount, computed};

state(['period' => 'month', 'typeFilter' => null, 'q' => '']);

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

    if (filled($this->q)) {
        $term = '%'.$this->q.'%';
        $query->where(function ($inner) use ($term) {
            $inner->where('sala_ingreso', 'like', $term)
                ->orWhere('habitacion', 'like', $term)
                ->orWhereHas('patient', function ($patient) use ($term) {
                    $patient->where('primer_nombre', 'like', $term)
                        ->orWhere('primer_apellido', 'like', $term)
                        ->orWhere('dpi', 'like', $term)
                        ->orWhere('expediente_no', 'like', $term);
                });
        });
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

$cardMeta = function (Admission $admission): string {
    return collect([
        $admission->fecha_ingreso?->format('d/m/Y'),
        $admission->admissionType?->name,
        $admission->sala_ingreso,
    ])->filter()->join(' · ');
};

$cardPlace = function (Admission $admission): string {
    return collect([
        $admission->admissionType?->name,
        $admission->sala_ingreso,
    ])->filter()->join(' · ');
};

$cardSecondary = function (Admission $admission): string {
    return collect([
        $admission->patient->expediente_no ?: $admission->patient->dpi,
        $admission->fecha_ingreso?->format('d/m/Y'),
        $admission->total_dias !== null ? $admission->total_dias.' '.__('días') : null,
    ])->filter()->join(' · ');
};

?>

<div class="max-w-5xl mx-auto sm:p-4 sm:space-y-4">
    {{-- Mockup 1e: header blanco (título 24/600, + 40×40 r12, search 44 r12, chips 34 r10 texto) + canvas #F4F6F5 --}}
    <div class="sm:hidden -mx-4 -mt-4">
        <div class="bg-white dark:bg-zinc-900 border-b border-zinc-200 dark:border-border-dark px-5 pt-3 pb-3.5 grid gap-3.5">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-semibold tracking-tight text-ink dark:text-zinc-50">{{ __('Ingresos') }}</h1>
                <a href="{{ route('admissions.create') }}" wire:navigate
                    class="size-10 rounded-xl bg-accent text-accent-foreground grid place-items-center text-xl leading-none shrink-0"
                    aria-label="{{ __('Nuevo ingreso') }}">+</a>
            </div>

            <label class="h-11 px-3.5 rounded-xl bg-canvas dark:bg-field-dark border border-[#D5DDDA] dark:border-zinc-700 flex items-center gap-2.5 text-[#7C8A87] dark:text-zinc-500">
                <flux:icon.magnifying-glass class="size-4 shrink-0" />
                <input type="search" wire:model.live.debounce.300ms="q"
                    placeholder="{{ __('Buscar paciente, habitación…') }}"
                    class="min-w-0 flex-1 bg-transparent border-0 p-0 text-[15px] text-ink dark:text-zinc-100 placeholder:text-[#7C8A87] dark:placeholder:text-zinc-500 focus:ring-0" />
            </label>

            <div class="flex gap-2 overflow-x-auto [scrollbar-width:none] [-ms-overflow-style:none] [&::-webkit-scrollbar]:hidden">
                <button type="button" wire:click="$set('typeFilter', null)"
                    class="h-[34px] px-3 rounded-[10px] text-[13px] border shrink-0 whitespace-nowrap {{ !$typeFilter ? 'border-accent bg-mist text-accent font-semibold dark:bg-accent/20' : 'border-[#D5DDDA] dark:border-zinc-700 bg-white dark:bg-zinc-900 text-zinc-700 dark:text-zinc-300 font-normal' }}">
                    {{ __('Todos') }}
                </button>
                @foreach ($this->admissionTypes as $type)
                    <button type="button" wire:key="type-m-{{ $type->id }}" wire:click="$set('typeFilter', {{ $type->id }})"
                        class="h-[34px] px-3 rounded-[10px] text-[13px] border shrink-0 whitespace-nowrap {{ $typeFilter === $type->id ? 'border-accent bg-mist text-accent font-semibold dark:bg-accent/20' : 'border-[#D5DDDA] dark:border-zinc-700 bg-white dark:bg-zinc-900 text-zinc-700 dark:text-zinc-300' }}">
                        {{ $type->name }}
                    </button>
                @endforeach
            </div>

            {{-- Periodo es producto (no está en 1e); se trata como segmented 1l, debajo de los chips. --}}
            <div class="h-9 grid grid-cols-3 rounded-[10px] overflow-hidden border border-[#D5DDDA] dark:border-zinc-700 text-[13px]">
                <button type="button" wire:click="$set('period', 'day')"
                    class="{{ $period === 'day' ? 'bg-mist text-accent font-semibold dark:bg-accent/20 dark:text-accent' : 'bg-white dark:bg-zinc-900 text-zinc-500 dark:text-zinc-400' }}">{{ __('Hoy') }}</button>
                <button type="button" wire:click="$set('period', 'week')"
                    class="{{ $period === 'week' ? 'bg-mist text-accent font-semibold dark:bg-accent/20 dark:text-accent' : 'bg-white dark:bg-zinc-900 text-zinc-500 dark:text-zinc-400' }}">{{ __('Semana') }}</button>
                <button type="button" wire:click="$set('period', 'month')"
                    class="{{ $period === 'month' ? 'bg-mist text-accent font-semibold dark:bg-accent/20 dark:text-accent' : 'bg-white dark:bg-zinc-900 text-zinc-500 dark:text-zinc-400' }}">{{ __('Mes') }}</button>
            </div>
        </div>

        {{-- Mockup 1e: tarjetas sueltas en canvas, barra 6×44 r3, type·place 13/#5B6B68, doc·días 12/#7C8A87, chip 11/4×8/r8 --}}
        <div class="bg-canvas dark:bg-night px-5 pt-4 pb-6 grid gap-2.5">
            @forelse ($this->admissions as $admission)
                <a href="{{ route('admissions.show', ['admission' => $admission, 'token' => $admission->qr_token]) }}"
                    wire:navigate wire:key="admission-card-{{ $admission->id }}"
                    class="flex items-center gap-3 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-border-dark rounded-[14px] px-4 py-3.5">
                    <span class="w-1.5 h-11 rounded-[3px] shrink-0 {{ $admission->admissionType?->colorBarClass() ?? 'bg-zinc-300 dark:bg-zinc-600' }}"></span>
                    <div class="min-w-0 flex-1">
                        <div class="font-semibold truncate text-ink dark:text-zinc-50">{{ $admission->patient->nombreCompleto() ?: __('Recién nacido/a') }}</div>
                        @if ($this->cardPlace($admission) !== '')
                            <div class="text-[13px] text-muted dark:text-zinc-400 mt-0.5 truncate">{{ $this->cardPlace($admission) }}</div>
                        @elseif ($this->cardMeta($admission) !== '')
                            <div class="text-[13px] text-muted dark:text-zinc-400 mt-0.5 truncate">{{ $this->cardMeta($admission) }}</div>
                        @endif
                        @if ($this->cardSecondary($admission) !== '')
                            <div class="text-[12px] text-[#7C8A87] dark:text-zinc-500 mt-0.5 truncate">{{ $this->cardSecondary($admission) }}</div>
                        @endif
                    </div>
                    <span @class([
                        'text-[11px] font-semibold px-2 py-1 rounded-lg whitespace-nowrap shrink-0',
                        'bg-done-soft text-done' => $admission->completo,
                        'bg-pending-soft text-pending' => ! $admission->completo,
                    ])>
                        {{ $admission->completo ? __('Completo') : __('Pendiente') }}
                    </span>
                </a>
            @empty
                <div class="rounded-[14px] border border-zinc-200 dark:border-border-dark bg-white dark:bg-zinc-900 px-6 py-8 text-center text-sm text-zinc-500 dark:text-zinc-400 italic">
                    {{ __('Sin ingresos en este periodo.') }}
                </div>
            @endforelse
        </div>
    </div>


    {{-- Desktop header + filtros (1d) --}}
    <div class="hidden sm:flex items-end justify-between gap-3">
        <div>
            <flux:heading size="xl">{{ __('Ingresos') }}</flux:heading>
            <div class="text-[13px] text-muted mt-1">
                {{ $this->admissions->count() }} {{ __('activos') }}
                · {{ $this->admissions->where('completo', false)->count() }} {{ __('pendientes de completar') }}
            </div>
        </div>
        <flux:button href="{{ route('admissions.create') }}" variant="primary" icon="plus">{{ __('Nuevo ingreso') }}</flux:button>
    </div>

    <div class="hidden sm:flex gap-2">
        <button type="button" wire:click="$set('period', 'day')"
            class="px-4 h-9 rounded-lg text-sm whitespace-nowrap border {{ $period === 'day' ? 'bg-mist border-accent text-accent-content dark:bg-accent/20 dark:text-accent font-semibold' : 'bg-white dark:bg-zinc-900 border-zinc-200 dark:border-zinc-700 text-zinc-600 dark:text-zinc-400' }}">{{ __('Hoy') }}</button>
        <button type="button" wire:click="$set('period', 'week')"
            class="px-4 h-9 rounded-lg text-sm whitespace-nowrap border {{ $period === 'week' ? 'bg-mist border-accent text-accent-content dark:bg-accent/20 dark:text-accent font-semibold' : 'bg-white dark:bg-zinc-900 border-zinc-200 dark:border-zinc-700 text-zinc-600 dark:text-zinc-400' }}">{{ __('Esta semana') }}</button>
        <button type="button" wire:click="$set('period', 'month')"
            class="px-4 h-9 rounded-lg text-sm whitespace-nowrap border {{ $period === 'month' ? 'bg-mist border-accent text-accent-content dark:bg-accent/20 dark:text-accent font-semibold' : 'bg-white dark:bg-zinc-900 border-zinc-200 dark:border-zinc-700 text-zinc-600 dark:text-zinc-400' }}">{{ __('Este mes') }}</button>
    </div>

    <div class="hidden sm:flex gap-2 flex-wrap items-center">
        <button type="button" wire:click="$set('typeFilter', null)"
            class="h-9 px-3.5 rounded-[10px] text-sm font-semibold border {{ !$typeFilter ? 'border-accent bg-mist text-accent-content dark:bg-accent/20 dark:text-accent' : 'border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-zinc-600 dark:text-zinc-400' }}">
            {{ __('Todos') }} · {{ $this->typeCounts->sum() }}
        </button>
        @foreach ($this->admissionTypes as $type)
            <button type="button" wire:key="type-d-{{ $type->id }}" wire:click="$set('typeFilter', {{ $type->id }})"
                class="h-9 px-3.5 rounded-[10px] text-sm border flex items-center gap-2 {{ $typeFilter === $type->id ? 'border-accent bg-mist text-accent-content dark:bg-accent/20 dark:text-accent font-semibold' : 'border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-zinc-600 dark:text-zinc-400' }}">
                <span class="size-2 rounded-sm {{ $type->colorBarClass() }}"></span>
                {{ $type->name }} · {{ $this->typeCounts[$type->id] ?? 0 }}
            </button>
        @endforeach
    </div>

    {{-- Desktop header + filtros (1d) --}}
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
                            <span @class([
                                'text-xs font-semibold px-2.5 py-1 rounded-lg whitespace-nowrap',
                                'bg-done-soft text-done' => $admission->completo,
                                'bg-pending-soft text-pending' => ! $admission->completo,
                            ])>
                                {{ $admission->completo ? __('Completo') : __('Pendiente') }}
                            </span>
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
