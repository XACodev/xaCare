<?php

use App\Modules\QxLog\Models\OperatingRoom;
use App\Modules\QxLog\Models\SurgeryStatus;
use App\Modules\QxLog\Models\SurgicalCase;
use Flux\Flux;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

use function Livewire\Volt\{state, mount, computed};

state(['view' => 'list'])->url(except: 'list');
state(['calendar_scale' => 'month'])->url(except: 'month');
state([
    'date_from' => null,
    'date_to' => null,
    'room_filter' => null,
    'status_filter' => null,
    'calendar_anchor' => null,
])->url();
state(['calendar_selected_day' => null]);

mount(function () {
    $user = Auth::user();
    abort_unless($user && $user->can('surgeries.view'), 403);

    if (! $this->calendar_anchor) {
        $this->calendar_anchor = now()->toDateString();
    }
});

$rooms = computed(fn () => OperatingRoom::query()->where('active', true)->orderBy('sort_order')->get());

$statuses = computed(fn () => SurgeryStatus::query()->where('active', true)->orderBy('sort_order')->get());

$cases = computed(function () {
    return SurgicalCase::query()
        ->with(['operatingRoom', 'surgeryStatus', 'patient', 'procedureType'])
        ->when($this->view !== 'list', fn ($q) => $q->where('is_draft', false))
        ->when($this->room_filter, fn ($q) => $q->where('operating_room_id', $this->room_filter))
        ->when($this->status_filter, fn ($q) => $q->where('surgery_status_id', $this->status_filter))
        ->when($this->date_from, fn ($q) => $q->whereDate('procedure_date', '>=', $this->date_from))
        ->when($this->date_to, fn ($q) => $q->whereDate('procedure_date', '<=', $this->date_to))
        ->orderBy('procedure_date')
        ->orderBy('start_time')
        ->get();
});

$casesByStatus = computed(function () {
    $grouped = $this->cases->groupBy('surgery_status_id');

    return $this->statuses->map(fn (SurgeryStatus $status) => [
        'status' => $status,
        'cases' => $grouped->get($status->id, collect()),
    ]);
});

$calendarRange = computed(function () {
    $anchor = Carbon::parse($this->calendar_anchor);

    if ($this->calendar_scale === 'week') {
        return [$anchor->copy()->startOfWeek(Carbon::MONDAY), $anchor->copy()->endOfWeek(Carbon::SUNDAY)];
    }

    return [
        $anchor->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY),
        $anchor->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY),
    ];
});

$calendarLabel = computed(function () {
    $anchor = Carbon::parse($this->calendar_anchor);

    if ($this->calendar_scale === 'week') {
        [$start, $end] = $this->calendarRange;

        return $start->translatedFormat('d M').' - '.$end->translatedFormat('d M Y');
    }

    return ucfirst($anchor->translatedFormat('F Y'));
});

$calendarCases = computed(function () {
    [$start, $end] = $this->calendarRange;

    return SurgicalCase::query()
        ->with(['operatingRoom', 'surgeryStatus', 'patient', 'procedureType'])
        ->where('is_draft', false)
        ->when($this->room_filter, fn ($q) => $q->where('operating_room_id', $this->room_filter))
        ->when($this->status_filter, fn ($q) => $q->where('surgery_status_id', $this->status_filter))
        ->whereDate('procedure_date', '>=', $start->toDateString())
        ->whereDate('procedure_date', '<=', $end->toDateString())
        ->orderBy('procedure_date')
        ->orderBy('start_time')
        ->get()
        ->groupBy(fn ($c) => optional($c->procedure_date)->format('Y-m-d'));
});

$calendarDays = computed(function () {
    [$start, $end] = $this->calendarRange;
    $anchorMonth = Carbon::parse($this->calendar_anchor)->format('Y-m');
    $grouped = $this->calendarCases;

    $days = [];
    $cursor = $start->copy();
    while ($cursor->lte($end)) {
        $key = $cursor->toDateString();
        $days[] = [
            'date' => $cursor->copy(),
            'in_current_month' => $cursor->format('Y-m') === $anchorMonth,
            'is_today' => $cursor->isToday(),
            'cases' => $grouped->get($key, collect()),
        ];
        $cursor->addDay();
    }

    return $days;
});

$selectedDayCases = computed(function () {
    if (! $this->calendar_selected_day) {
        return collect();
    }

    return $this->calendarCases->get($this->calendar_selected_day, collect());
});

$setCalendarScale = function (string $scale) {
    $this->calendar_scale = in_array($scale, ['month', 'week'], true) ? $scale : 'month';
    $this->calendar_selected_day = null;
};

$calendarPrev = function () {
    $anchor = Carbon::parse($this->calendar_anchor);
    $this->calendar_anchor = $this->calendar_scale === 'week'
        ? $anchor->subWeek()->toDateString()
        : $anchor->subMonthNoOverflow()->toDateString();
    $this->calendar_selected_day = null;
};

$calendarNext = function () {
    $anchor = Carbon::parse($this->calendar_anchor);
    $this->calendar_anchor = $this->calendar_scale === 'week'
        ? $anchor->addWeek()->toDateString()
        : $anchor->addMonthNoOverflow()->toDateString();
    $this->calendar_selected_day = null;
};

$calendarToday = function () {
    $this->calendar_anchor = now()->toDateString();
    $this->calendar_selected_day = null;
};

$openCalendarDay = function (string $date) {
    $this->calendar_selected_day = $date;
    Flux::modal('calendar-day')->show();
};

$closeCalendarDay = function () {
    $this->calendar_selected_day = null;
    Flux::modal('calendar-day')->close();
};

$moveToStatus = function (int $caseId, int $statusId) {
    $user = Auth::user();
    abort_unless($user && $user->can('surgeries.schedule'), 403);

    $case = SurgicalCase::query()->findOrFail($caseId);
    $status = SurgeryStatus::query()->findOrFail($statusId);

    $case->update(['surgery_status_id' => $status->id]);
};

?>

<div class="max-w-7xl mx-auto p-4 space-y-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Surgery Schedule') }}</flux:heading>
            <flux:subheading>xaCare • {{ __('Surgery scheduling board') }}</flux:subheading>
        </div>

        @can('surgeries.schedule')
            <flux:button href="{{ route('surgeries.schedule.create') }}" variant="primary">
                {{ __('Schedule Surgery') }}
            </flux:button>
        @endcan
    </div>

    <div class="flex flex-wrap gap-2">
        <flux:button size="sm" :variant="$view === 'list' ? 'primary' : 'subtle'" wire:click="$set('view', 'list')">
            {{ __('List') }}
        </flux:button>
        <flux:button size="sm" :variant="$view === 'kanban' ? 'primary' : 'subtle'" wire:click="$set('view', 'kanban')">
            {{ __('Kanban') }}
        </flux:button>
        <flux:button size="sm" :variant="$view === 'calendar' ? 'primary' : 'subtle'" wire:click="$set('view', 'calendar')">
            {{ __('Calendar') }}
        </flux:button>
    </div>

    <div class="flex flex-wrap gap-4">
        @if($view !== 'calendar')
            <flux:input type="date" wire:model.live="date_from" placeholder="{{ __('From') }}" />
            <flux:input type="date" wire:model.live="date_to" placeholder="{{ __('To') }}" />
        @endif

        @if($this->rooms->count() > 1)
            <flux:select wire:model.live="room_filter" placeholder="{{ __('Operating Room') }}">
                <flux:select.option value="">{{ __('All rooms') }}</flux:select.option>
                @foreach($this->rooms as $room)
                    <flux:select.option value="{{ $room->id }}">{{ $room->name }}</flux:select.option>
                @endforeach
            </flux:select>
        @endif

        <flux:select wire:model.live="status_filter" placeholder="{{ __('Status') }}">
            <flux:select.option value="">{{ __('All statuses') }}</flux:select.option>
            @foreach($this->statuses as $status)
                <flux:select.option value="{{ $status->id }}">{{ $status->name }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    @if($view === 'list')
        <div class="rounded-xl border border-zinc-200 bg-white shadow-sm dark:bg-zinc-800 dark:border-zinc-700 divide-y divide-zinc-200 dark:divide-zinc-700">
            @forelse($this->cases as $case)
                <a href="{{ route('surgeries.schedule.edit', $case) }}" class="block p-4 hover:bg-zinc-50 dark:hover:bg-zinc-700/50">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <div class="font-medium text-zinc-900 dark:text-zinc-100">
                                {{ $case->patient_name ?? __('Unnamed patient') }}
                                @if($case->is_draft)
                                    <flux:badge color="zinc" size="sm">{{ __('Draft') }}</flux:badge>
                                @endif
                            </div>
                            <div class="text-sm text-zinc-500 dark:text-zinc-400">
                                {{ $case->procedureType?->name }} · {{ $case->operatingRoom?->name }}
                            </div>
                        </div>
                        <div class="text-right text-sm text-zinc-500 dark:text-zinc-400">
                            <div>{{ $case->procedure_date?->format('d/m/Y') }}</div>
                            <flux:badge size="sm">{{ $case->surgeryStatus?->name ?? __('No status') }}</flux:badge>
                        </div>
                    </div>
                </a>
            @empty
                <div class="p-8 text-center text-zinc-500 dark:text-zinc-400">{{ __('No surgeries found.') }}</div>
            @endforelse
        </div>
    @elseif($view === 'kanban')
        <div class="flex gap-4 overflow-x-auto pb-4">
            @foreach($this->casesByStatus as $column)
                <div class="min-w-72 flex-shrink-0 rounded-xl border border-zinc-200 bg-zinc-50 dark:bg-zinc-800 dark:border-zinc-700 p-3 space-y-3">
                    <div class="font-medium text-zinc-700 dark:text-zinc-200">
                        {{ $column['status']->name }} ({{ $column['cases']->count() }})
                    </div>

                    @foreach($column['cases'] as $case)
                        <div class="rounded-lg border border-zinc-200 bg-white dark:bg-zinc-900 dark:border-zinc-700 p-3 space-y-2">
                            <a href="{{ route('surgeries.schedule.edit', $case) }}" class="font-medium text-sm text-zinc-900 dark:text-zinc-100 hover:underline">
                                {{ $case->patient_name ?? __('Unnamed patient') }}
                            </a>
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                {{ $case->procedure_date?->format('d/m/Y') }} · {{ $case->operatingRoom?->name }}
                            </div>

                            @can('surgeries.schedule')
                                <div class="flex flex-wrap gap-1">
                                    @foreach($this->statuses as $target)
                                        @if($target->id !== $case->surgery_status_id)
                                            <flux:button size="xs" variant="subtle"
                                                wire:click="moveToStatus({{ $case->id }}, {{ $target->id }})">
                                                {{ $target->name }}
                                            </flux:button>
                                        @endif
                                    @endforeach
                                </div>
                            @endcan
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>
    @else
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <flux:button size="sm" :variant="$calendar_scale === 'month' ? 'primary' : 'subtle'" wire:click="setCalendarScale('month')">
                    {{ __('Month') }}
                </flux:button>
                <flux:button size="sm" :variant="$calendar_scale === 'week' ? 'primary' : 'subtle'" wire:click="setCalendarScale('week')">
                    {{ __('Week') }}
                </flux:button>
            </div>

            <div class="flex items-center gap-2">
                <flux:button size="sm" variant="subtle" icon="chevron-left" wire:click="calendarPrev" aria-label="{{ __('Previous') }}" />
                <div class="font-medium text-zinc-700 dark:text-zinc-200 min-w-40 text-center">{{ $this->calendarLabel }}</div>
                <flux:button size="sm" variant="subtle" icon="chevron-right" wire:click="calendarNext" aria-label="{{ __('Next') }}" />
                <flux:button size="sm" variant="ghost" wire:click="calendarToday">{{ __('Today') }}</flux:button>
            </div>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-white shadow-sm dark:bg-zinc-800 dark:border-zinc-700 overflow-hidden">
            <div class="grid grid-cols-7 text-xs font-medium text-zinc-500 dark:text-zinc-400 border-b border-zinc-200 dark:border-zinc-700">
                @foreach(array_slice($this->calendarDays, 0, 7) as $headerDay)
                    <div class="p-2 text-center">{{ ucfirst($headerDay['date']->translatedFormat('D')) }}</div>
                @endforeach
            </div>

            @if($calendar_scale === 'month')
                <div class="grid grid-cols-7">
                    @foreach($this->calendarDays as $day)
                        <button type="button"
                            @if($day['cases']->count() > 0) wire:click="openCalendarDay('{{ $day['date']->toDateString() }}')" @endif
                            class="min-h-24 border-b border-r border-zinc-100 dark:border-zinc-700 p-2 text-left align-top
                            {{ $day['in_current_month'] ? 'bg-white dark:bg-zinc-800' : 'bg-zinc-50 dark:bg-zinc-900/40 text-zinc-400 dark:text-zinc-600' }}
                            {{ $day['cases']->count() > 0 ? 'hover:bg-indigo-50 dark:hover:bg-indigo-900/20 cursor-pointer' : 'cursor-default' }}">
                            <span class="text-sm {{ $day['is_today'] ? 'inline-flex h-6 w-6 items-center justify-center rounded-full bg-indigo-600 text-white font-medium' : '' }}">
                                {{ $day['date']->day }}
                            </span>
                            @if($day['cases']->count() > 0)
                                <div class="mt-1">
                                    <flux:badge size="sm">{{ $day['cases']->count() }}</flux:badge>
                                </div>
                            @endif
                        </button>
                    @endforeach
                </div>
            @else
                <div class="grid grid-cols-1 md:grid-cols-7 divide-y md:divide-y-0 md:divide-x divide-zinc-100 dark:divide-zinc-700">
                    @foreach($this->calendarDays as $day)
                        <div class="p-2 space-y-2 min-h-32">
                            <div class="text-xs font-medium {{ $day['is_today'] ? 'text-indigo-600 dark:text-indigo-400' : 'text-zinc-500 dark:text-zinc-400' }}">
                                {{ $day['date']->day }}
                            </div>
                            @forelse($day['cases'] as $case)
                                <a href="{{ route('surgeries.schedule.edit', $case) }}" class="block text-sm rounded bg-indigo-50 dark:bg-indigo-900/30 px-2 py-1 hover:bg-indigo-100 dark:hover:bg-indigo-900/50">
                                    <span class="font-mono text-xs">{{ $case->start_time ? substr($case->start_time, 0, 5) : '--:--' }}</span>
                                    {{ $case->patient_name ?? __('Unnamed patient') }}
                                </a>
                            @empty
                                <div class="text-xs text-zinc-300 dark:text-zinc-600">—</div>
                            @endforelse
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <flux:modal name="calendar-day" class="max-w-lg" @close="$wire.closeCalendarDay()">
            <div class="space-y-4">
                <flux:heading size="lg">
                    {{ $calendar_selected_day ? ucfirst(\Illuminate\Support\Carbon::parse($calendar_selected_day)->translatedFormat('l, d F Y')) : '' }}
                </flux:heading>

                <div class="space-y-2">
                    @forelse($this->selectedDayCases as $case)
                        <a href="{{ route('surgeries.schedule.edit', $case) }}" class="block rounded-lg border border-zinc-200 dark:border-zinc-700 p-3 hover:bg-zinc-50 dark:hover:bg-zinc-700/50">
                            <div class="flex items-center justify-between gap-4">
                                <div>
                                    <div class="font-medium text-zinc-900 dark:text-zinc-100">
                                        {{ $case->patient_name ?? __('Unnamed patient') }}
                                    </div>
                                    <div class="text-sm text-zinc-500 dark:text-zinc-400">
                                        {{ $case->procedureType?->name }} · {{ $case->operatingRoom?->name }}
                                    </div>
                                </div>
                                <div class="text-right text-sm text-zinc-500 dark:text-zinc-400">
                                    <div class="font-mono">{{ $case->start_time ? substr($case->start_time, 0, 5) : '--:--' }}</div>
                                    <flux:badge size="sm">{{ $case->surgeryStatus?->name ?? __('No status') }}</flux:badge>
                                </div>
                            </div>
                        </a>
                    @empty
                        <div class="p-4 text-center text-zinc-500 dark:text-zinc-400">{{ __('No surgeries found.') }}</div>
                    @endforelse
                </div>
            </div>
        </flux:modal>
    @endif
</div>
