<?php

use App\Models\PayoutBatch;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

use function Livewire\Volt\{state, computed, mount};

state([
    // filtros
    'instrumentist_id' => '',
    'date_from' => '',
    'date_to' => '',

    // data select
    'instrumentists' => [],
]);

mount(function () {
    $user = Auth::user();
    abort_unless((bool) $user, 401);
    abort_unless($user->can('payouts.view'), 403);

    $this->instrumentists = User::query()
        ->whereIn('id', PayoutBatch::query()->distinct()->pluck('payee_id'))
        ->orderBy('name')
        ->get(['id', 'name'])
        ->map(fn($u) => ['id' => $u->id, 'name' => $u->name]);
});

$batches = computed(function () {

    if ($this->instrumentist_id === '') {
        return collect();
    }

    $q = PayoutBatch::query()
        ->with([
            'payee:id,name',
            'paidByUser:id,name',
        ])
        ->orderByDesc('paid_at');

    if ($this->instrumentist_id !== 'all') {
        $q->where('payee_id', (int) $this->instrumentist_id);
    }

    if ($this->date_from) {
        $q->whereDate('paid_at', '>=', $this->date_from);
    }

    if ($this->date_to) {
        $q->whereDate('paid_at', '<=', $this->date_to);
    }

    return $q->limit(100)->get();
});

?>

<div class="max-w-6xl mx-auto p-4 space-y-6">
    <div class="mb-4">
        <flux:heading size="xl">{{ __('Payouts') }}</flux:heading>
        <flux:subheading>{{ __('Settlement History') }}</flux:subheading>
    </div>

    <div class="rounded-xl border bg-white p-6 dark:bg-zinc-900 dark:border-zinc-700 space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <flux:field>
                <flux:label>{{ __('Instrumentist') }}</flux:label>
                <flux:select wire:model.change="instrumentist_id" placeholder="{{ __('Select instrumentist') }}">
                    <flux:select.option value="all">{{ __('All') }}</flux:select.option>
                    @foreach($instrumentists as $i)
                        <flux:select.option value="{{ $i['id'] }}">
                            {{ $i['name'] }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>

            <flux:field>
                <flux:label>{{ __('From') }}</flux:label>
                <flux:input type="date" wire:model.live="date_from" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('To') }}</flux:label>
                <flux:input type="date" wire:model.live="date_to" />
            </flux:field>
        </div>

        <div class="overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
            {{-- Desktop Table --}}
            <div class="hidden md:block overflow-x-auto">
                <table class="min-w-full text-sm divide-y divide-zinc-200 dark:divide-zinc-700">
                    <thead class="bg-zinc-50 dark:bg-zinc-800 text-left text-zinc-500 dark:text-zinc-400">
                        <tr>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider">{{ __('Date') }}</th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider">
                                {{ __('Instrumentist') }}
                            </th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-right">
                                {{ __('Total') }}
                            </th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider">{{ __('Paid by') }}
                            </th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-right">
                                {{ __('Actions') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700 bg-white dark:bg-zinc-900">
                        @forelse($this->batches as $b)
                            <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors">
                                <td class="px-4 py-3 text-sm text-zinc-600 dark:text-zinc-300">
                                    {{ optional($b->paid_at)->format('Y-m-d H:i') ?? $b->paid_at }}
                                </td>
                                <td class="px-4 py-3 text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                    {{ $b->payee->name ?? ('#' . $b->payee_id) }}
                                </td>
                                <td
                                    class="px-4 py-3 text-sm text-right font-mono font-bold text-emerald-600 dark:text-emerald-400">
                                    Q{{ number_format((float) $b->total_amount, 2) }}
                                </td>
                                <td class="px-4 py-3 text-sm text-zinc-600 dark:text-zinc-300">
                                    {{ $b->paidByUser->name ?? ('#' . $b->paid_by_user_id) }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <flux:button href="{{ route('payouts.voucher', $b->id) }}" variant="ghost" size="sm"
                                        icon="document-text">
                                        {{ __('Voucher') }}
                                    </flux:button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-zinc-500 dark:text-zinc-400">
                                    @if ($this->instrumentist_id === '')
                                        {{ __('Select an instrumentist to see their payments.') }}
                                    @else
                                        {{ __('No payments registered yet.') }}
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Mobile Cards --}}
            <div class="md:hidden divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse($this->batches as $b)
                    <div class="p-4 bg-white dark:bg-zinc-900 space-y-3">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <div class="font-medium text-zinc-900 dark:text-zinc-100">
                                    {{ $b->payee->name ?? ('#' . $b->payee_id) }}
                                </div>
                                <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ optional($b->paid_at)->format('Y-m-d H:i') ?? $b->paid_at }}
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="font-mono font-bold text-emerald-600 dark:text-emerald-400">
                                    Q{{ number_format((float) $b->total_amount, 2) }}</div>
                            </div>
                        </div>

                        <div class="flex items-center justify-between text-sm pt-2">
                            <div class="text-zinc-500 dark:text-zinc-400">
                                <span class="text-xs uppercase tracking-wide">{{ __('Paid by') }}:</span>
                                {{ $b->paidByUser->name ?? ('#' . $b->paid_by_user_id) }}
                            </div>
                            <flux:button href="{{ route('payouts.voucher', $b->id) }}" variant="filled" size="sm">
                                {{ __('Voucher') }}
                            </flux:button>
                        </div>
                    </div>
                @empty
                    <div class="p-8 text-center text-zinc-500 dark:text-zinc-400">
                        @if ($this->instrumentist_id === '')
                            {{ __('Select an instrumentist to see their payments.') }}
                        @else
                            {{ __('No payments registered yet.') }}
                        @endif
                    </div>
                @endforelse
            </div>
        </div>

        <p class="text-xs text-zinc-500 dark:text-zinc-400 text-center">
            {{ __('Showing maximum 100 records for performance.') }}
        </p>
    </div>
</div>