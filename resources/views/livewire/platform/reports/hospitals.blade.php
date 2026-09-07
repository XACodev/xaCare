<?php

use App\Modules\Reports\Services\ReportService;
use Illuminate\Support\Facades\Auth;

use function Livewire\Volt\{mount, computed, layout};

layout('components.layouts.platform');

mount(function () {
    abort_unless(Auth::check() && Auth::user()->is_platform_admin, 403);
});

$hospitals = computed(fn () => app(ReportService::class)->hospitalsWithPlans());

$exportCsv = function () {
    abort_unless((bool) Auth::user()?->is_platform_admin, 403);

    $hospitals = app(ReportService::class)->hospitalsWithPlans();

    return response()->streamDownload(function () use ($hospitals) {
        $handle = fopen('php://output', 'w');
        fputcsv($handle, ['name', 'plan', 'subscription_status', 'is_active']);

        foreach ($hospitals as $hospital) {
            fputcsv($handle, [$hospital->name, $hospital->plan, $hospital->subscription_status->value, $hospital->is_active ? '1' : '0']);
        }

        fclose($handle);
    }, 'hospitals-report-'.now()->format('Y-m-d').'.csv');
};

?>

<div class="flex h-full w-full flex-1 flex-col gap-6 rounded-xl">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">{{ __('Hospitales activos') }}</flux:heading>
        <flux:button wire:click="exportCsv" icon="arrow-down-tray">{{ __('Export CSV') }}</flux:button>
    </div>

    <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900">
        <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
            <thead class="bg-zinc-50 dark:bg-zinc-800/50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-zinc-500 tracking-wider">{{ __('Name') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-zinc-500 tracking-wider">{{ __('Plan') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-zinc-500 tracking-wider">{{ __('Status') }}</th>
                    <th class="px-4 py-3 text-center text-xs font-semibold text-zinc-500 tracking-wider">{{ __('Active') }}</th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-zinc-900 divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse($this->hospitals as $hospital)
                    <tr>
                        <td class="px-4 py-3 text-sm text-zinc-900 dark:text-zinc-100">{{ $hospital->name }}</td>
                        <td class="px-4 py-3 text-sm text-zinc-500 dark:text-zinc-400">{{ $hospital->plan }}</td>
                        <td class="px-4 py-3 text-sm text-zinc-500 dark:text-zinc-400">{{ $hospital->subscription_status->value }}</td>
                        <td class="px-4 py-3 text-center">
                            <flux:badge size="sm" color="{{ $hospital->is_active ? 'green' : 'red' }}">
                                {{ $hospital->is_active ? __('Yes') : __('No') }}
                            </flux:badge>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-6 text-center text-sm text-zinc-500">{{ __('No hay hospitales.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
