<?php

use App\Modules\Reports\Services\ReportService;
use Illuminate\Support\Facades\Auth;

use function Livewire\Volt\{state, mount, computed, layout};

layout('components.layouts.platform');

state([
    'date_from' => '',
    'date_to' => '',
]);

mount(function () {
    abort_unless(Auth::check() && Auth::user()->is_platform_admin, 403);
});

$byHospital = computed(fn () => app(ReportService::class)->proceduresTotalByHospital($this->date_from ?: null, $this->date_to ?: null));

$exportCsv = function () {
    abort_unless((bool) Auth::user()?->is_platform_admin, 403);

    $rows = app(ReportService::class)->proceduresTotalByHospital($this->date_from ?: null, $this->date_to ?: null);

    return response()->streamDownload(function () use ($rows) {
        $handle = fopen('php://output', 'w');
        fputcsv($handle, ['hospital', 'total_procedures']);

        foreach ($rows as $row) {
            fputcsv($handle, [$row['hospital'], $row['total']]);
        }

        fclose($handle);
    }, 'procedures-by-hospital-'.now()->format('Y-m-d').'.csv');
};

?>

<div class="flex h-full w-full flex-1 flex-col gap-6 rounded-xl">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">{{ __('Procedimientos totales por hospital') }}</flux:heading>
        <flux:button wire:click="exportCsv" icon="arrow-down-tray">{{ __('Export CSV') }}</flux:button>
    </div>

    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-6 space-y-4">
        <div class="flex flex-col sm:flex-row gap-4">
            <div>
                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">{{ __('From') }}</label>
                <input type="date" wire:model.live="date_from"
                    class="w-full rounded-lg border-zinc-200 dark:border-zinc-800 bg-indigo-50 dark:bg-zinc-700/60 p-2.5">
            </div>
            <div>
                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">{{ __('To') }}</label>
                <input type="date" wire:model.live="date_to"
                    class="w-full rounded-lg border-zinc-200 dark:border-zinc-800 bg-indigo-50 dark:bg-zinc-700/60 p-2.5">
            </div>
        </div>

        <div class="overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
            <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                <thead class="bg-zinc-50 dark:bg-zinc-800/50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-zinc-500 tracking-wider">{{ __('Hospital') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-zinc-500 tracking-wider">{{ __('Procedures') }}</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-zinc-900 divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse($this->byHospital as $row)
                        <tr>
                            <td class="px-4 py-3 text-sm text-zinc-900 dark:text-zinc-100">{{ $row['hospital'] }}</td>
                            <td class="px-4 py-3 text-sm text-right">{{ $row['total'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2" class="px-4 py-6 text-center text-sm text-zinc-500">{{ __('No hay datos.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
