<?php

use App\Modules\QxLog\Models\OperatingRoom;
use App\Modules\QxLog\Models\SurgeryStatus;
use App\Modules\QxLog\Models\SurgicalCase;
use Illuminate\Support\Facades\Auth;

use function Livewire\Volt\{state, mount, computed};

state([
    'view' => 'list',
    'date_from' => null,
    'date_to' => null,
    'room_filter' => null,
    'status_filter' => null,
]);

mount(function () {
    $user = Auth::user();
    abort_unless($user && $user->can('surgeries.view'), 403);
});

$rooms = computed(fn () => OperatingRoom::query()->where('active', true)->orderBy('sort_order')->get());

$statuses = computed(fn () => SurgeryStatus::query()->where('active', true)->orderBy('sort_order')->get());

$cases = computed(function () {
    return SurgicalCase::query()
        ->with(['operatingRoom', 'surgeryStatus', 'patient'])
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
        <flux:input type="date" wire:model.live="date_from" placeholder="{{ __('From') }}" />
        <flux:input type="date" wire:model.live="date_to" placeholder="{{ __('To') }}" />

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
                                {{ $case->procedure_type }} · {{ $case->operatingRoom?->name }}
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
        <div class="rounded-xl border border-zinc-200 bg-white shadow-sm dark:bg-zinc-800 dark:border-zinc-700 divide-y divide-zinc-200 dark:divide-zinc-700">
            @forelse($this->cases->groupBy(fn ($c) => optional($c->procedure_date)->format('Y-m-d')) as $date => $dayCases)
                <div class="p-4 space-y-2">
                    <div class="font-medium text-zinc-700 dark:text-zinc-200">
                        {{ \Illuminate\Support\Carbon::parse($date)->format('d/m/Y') }}
                    </div>
                    <div class="grid grid-cols-1 {{ $this->rooms->count() > 1 ? 'md:grid-cols-'.min($this->rooms->count(), 4) : '' }} gap-3">
                        @foreach($this->rooms->count() > 1 ? $this->rooms : [null] as $room)
                            <div class="rounded-lg border border-zinc-100 dark:border-zinc-700 p-2 space-y-2">
                                @if($room)
                                    <div class="text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ $room->name }}</div>
                                @endif
                                @foreach($dayCases->filter(fn ($c) => !$room || $c->operating_room_id === $room->id) as $case)
                                    <a href="{{ route('surgeries.schedule.edit', $case) }}" class="block text-sm rounded bg-indigo-50 dark:bg-indigo-900/30 px-2 py-1 hover:bg-indigo-100 dark:hover:bg-indigo-900/50">
                                        <span class="font-mono text-xs">{{ $case->start_time ? substr($case->start_time, 0, 5) : '--:--' }}</span>
                                        {{ $case->patient_name ?? __('Unnamed patient') }}
                                    </a>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                </div>
            @empty
                <div class="p-8 text-center text-zinc-500 dark:text-zinc-400">{{ __('No surgeries found.') }}</div>
            @endforelse
        </div>
    @endif
</div>
