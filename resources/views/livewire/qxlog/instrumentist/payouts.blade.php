<?php

use App\Modules\QxLog\Models\SurgicalAssignment;
use App\Modules\QxLog\Models\PayoutBatch;
use Illuminate\Support\Facades\Auth;

use function Livewire\Volt\{state, mount, computed};

state([
    // Pending
    'pending' => [],
    'pending_count' => 0,
    'pending_total' => 0.0,

    // History
    'from' => '',
    'to' => '',
]);

mount(function () {
    $user = Auth::user();
    abort_unless(Auth::check(), 401);

    $this->from = Carbon\Carbon::now()->subMonth()->format('Y-m-d');
    $this->to = Carbon\Carbon::now()->format('Y-m-d');

    // ======================
    // Pending procedures
    // ======================
    $pendingBase = SurgicalAssignment::query()
        ->where('user_id', $user->id)
        ->where('status', 'pending');

    $this->pending_count = (int) $pendingBase->count();
    $this->pending_total = (float) $pendingBase->sum('calculated_amount');

    $this->pending = $pendingBase
        ->with(['surgicalCase', 'surgicalRole'])
        ->orderByDesc('created_at')
        ->limit(50)
        ->get();

});

$batches = computed(function () {
    $user = Auth::user();

    $query = PayoutBatch::query()
        ->where('payee_id', $user->id)
        ->withCount('items')
        ->orderByDesc('paid_at');

    if ($this->from) {
        $query->whereDate('paid_at', '>=', $this->from);
    }
    if ($this->to) {
        $query->whereDate('paid_at', '<=', $this->to);
    }

    return $query->limit(100)->get();
});

$ruleLabel = function (?string $rule) {
    return match ($rule) {
        'video_rate' => __('Video'),
        'night_rate' => __('Night'),
        'long_case_rate' => __('Long'),
        default => __('Base Rate'),
    };
};

$ruleColor = function (?string $rule) {
    return match ($rule) {
        'video_rate' => 'indigo',
        'night_rate' => 'rose',
        'long_case_rate' => 'amber',
        default => 'zinc',
    };
};

?>

<div class="max-w-6xl mx-auto p-4 space-y-8">
    <div class="flex items-center justify-between gap-3">
        <div>
            <flux:heading size="xl">{{ __('My Payouts') }}</flux:heading>
            <flux:subheading>{{ __('Pending balance and paid vouchers.') }}</flux:subheading>
        </div>
    </div>

    {{-- Pending --}}
    <section>
        <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4 mb-4">
            <div>
                <flux:heading size="lg">{{ __('Pending') }}</flux:heading>
                <flux:subheading>{{ __('Estimated total of procedures not yet paid.') }}</flux:subheading>
            </div>

            <div
                class="flex items-center gap-4 bg-indigo-50 dark:bg-indigo-500/10 px-6 py-3 rounded-full border border-indigo-100 dark:border-indigo-500/20">
                <div class="text-right flex flex-col items-center gap-1">
                    <div class="text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">
                        {{ __('Procedures') }}
                    </div>
                    <div class="text-xl font-bold text-zinc-900 dark:text-zinc-200 leading-none">
                        {{ $this->pending_count }}
                    </div>
                </div>

                <div class="w-px h-8 bg-indigo-200 dark:bg-indigo-500/30"></div>

                <div class="text-right flex flex-col items-center gap-1">
                    <div class="text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">
                        {{ __('Amount') }}
                    </div>
                    <div class="text-xl font-bold text-indigo-600 dark:text-indigo-300 leading-none">
                        Q{{ number_format((float) $this->pending_total, 2) }}
                    </div>
                </div>
            </div>
        </div>

        <div
            class="rounded-xl border border-zinc-200 bg-white shadow-sm dark:bg-zinc-900 dark:border-zinc-700 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm divide-y divide-zinc-200 dark:divide-zinc-700">
                    <thead class="bg-zinc-50 dark:bg-zinc-800/50 text-zinc-500 dark:text-zinc-400">
                        <tr>
                            <th class="px-6 py-3 text-left font-medium">{{ __('Date') }}</th>
                            <th class="px-6 py-3 text-left font-medium">{{ __('Start') }}</th>
                            <th class="px-6 py-3 text-left font-medium">{{ __('Patient') }}</th>
                            <th class="px-6 py-3 text-left font-medium">{{ __('Procedure') }}</th>
                            <th class="px-6 py-3 text-right font-medium">{{ __('Amount') }}</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @forelse($this->pending as $p)
                            <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30 transition-colors">
                                <td class="px-6 py-4 text-zinc-600 dark:text-zinc-300 whitespace-nowrap">
                                    {{ \Carbon\Carbon::parse($p->surgicalCase->procedure_date)->format('d/m/Y') ?? '-' }}
                                </td>
                                <td class="px-6 py-4 text-zinc-600 dark:text-zinc-300 whitespace-nowrap">
                                    {{ \Carbon\Carbon::parse($p->surgicalCase->start_time)->format('H:i') ?? '-' }}
                                </td>
                                <td class="px-6 py-4 text-zinc-900 dark:text-zinc-100 font-medium whitespace-nowrap">
                                    {{ $p->surgicalCase->patient_name ?? '-' }}
                                </td>
                                <td class="px-6 py-4 text-zinc-600 dark:text-zinc-300 whitespace-nowrap">
                                    <div class="flex items-center gap-2">
                                        <span>{{ $p->surgicalCase->procedure_type ?? '-' }}</span>
                                        @if (Auth::user()->use_pay_scheme)
                                            <x-procedure-rule-badge :rule="data_get($p->pricing_snapshot, 'rule')"
                                                :videosurgery="$p->surgicalCase->is_videosurgery" />
                                        @else
                                            @if ($p->surgicalCase->is_videosurgery)
                                                <flux:badge color="indigo" size="sm" icon="video-camera">{{ __('Video') }}
                                                </flux:badge>
                                            @endif
                                        @endif
                                    </div>
                                </td>
                                <td
                                    class="px-6 py-4 text-right font-medium text-zinc-900 dark:text-zinc-100 whitespace-nowrap">
                                    Q{{ number_format((float) ($p->calculated_amount ?? 0), 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-12 text-center text-zinc-500 dark:text-zinc-400">
                                    <div class="flex flex-col items-center gap-2">
                                        <flux:icon.document-text class="size-6 opacity-40" />
                                        <p>{{ __('No pending procedures.') }}</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($this->pending_count > count($this->pending))
                <div class="px-6 py-3 bg-zinc-50 dark:bg-zinc-800/50 border-t border-zinc-200 dark:border-zinc-700">
                    <p class="text-xs text-zinc-500 dark:text-zinc-400 text-center">
                        {{ __('Showing latest') }} {{ count($this->pending) }}. {{ __('You have more pending items.') }}
                    </p>
                </div>
            @endif
        </div>
    </section>

    {{-- History --}}
    <section>
        <div class="flex flex-col md:flex-row items-start md:items-end justify-between gap-4 mb-4">
            <div>
                <flux:heading size="lg">{{ __('History') }}</flux:heading>
                <flux:subheading>{{ __('Paid vouchers.') }}</flux:subheading>
            </div>

            <div class="flex flex-col md:flex-row items-center md:items-end gap-2 md:gap-3 w-full md:w-auto">
                <flux:input type="date" wire:model.live="from" label="{{ __('From') }}" class="w-full md:w-40" />
                <flux:input type="date" wire:model.live="to" label="{{ __('To') }}" class="w-full md:w-40" />
            </div>
        </div>

        <div
            class="rounded-xl border border-zinc-200 bg-white shadow-sm dark:bg-zinc-900 dark:border-zinc-700 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm divide-y divide-zinc-200 dark:divide-zinc-700">
                    <thead class="bg-zinc-50 dark:bg-zinc-800/50 text-zinc-500 dark:text-zinc-400">
                        <tr>
                            <th class="px-6 py-3 text-left font-medium text-zinc-900 dark:text-zinc-100">
                                {{ __('Voucher') }}
                            </th>
                            <th class="px-6 py-3 text-left font-medium text-zinc-900 dark:text-zinc-100">
                                {{ __('Date of Payment') }}
                            </th>
                            <th class="px-6 py-3 text-right font-medium text-zinc-900 dark:text-zinc-100">
                                {{ __('Total Procedures') }}
                            </th>
                            <th class="px-6 py-3 text-right font-medium text-zinc-900 dark:text-zinc-100">
                                {{ __('Total Amount') }}
                            </th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @forelse($this->batches as $b)
                            <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30 transition-colors">
                                <td class="px-6 py-4 text-zinc-900 dark:text-zinc-100">
                                    {{ 'QX-' . Carbon\Carbon::parse($b->paid_at)->format('Y') . '-' . str_pad((string) $b->id, 6, '0', STR_PAD_LEFT) }}
                                </td>
                                <td class="px-6 py-4 text-zinc-600 dark:text-zinc-300 whitespace-nowrap">
                                    <div class="flex flex-col w-full md:w-auto md:flex-row md:items-center md:gap-2">
                                        {{ Carbon\Carbon::parse($b->paid_at)->format('Y-m-d') }}
                                        <span class="text-xs text-zinc-500 dark:text-zinc-400">
                                            {{ Carbon\Carbon::parse($b->paid_at)->format('h:i a') }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-right text-zinc-900 dark:text-zinc-100">
                                    {{ (int) ($b->items_count ?? 0) }}
                                </td>
                                <td class="px-6 py-4 text-right font-medium text-zinc-900 dark:text-zinc-100">
                                    Q{{ number_format((float) ($b->total_amount ?? 0), 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-12 text-center text-zinc-500 dark:text-zinc-400">
                                    <div class="flex flex-col items-center gap-2">
                                        <flux:icon.currency-dollar class="size-6 opacity-40" />
                                        <p>{{ __('No payments yet.') }}</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</div>