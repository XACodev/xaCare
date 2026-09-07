<?php

use App\Modules\Reports\Services\ReportService;
use Illuminate\Support\Facades\Auth;

use function Livewire\Volt\{state, mount, computed};

state([
    'date_from' => '',
    'date_to' => '',
]);

mount(function () {
    abort_unless((bool) (Auth::user()?->hasRole('admin') || Auth::user()?->is_platform_admin), 403);
});

$procedures = computed(fn () => app(ReportService::class)->proceduresByDateRange($this->date_from ?: null, $this->date_to ?: null));

$total = computed(fn () => $this->procedures->sum(fn ($p) => (float) $p->assignments->sum('calculated_amount')));

$exportCsv = function () {
    abort_unless((bool) (Auth::user()?->hasRole('admin') || Auth::user()?->is_platform_admin), 403);

    $procedures = app(ReportService::class)->proceduresByDateRange($this->date_from ?: null, $this->date_to ?: null);

    return response()->streamDownload(function () use ($procedures) {
        $handle = fopen('php://output', 'w');
        fputcsv($handle, ['date', 'patient', 'procedure_type', 'status', 'amount']);

        foreach ($procedures as $p) {
            fputcsv($handle, [
                $p->procedure_date?->format('Y-m-d'),
                $p->patient_name,
                $p->procedure_type,
                $p->status,
                (float) $p->assignments->sum('calculated_amount'),
            ]);
        }

        fclose($handle);
    }, 'procedures-report-'.now()->format('Y-m-d').'.csv');
};

?>

<div class="max-w-6xl mx-auto p-4 space-y-6">
    <flux:heading size="xl">{{ __('Procedures Report') }}</flux:heading>

    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-6 space-y-4">
        <div class="flex flex-col sm:flex-row items-start sm:items-end justify-between gap-4">
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

            <flux:button wire:click="exportCsv" icon="arrow-down-tray">{{ __('Export CSV') }}</flux:button>
        </div>

        <div class="text-sm font-semibold text-emerald-600 dark:text-emerald-400">
            {{ __('Total') }}: Q{{ number_format($this->total, 2) }} ({{ $this->procedures->count() }} {{ __('procedures') }})
        </div>

        <div class="overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
            <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                <thead class="bg-zinc-50 dark:bg-zinc-800/50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-zinc-500 tracking-wider">{{ __('Date') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-zinc-500 tracking-wider">{{ __('Patient') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-zinc-500 tracking-wider">{{ __('Procedure') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-zinc-500 tracking-wider">{{ __('Status') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-zinc-500 tracking-wider">{{ __('Amount') }}</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-zinc-900 divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse($this->procedures as $p)
                        <tr>
                            <td class="px-4 py-3 text-sm text-zinc-500 dark:text-zinc-400">{{ $p->procedure_date?->format('Y-m-d') }}</td>
                            <td class="px-4 py-3 text-sm text-zinc-900 dark:text-zinc-100">{{ $p->patient_name }}</td>
                            <td class="px-4 py-3 text-sm text-zinc-500 dark:text-zinc-400">{{ $p->procedure_type }}</td>
                            <td class="px-4 py-3 text-sm text-zinc-500 dark:text-zinc-400">{{ __($p->status) }}</td>
                            <td class="px-4 py-3 text-sm text-right font-medium">Q{{ number_format($p->assignments->sum('calculated_amount'), 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-6 text-center text-sm text-zinc-500">{{ __('No results found') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
