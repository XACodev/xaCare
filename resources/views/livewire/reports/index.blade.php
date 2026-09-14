<?php

use App\Modules\Reports\Services\ReportService;
use Illuminate\Support\Facades\Auth;

use function Livewire\Volt\{state, mount, computed};

state(['period' => 'month']);

mount(function () {
    abort_unless((bool) (Auth::user()?->hasRole('admin') || Auth::user()?->is_platform_admin), 403);
});

$setPeriod = function (string $period) {
    $this->period = $period;
};

// Rango de fechas segun el periodo elegido. El grafico de ingresos por dia
// (14 dias) queda fijo sin importar el periodo -- el mockup lo muestra como
// una ventana corta y fija, no como parte del selector.
$range = computed(function () {
    $to = now()->endOfDay();
    $from = match ($this->period) {
        'week' => now()->startOfWeek(),
        'quarter' => now()->startOfQuarter(),
        'year' => now()->startOfYear(),
        default => now()->startOfMonth(),
    };

    return [$from->format('Y-m-d'), $to->format('Y-m-d')];
});

$kpis = computed(function () {
    $service = app(ReportService::class);
    [$from, $to] = $this->range;

    $procedures = $service->proceduresByDateRange($from, $to);
    $totalAmount = $procedures->sum(fn ($p) => (float) $p->assignments->sum('calculated_amount'));
    $totalCount = $procedures->count();
    $topRoom = $service->proceduresByOperatingRoom($from, $to)->first();

    return [
        ['label' => __('Procedures'), 'value' => $totalCount, 'note' => __('period total'), 'color' => 'text-accent'],
        ['label' => __('Revenue'), 'value' => 'Q'.number_format($totalAmount, 2), 'note' => __('period total'), 'color' => 'text-emerald-600 dark:text-emerald-400'],
        ['label' => __('Avg. per procedure'), 'value' => 'Q'.number_format($totalCount ? $totalAmount / $totalCount : 0, 2), 'note' => __('period average'), 'color' => 'text-blue-600 dark:text-blue-400'],
        ['label' => __('Busiest room'), 'value' => $topRoom['name'] ?? '—', 'note' => $topRoom ? __(':count cases', ['count' => $topRoom['count']]) : __('no data'), 'color' => 'text-violet-600 dark:text-violet-400'],
    ];
});

$revenueChart = computed(fn () => app(ReportService::class)->revenueByDayAndAdmissionType(14));

$roomStats = computed(function () {
    [$from, $to] = $this->range;

    return app(ReportService::class)->proceduresByOperatingRoom($from, $to)->take(5);
});

$chartMax = computed(fn () => max($this->revenueChart->max('total'), 1));

?>

<div class="max-w-6xl mx-auto p-4 space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Reports') }}</flux:heading>
            <flux:subheading>{{ now()->translatedFormat('F Y') }}</flux:subheading>
        </div>

        <div class="flex flex-wrap gap-2">
            @foreach (['week' => __('Week'), 'month' => __('Month'), 'quarter' => __('Quarter'), 'year' => __('Year')] as $value => $label)
                <flux:button size="sm" :variant="$period === $value ? 'primary' : 'ghost'" wire:click="setPeriod('{{ $value }}')">
                    {{ $label }}
                </flux:button>
            @endforeach
        </div>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        @foreach ($this->kpis as $kpi)
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-4">
                <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $kpi['label'] }}</div>
                <div class="text-xl font-semibold mt-1 {{ $kpi['color'] }}">{{ $kpi['value'] }}</div>
                <div class="text-xs text-zinc-400 dark:text-zinc-500 mt-1">{{ $kpi['note'] }}</div>
            </div>
        @endforeach
    </div>

    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-6 space-y-4">
        <flux:heading size="lg">{{ __('Revenue by day (last 14 days)') }}</flux:heading>

        <div class="flex items-end gap-2 h-40">
            @foreach ($this->revenueChart as $day)
                <div class="flex-1 flex flex-col justify-end h-full gap-px" title="{{ $day['date'] }}: Q{{ number_format($day['total'], 2) }}">
                    @foreach ($day['types'] as $type)
                        <div class="{{ $type['color'] }} rounded-sm"
                            style="height: {{ $day['total'] > 0 ? max(($type['amount'] / $this->chartMax) * 100, 2) : 0 }}%"
                            title="{{ $type['name'] }}: Q{{ number_format($type['amount'], 2) }}"></div>
                    @endforeach
                </div>
            @endforeach
        </div>

        <div class="flex justify-between text-xs text-zinc-400 dark:text-zinc-500">
            <span>{{ \Illuminate\Support\Carbon::parse($this->revenueChart->first()['date'])->format('d M') }}</span>
            <span>{{ \Illuminate\Support\Carbon::parse($this->revenueChart->last()['date'])->format('d M') }}</span>
        </div>
    </div>

    <div class="grid lg:grid-cols-2 gap-6">
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-6 space-y-4">
            <flux:heading size="lg">{{ __('Procedures by room') }}</flux:heading>

            @forelse ($this->roomStats as $room)
                <div class="space-y-1">
                    <div class="flex justify-between text-sm">
                        <span class="text-zinc-700 dark:text-zinc-300">{{ $room['name'] }}</span>
                        <span class="text-zinc-500 dark:text-zinc-400">{{ $room['count'] }} ({{ $room['percent'] }}%)</span>
                    </div>
                    <div class="h-2 rounded-full bg-zinc-100 dark:bg-zinc-800 overflow-hidden">
                        <div class="h-full bg-accent rounded-full" style="width: {{ $room['percent'] }}%"></div>
                    </div>
                </div>
            @empty
                <p class="text-sm text-zinc-500 dark:text-zinc-400 italic">{{ __('No data for this period.') }}</p>
            @endforelse
        </div>

        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-6 space-y-3">
            <flux:heading size="lg">{{ __('Saved reports') }}</flux:heading>

            <div class="flex items-center justify-between py-2 border-b border-zinc-100 dark:border-zinc-800">
                <span class="text-sm text-zinc-700 dark:text-zinc-300">{{ __('Procedures report') }}</span>
                <flux:link :href="route('reports.procedures')" wire:navigate>{{ __('Open') }}</flux:link>
            </div>
            <div class="flex items-center justify-between py-2 border-b border-zinc-100 dark:border-zinc-800">
                <span class="text-sm text-zinc-700 dark:text-zinc-300">{{ __('Payouts report') }}</span>
                <flux:link :href="route('reports.payouts')" wire:navigate>{{ __('Open') }}</flux:link>
            </div>
            <div class="flex items-center justify-between py-2">
                <span class="text-sm text-zinc-700 dark:text-zinc-300">{{ __('Distribution report') }}</span>
                <flux:link :href="route('reports.distribution')" wire:navigate>{{ __('Open') }}</flux:link>
            </div>
        </div>
    </div>
</div>
