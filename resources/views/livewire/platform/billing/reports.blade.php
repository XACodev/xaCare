<?php

use App\Enums\SubscriptionStatus;
use App\Services\BillingReportService;
use Illuminate\Support\Facades\Auth;

use function Livewire\Volt\{state, mount, computed, layout};

layout('components.layouts.platform');

state([
    'status_filter' => '',
    'plan_filter' => '',
]);

mount(function () {
    abort_unless(Auth::check() && Auth::user()->is_platform_admin, 403);
});

$hospitals = computed(function () {
    return app(BillingReportService::class)->filteredHospitals(
        $this->status_filter ?: null,
        $this->plan_filter ?: null,
    );
});

$mrr = computed(fn () => app(BillingReportService::class)->estimatedMrr());

$byStatus = computed(fn () => app(BillingReportService::class)->hospitalsByStatus());

$expiringTrials = computed(fn () => [
    7 => app(BillingReportService::class)->trialsExpiringWithin(7)->count(),
    14 => app(BillingReportService::class)->trialsExpiringWithin(14)->count(),
    30 => app(BillingReportService::class)->trialsExpiringWithin(30)->count(),
]);

$exportCsv = function () {
    abort_unless((bool) Auth::user()->is_platform_admin, 403);

    $service = app(BillingReportService::class);
    $rows = $service->toCsvRows($service->filteredHospitals($this->status_filter ?: null, $this->plan_filter ?: null));

    return response()->streamDownload(function () use ($rows) {
        $handle = fopen('php://output', 'w');
        fputcsv($handle, ['name', 'plan', 'subscription_status', 'trial_ends_at', 'is_active']);

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        fclose($handle);
    }, 'billing-report-'.now()->format('Y-m-d').'.csv');
};

?>

<div class="flex h-full w-full flex-1 flex-col gap-6 rounded-xl">
    <flux:heading size="xl">{{ __('Reportes de facturación') }}</flux:heading>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-6">
            <flux:subheading>{{ __('MRR estimado') }}</flux:subheading>
            <p class="text-2xl font-semibold">${{ number_format($this->mrr, 2) }}</p>
        </div>

        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-6">
            <flux:subheading>{{ __('Hospitales por status') }}</flux:subheading>
            <div class="mt-2 flex flex-wrap gap-2">
                @foreach($this->byStatus as $status => $count)
                    <flux:badge size="sm" color="{{ $status === 'active' ? 'green' : ($status === 'trialing' ? 'amber' : 'red') }}">
                        {{ $status }}: {{ $count }}
                    </flux:badge>
                @endforeach
            </div>
        </div>

        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-6">
            <flux:subheading>{{ __('Trials por vencer') }}</flux:subheading>
            <div class="mt-2 flex flex-wrap gap-2 text-sm">
                <span>7d: {{ $this->expiringTrials[7] }}</span>
                <span>14d: {{ $this->expiringTrials[14] }}</span>
                <span>30d: {{ $this->expiringTrials[30] }}</span>
            </div>
        </div>
    </div>

    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-6 space-y-4">
        <div class="flex flex-col sm:flex-row items-start sm:items-end justify-between gap-4">
            <div class="flex flex-col sm:flex-row gap-4">
                <div>
                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">{{ __('Status') }}</label>
                    <select wire:model.live="status_filter"
                        class="w-full rounded-lg border-zinc-200 dark:border-zinc-800 bg-indigo-50 dark:bg-zinc-700/60 text-zinc-900 dark:text-zinc-100 focus:ring-0 focus:border-zinc-500 p-2.5">
                        <option value="">{{ __('Todos') }}</option>
                        @foreach(SubscriptionStatus::cases() as $status)
                            <option value="{{ $status->value }}">{{ $status->value }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">{{ __('Plan') }}</label>
                    <select wire:model.live="plan_filter"
                        class="w-full rounded-lg border-zinc-200 dark:border-zinc-800 bg-indigo-50 dark:bg-zinc-700/60 text-zinc-900 dark:text-zinc-100 focus:ring-0 focus:border-zinc-500 p-2.5">
                        <option value="">{{ __('Todos') }}</option>
                        @foreach(config('billing.plans') as $planKey => $plan)
                            <option value="{{ $planKey }}">{{ $plan['name'] }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <flux:button wire:click="exportCsv" icon="arrow-down-tray">{{ __('Exportar CSV') }}</flux:button>
        </div>

        <div class="overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
            <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                <thead class="bg-zinc-50 dark:bg-zinc-800/50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-zinc-500 tracking-wider">{{ __('Name') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-zinc-500 tracking-wider">{{ __('Plan') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-zinc-500 tracking-wider">{{ __('Status') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-zinc-500 tracking-wider">{{ __('Trial ends') }}</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-zinc-500 tracking-wider">{{ __('Active') }}</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-zinc-900 divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse($this->hospitals as $hospital)
                        <tr>
                            <td class="px-4 py-3 text-sm text-zinc-900 dark:text-zinc-100">
                                <flux:link href="{{ route('platform.hospitals.edit', $hospital) }}" wire:navigate>
                                    {{ $hospital->name }}
                                </flux:link>
                            </td>
                            <td class="px-4 py-3 text-sm text-zinc-500 dark:text-zinc-400">{{ $hospital->plan }}</td>
                            <td class="px-4 py-3 text-sm text-zinc-500 dark:text-zinc-400">{{ $hospital->subscription_status->value }}</td>
                            <td class="px-4 py-3 text-sm text-zinc-500 dark:text-zinc-400">{{ $hospital->trial_ends_at?->format('Y-m-d H:i') ?? '—' }}</td>
                            <td class="px-4 py-3 text-center">
                                <flux:badge size="sm" color="{{ $hospital->is_active ? 'green' : 'red' }}">
                                    {{ $hospital->is_active ? __('Yes') : __('No') }}
                                </flux:badge>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-6 text-center text-sm text-zinc-500">{{ __('No hay hospitales que coincidan con el filtro.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
