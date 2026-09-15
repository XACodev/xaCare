<?php

use App\Modules\QxLog\Models\OperatingRoom;
use App\Modules\QxLog\Models\SurgicalCase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

use function Livewire\Volt\{state, mount, computed};

state(['anchor' => null])->url();

mount(function () {
    abort_unless(Auth::user()?->can('surgeries.view'), 403);

    if (! $this->anchor) {
        $this->anchor = now()->toDateString();
    }
});

// Franja horaria fija 07:00-19:00 (horario quirúrgico típico) -- el mockup
// muestra filas de hora sin especificar el rango exacto, así que se eligió
// uno razonable en vez de calcularlo dinámicamente por caso.
$hours = computed(fn () => range(7, 19));

$weekRange = computed(function () {
    $anchor = Carbon::parse($this->anchor);

    return [$anchor->copy()->startOfWeek(Carbon::MONDAY), $anchor->copy()->endOfWeek(Carbon::SUNDAY)];
});

$weekLabel = computed(function () {
    [$start, $end] = $this->weekRange;

    return $start->translatedFormat('d M').' - '.$end->translatedFormat('d M Y');
});

$weekDays = computed(function () {
    [$start] = $this->weekRange;

    return collect(range(0, 6))->map(fn (int $i) => $start->copy()->addDays($i));
});

$rooms = computed(fn () => OperatingRoom::query()->where('active', true)->orderBy('sort_order')->get());

$cases = computed(function () {
    [$start, $end] = $this->weekRange;

    return SurgicalCase::query()
        ->with(['operatingRoom', 'procedureType'])
        ->where('is_draft', false)
        ->whereDate('procedure_date', '>=', $start->toDateString())
        ->whereDate('procedure_date', '<=', $end->toDateString())
        ->orderBy('start_time')
        ->get()
        ->groupBy(fn (SurgicalCase $c) => $c->procedure_date->format('Y-m-d').'|'.((int) substr($c->start_time ?? '00:00', 0, 2)));
});

$casesFor = fn (Carbon $day, int $hour) => $this->cases->get($day->format('Y-m-d').'|'.$hour, collect());

$prevWeek = function () {
    $this->anchor = Carbon::parse($this->anchor)->subWeek()->toDateString();
};

$nextWeek = function () {
    $this->anchor = Carbon::parse($this->anchor)->addWeek()->toDateString();
};

$today = function () {
    $this->anchor = now()->toDateString();
};

?>

<div class="max-w-7xl mx-auto p-4 space-y-6">
    <x-mobile-back :href="route('surgeries.board')" :label="__('Surgeries')" />
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Surgery Calendar') }}</flux:heading>
            <flux:subheading>{{ __('Weekly view by operating room') }}</flux:subheading>
        </div>

        <flux:button :href="route('surgeries.board')" wire:navigate variant="subtle" size="sm" class="hidden lg:inline-flex">
            {{ __('Back to board') }}
        </flux:button>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-2">
            <flux:button size="sm" variant="subtle" icon="chevron-left" wire:click="prevWeek" aria-label="{{ __('Previous') }}" />
            <div class="font-medium text-zinc-700 dark:text-zinc-200 min-w-48 text-center">{{ $this->weekLabel }}</div>
            <flux:button size="sm" variant="subtle" icon="chevron-right" wire:click="nextWeek" aria-label="{{ __('Next') }}" />
            <flux:button size="sm" variant="ghost" wire:click="today">{{ __('Today') }}</flux:button>
        </div>

        <div class="flex flex-wrap gap-3 text-xs text-zinc-600 dark:text-zinc-400">
            @foreach ($this->rooms as $room)
                <span class="flex items-center gap-1.5">
                    <span class="size-2.5 rounded-sm {{ $room->colorBarClass() }}"></span>
                    {{ $room->name }}
                </span>
            @endforeach
        </div>
    </div>

    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 overflow-x-auto">
        <div class="grid min-w-[900px]" style="grid-template-columns: 64px repeat(7, 1fr);">
            <div class="border-b border-r border-zinc-100 dark:border-zinc-800"></div>
            @foreach ($this->weekDays as $day)
                <div class="border-b border-r border-zinc-100 dark:border-zinc-800 p-2 text-center text-xs font-medium
                    {{ $day->isToday() ? 'bg-accent/10 text-accent' : 'text-zinc-500 dark:text-zinc-400' }}">
                    <div>{{ ucfirst($day->translatedFormat('D')) }}</div>
                    <div class="text-sm font-semibold">{{ $day->day }}</div>
                </div>
            @endforeach

            @foreach ($this->hours as $hour)
                <div class="border-b border-r border-zinc-100 dark:border-zinc-800 p-1.5 text-xs text-zinc-400 dark:text-zinc-500 tabular-nums">
                    {{ sprintf('%02d:00', $hour) }}
                </div>
                @foreach ($this->weekDays as $day)
                    <div class="border-b border-r border-zinc-100 dark:border-zinc-800 p-1 min-h-16 align-top space-y-1">
                        @foreach ($this->casesFor($day, $hour) as $case)
                            <a href="{{ route('surgeries.schedule.edit', $case) }}"
                                class="block rounded-md px-2 py-1 text-xs bg-mist dark:bg-zinc-800 border-l-2 {{ $case->operatingRoom?->colorBarClass() ? str_replace('bg-', 'border-', $case->operatingRoom->colorBarClass()) : 'border-zinc-400' }} hover:bg-zinc-100 dark:hover:bg-zinc-700">
                                <div class="font-semibold truncate">{{ $case->procedureType?->name ?? __('Sin procedimiento') }}</div>
                                <div class="text-zinc-500 dark:text-zinc-400 truncate">{{ $case->start_time ? substr($case->start_time, 0, 5) : '' }} · {{ $case->operatingRoom?->name }}</div>
                            </a>
                        @endforeach
                    </div>
                @endforeach
            @endforeach
        </div>
    </div>
</div>
