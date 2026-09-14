<?php

use App\Modules\QxLog\Models\PayoutBatch;
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
        ->withCount('items')
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

<div class="max-w-6xl mx-auto p-4 space-y-4">
    <div>
        <flux:heading size="xl">{{ __('Payouts') }}</flux:heading>
        <flux:subheading>{{ __('Settlement History') }}</flux:subheading>
    </div>

    <div class="flex flex-wrap gap-3 items-end">
        <flux:field class="w-56">
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

        <flux:field class="w-44">
            <flux:label>{{ __('From') }}</flux:label>
            <flux:input type="date" wire:model.live="date_from" />
        </flux:field>

        <flux:field class="w-44">
            <flux:label>{{ __('To') }}</flux:label>
            <flux:input type="date" wire:model.live="date_to" />
        </flux:field>
    </div>

    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 overflow-hidden">
        {{-- Desktop Table --}}
        <div class="hidden md:block overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="qx-table-head">
                        <th scope="col" class="px-4 py-3 text-left font-semibold tracking-wider">{{ __('Voucher') }}</th>
                        <th scope="col" class="px-4 py-3 text-left font-semibold tracking-wider">{{ __('Instrumentist') }}</th>
                        <th scope="col" class="px-4 py-3 text-left font-semibold tracking-wider">{{ __('Date') }}</th>
                        <th scope="col" class="px-4 py-3 text-left font-semibold tracking-wider">{{ __('Procs.') }}</th>
                        <th scope="col" class="px-4 py-3 text-right font-semibold tracking-wider">{{ __('Total') }}</th>
                        <th scope="col" class="px-4 py-3 text-left font-semibold tracking-wider">{{ __('Status') }}</th>
                        <th scope="col" class="px-4 py-3 text-right font-semibold tracking-wider">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($this->batches as $b)
                        <tr wire:key="batch-{{ $b->id }}" class="qx-table-row">
                            <td class="px-4 py-3 font-semibold text-accent tabular-nums">#{{ $b->id }}</td>
                            <td class="px-4 py-3 font-medium">{{ $b->payee->name ?? ('#' . $b->payee_id) }}</td>
                            <td class="px-4 py-3 text-zinc-700 dark:text-zinc-300 tabular-nums">
                                {{ optional($b->paid_at)->format('d/m/Y') ?? $b->paid_at }}
                            </td>
                            <td class="px-4 py-3 text-zinc-700 dark:text-zinc-300 tabular-nums">{{ $b->items_count }}</td>
                            <td class="px-4 py-3 text-right font-semibold tabular-nums">
                                Q{{ number_format((float) $b->total_amount, 2) }}
                            </td>
                            <td class="px-4 py-3">
                                <flux:badge :variant="$b->status === 'paid' ? 'success' : 'warning'">
                                    {{ ucfirst($b->status) }}
                                </flux:badge>
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
                            <td colspan="7" class="px-6 py-8 text-center text-sm text-zinc-500 dark:text-zinc-400 italic">
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
        <div class="md:hidden divide-y divide-zinc-100 dark:divide-zinc-800">
            @forelse($this->batches as $b)
                <div class="p-4 space-y-2">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div class="font-medium text-zinc-900 dark:text-zinc-100">
                                {{ $b->payee->name ?? ('#' . $b->payee_id) }}
                            </div>
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                #{{ $b->id }} · {{ optional($b->paid_at)->format('d/m/Y') ?? $b->paid_at }} · {{ $b->items_count }} {{ __('Procs.') }}
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="font-semibold tabular-nums">Q{{ number_format((float) $b->total_amount, 2) }}</div>
                            <flux:badge size="sm" :variant="$b->status === 'paid' ? 'success' : 'warning'">
                                {{ ucfirst($b->status) }}
                            </flux:badge>
                        </div>
                    </div>

                    <div class="flex items-center justify-between text-sm pt-1">
                        <div class="text-zinc-500 dark:text-zinc-400">
                            <span class="text-xs uppercase tracking-wide">{{ __('Paid by') }}:</span>
                            {{ $b->paidByUser->name ?? ('#' . $b->paid_by_id) }}
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