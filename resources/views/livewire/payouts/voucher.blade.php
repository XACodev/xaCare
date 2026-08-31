<?php

use App\Models\OrganizationSetting;
use App\Models\PayoutBatch;
use App\Models\Procedure;
use Illuminate\Support\Facades\Auth;

use function Livewire\Volt\{state, mount};

state([
    'batch' => null,
    'year' => null,
    'folio' => null,
    'mode' => 'summary', // summary, detailed
    'summaryRows' => [],
    'longThreshold' => null,
    'items' => [],
    'usePayScheme' => false,
    'remaining_pending_count' => 0,
    'org_name' => null,
    'voucher_legend' => null,
]);

mount(function (string|int $batch) {
    abort_unless((bool) Auth::check(), 401);
    abort_unless((bool) Auth::user()->can('payouts.view'), 403);

    $b = PayoutBatch::query()
        ->with([
            'instrumentist:id,name',
            'paidByUser:id,name',
            'items.procedure',
        ])
        ->findOrFail($batch);

    $this->mode = request('mode', 'summary');

    $this->usePayScheme = (bool) data_get($b->items, '0.snapshot.pricing_snapshot.use_pay_scheme', false);

    $rows = [
        'default_rate' => [
            'label' => __('Working Day'),
            'count' => 0,
            'unit' => 0,
            'amount' => 0.0,
        ],
        'video_rate' => [
            'label' => __('Video Surgery'),
            'count' => 0,
            'unit' => 0,
            'amount' => 0.0,
        ],
        'long_case_rate' => [
            'label' => __('Long Procedure') . ' (' . __('Greater than') . 'X min)',
            'count' => 0,
            'unit' => 0,
            'amount' => 0.0,
        ],
        'night_rate' => [
            'label' => __('Non-working Day'),
            'count' => 0,
            'unit' => 0,
            'amount' => 0.0,
        ],
    ];

    $this->batch = $b;
    $this->items = $b->items;
    $this->year = optional($this->batch->paid_at)->format('Y') ?? now()->format('Y');
    $this->folio = 'QX-' . $this->year . '-' . str_pad((string) $this->batch->id, 6, '0', STR_PAD_LEFT);

    $this->remaining_pending_count = Procedure::query()
        ->where('instrumentist_id', $b->instrumentist_id)
        ->where('status', 'pending')
        ->count();

    $orgSettings = OrganizationSetting::current();
    $this->org_name = $orgSettings->org_name;
    $this->voucher_legend = $orgSettings->voucher_legend;

    $rates = data_get($this->items, '0.snapshot.pricing_snapshot.rates');

    $unit = [
        'default_rate' => (float) $rates['default_rate'] ?? 0,
        'video_rate' => (float) $rates['video_rate'] ?? 0,
        'long_case_rate' => (float) $rates['long_case_rate'] ?? 0,
        'night_rate' => (float) $rates['night_rate'] ?? 0,
    ];

    if (!$this->usePayScheme) {
        $count = $this->items->count();
        $amount = $this->batch->total_amount;

        $rows = [
            'per_call' => [
                'label' => __('Per Call'),
                'count' => $count,
                'unit' => $unit['default_rate'],
                'amount' => $amount,
            ],
        ];

    } else {

        foreach ($this->items as $item) {
            $rule = data_get($item->snapshot, 'pricing_snapshot.rule', 'default_rate');

            if (!isset($rows[$rule]))
                $rule = 'default_rate';

            $rows[$rule]['count']++;
            $rows[$rule]['unit'] += (float) $unit[$rule];
            $rows[$rule]['amount'] += (float) $item->amount;
        }

        $this->longThreshold = data_get($this->items, '0.snapshot.pricing_snapshot.thresholds.long_case_threshold_minutes');

        if ($this->longThreshold) {
            $rows['long_case_rate']['label'] = __('Long Procedure') . ' (' . __('Greater than') . ' ' . (int) $this->longThreshold . ' min)';
        }
    }


    $this->summaryRows = $rows;
});

?>

<style>
    @page {
        size: letter portrait;
    }

    @media print {
        .no-print {
            display: none !important;
        }

        body * {
            visibility: hidden !important;
        }

        #print-content,
        #print-content * {
            visibility: visible !important;
        }

        #print-content {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
        }

        * {
            background: transparent !important;
            box-shadow: none !important;
        }
    }
</style>

<div id="print-content" class="max-w-4xl mx-auto p-4 print-wrap print:text-black print:visible">
    <div class="no-print mb-6 flex items-center justify-between gap-2">
        <a href="{{ route('payouts.index') }}"
            class="text-md text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-200 transition-colors flex items-center gap-1">
            <flux:icon.arrow-left size="sm" class="mr-2" />
            {{ __('Back') }}
        </a>

        <div class="flex gap-2">
            <a href="{{ route('payouts.voucher', ['batch' => $this->batch->id, 'mode' => 'summary']) }}">
                <flux:button variant="{{ $this->mode === 'summary' ? 'primary' : 'outline' }}">
                    {{ __('Summary') }}
                </flux:button>
            </a>

            <a href="{{ route('payouts.voucher', ['batch' => $this->batch->id, 'mode' => 'detailed']) }}">
                <flux:button variant="{{ $this->mode === 'detailed' ? 'primary' : 'outline' }}">
                    {{ __('Detailed') }}
                </flux:button>
            </a>

            <flux:button onclick="window.print()" variant="primary">
                <flux:icon.printer class="size-4 mr-2" />
                {{ __('Print') }}
            </flux:button>

            @if($this->remaining_pending_count > 0 && Auth::user()->can('payouts.create'))
                <flux:button
                    href="{{ route('payouts.create', ['instrumentist_id' => $this->batch->instrumentist_id]) }}"
                    variant="ghost" class="ms-2 border-s border-zinc-200 ps-4 dark:border-zinc-700">
                    {{ __('Liquidate again') }} ({{ $this->remaining_pending_count }})
                </flux:button>
            @endif
        </div>
    </div>

    <div
        class="rounded-xl border bg-white p-8 dark:bg-zinc-900 border-zinc-400 print:border-zinc-900 dark:border-zinc-700">
        <div class="flex flex-col md:flex-row items-center justify-between gap-6 pb-6">
            <div class="items-center text-center md:text-left md:items-start">
                <h1 class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">
                    {{ __('Payment Voucher') }}
                </h1>
                <h2 class="text-lg font-semibold text-zinc-500 print:text-zinc-700 dark:text-zinc-400">
                    {{ $this->org_name }}
                </h2>
                <p class="text-sm text-zinc-500 dark:text-zinc-400 no-print">
                   {{ __('Surgery Registry') }}
                </p>
            </div>

            <div
                class="print:w-full flex flex-col print:flex-row print:justify-between justify-between items-center md:items-end">
                <div>
                    <span class="text-zinc-700 dark:text-zinc-400">
                        {{ __('Folio') }}:
                    </span>
                    <span class="font-semibold text-zinc-900 dark:text-zinc-100">
                        {{ $this->folio }}
                    </span>
                </div>

                <div>
                    <span class="text-zinc-700 dark:text-zinc-400">
                        {{ __('Payment Date') }}:
                    </span>
                    <span class="font-semibold text-zinc-900 dark:text-zinc-100">
                        {{ optional($this->batch->paid_at)->format('Y-m-d') ?? Carbon\Carbon::parse($this->batch->paid_at)->format('Y-m-d') }}
                    </span>
                </div>

                <div class="no-print">
                    <span class="text-zinc-700 dark:text-zinc-400">
                        {{ __('Time') }}:
                    </span>
                    <span class="font-semibold text-zinc-900 dark:text-zinc-100">
                        {{ optional($this->batch->paid_at)->format('H:i') ?? Carbon\Carbon::parse($this->batch->paid_at)->format('H:i a') }}
                    </span>
                </div>
            </div>
        </div>

        <div
            class="flex flex-col gap-2 justify-between py-6 px-6 border border-zinc-400 print:border-zinc-900 dark:border-zinc-700">
            <div class="flex gap-1">
                <flux:label class="min-w-3/12">
                    {{ __('Pay to') }}:
                </flux:label>
                <flux:label class="min-w-9/12 text-zinc-900 dark:text-zinc-300 capitalize">
                    {{ $this->batch->instrumentist->name }}
                </flux:label>
            </div>
            <div class="flex gap-1">
                <flux:label class="min-w-3/12">
                    {{ __('Amount') }} ({{ __('In letters') }}):
                </flux:label>
                <flux:label class="min-w-9/12 text-zinc-900 dark:text-zinc-300 capitalize">
                    {{ Illuminate\Support\Number::spell($this->batch->total_amount, config('app.locale')) }}
                </flux:label>
            </div>
            <div class="flex gap-1">
                <flux:label class="min-w-3/12">
                    {{ __('Payment Method') }}:
                </flux:label>
                <flux:label
                    class="min-w-9/12 text-zinc-900 dark:text-zinc-300 border-b border-zinc-900 dark:border-zinc-300 capitalize">
                    {{ $this->batch->payment_method }}
                </flux:label>
            </div>
        </div>

        @if($this->mode === 'summary')

            <!-- Tabla resumida -->
            <div class="mt-6 rounded-lg border border-zinc-400 print:border-zinc-900 dark:border-zinc-700">
                <table class="w-full text-sm">
                    <thead
                        class="text-left text-zinc-500 print:text-black dark:text-zinc-400 border-b border-zinc-400 print:border-zinc-900 dark:border-zinc-700">
                        <tr>
                            <th class="py-4 px-6 font-medium">
                                <flux:label>
                                    {{ __('Concept') }}
                                </flux:label>
                            </th>
                            <th class="py-4 px-6 font-medium text-center">
                                <flux:label>
                                    {{ __('Quantity') }}
                                </flux:label>
                            </th>
                            <th class="py-4 px-6 font-medium text-right">
                                <flux:label>
                                    {{ __('Unit Price') }}
                                </flux:label>
                            </th>
                            <th class="py-4 px-6 font-medium text-right">
                                <flux:label>
                                    {{ __('Subtotal') }}
                                </flux:label>
                            </th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-zinc-300 dark:divide-zinc-700 print:text-black">
                        <tr>
                            <td colspan="1"
                                class="{{ $this->usePayScheme ? 'py-3' : 'py-6' }} pl-6 pr-2 text-justify font-mono text-zinc-800 dark:text-zinc-300">
                                {{ $this->voucher_legend }}
                            </td>
                        </tr>
                        @foreach($this->summaryRows as $key => $row)
                            <tr>
                                <td class="{{ $this->usePayScheme ? 'py-3' : 'py-6' }} px-6 text-zinc-800 dark:text-zinc-300">
                                    {{ $row['label'] }}
                                </td>
                                <td
                                    class="{{ $this->usePayScheme ? 'py-3' : 'py-6' }} px-6 text-center text-zinc-800 dark:text-zinc-300">
                                    {{ $row['count'] }}
                                </td>
                                <td
                                    class="{{ $this->usePayScheme ? 'py-3' : 'py-6' }} px-6 text-right text-zinc-800 dark:text-zinc-300">
                                    Q{{ number_format((float) $row['unit'], 2) }}
                                </td>
                                <td
                                    class="{{ $this->usePayScheme ? 'py-3' : 'py-6' }} px-6 text-right text-zinc-800 dark:text-zinc-300">
                                    Q{{ number_format((float) $row['amount'], 2) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>

                    <tfoot class="items-center">
                        <tr>
                            <td colspan="4" class="{{ $this->usePayScheme ? 'py-4' : 'py-6' }} px-6"></td>
                        </tr>
                        <tr>
                            <td colspan="2"
                                class="{{ $this->usePayScheme ? 'py-2' : 'py-4' }} px-6 border-t border-zinc-400 print:border-zinc-900 dark:border-zinc-700">
                            </td>
                            <td colspan="1"
                                class="{{ $this->usePayScheme ? 'py-2' : 'py-4' }} px-6 text-center items-center font-bold text-zinc-900 dark:text-zinc-200 border-t border-zinc-400 print:border-zinc-900 dark:border-zinc-700">
                                <flux:label>
                                    {{ __('Total') }}
                                </flux:label>
                            </td>
                            <td colspan="1"
                                class="{{ $this->usePayScheme ? 'py-2' : 'py-4' }} px-6 text-right font-bold text-zinc-900 dark:text-zinc-200 border-t border-zinc-400 print:border-zinc-900 dark:border-zinc-700">
                                <flux:label>
                                    Q{{ number_format((float) $this->batch->total_amount, 2) }}
                                </flux:label>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @else

            <!-- Tabla detallada -->
            <div class="mt-6 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="text-left text-zinc-500 dark:text-zinc-400 border-b border-zinc-200 dark:border-zinc-700">
                        <tr>
                            <th class="py-2 pr-3 font-medium">
                                <flux:label>
                                    {{ __('Date') }}
                                </flux:label>
                            </th>
                            <th class="py-2 pr-3 font-medium no-print text-center">
                                <flux:label>
                                    {{ __('Duration') }}
                                </flux:label>
                            </th>
                            <th class="py-2 pr-3 font-medium text-center">
                                <flux:label>
                                    {{ __('Patient') }}
                                </flux:label>
                            </th>
                            <th class="py-2 pr-3 font-medium text-center">
                                <flux:label>
                                    {{ __('Surgery') }}
                                </flux:label>
                            </th>
                            <th class="py-2 font-medium text-right">
                                <flux:label>
                                    {{ __('Subtotal') }}
                                </flux:label>
                            </th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @foreach($this->items as $it)
                            @php
                                $p = $it->procedure;
                            @endphp
                            <tr>
                                <td class="py-3 pr-3 text-zinc-600 dark:text-zinc-300">
                                    {{ $p->procedure_date->format('d/m/Y') ?? '-' }}
                                </td>
                                <td class="py-3 pr-3 text-zinc-600 dark:text-zinc-300 no-print text-center">
                                    {{ $p->duration_minutes ?? '-' }} min
                                </td>
                                <td class="py-3 pr-3 text-zinc-900 dark:text-zinc-100 font-medium text-center">
                                    {{ $p->patient_name ?? '-' }}
                                </td>
                                <td class="py-3 pr-3 text-zinc-600 dark:text-zinc-300">
                                    {{ $p->procedure_type ?? '-' }}
                                    @if(($p->is_videosurgery ?? false) === true)
                                        <span
                                            class="ml-2 inline-flex items-center rounded-full bg-zinc-100 dark:bg-zinc-800 px-2 py-0.5 text-xs font-medium text-zinc-800 dark:text-zinc-200 no-print">
                                            {{ __('Video') }}
                                        </span>
                                    @endif
                                </td>
                                <td class="py-3 text-right font-mono font-medium text-zinc-900 dark:text-zinc-100">
                                    Q{{ number_format((float) $it->amount, 2) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>

                    <tfoot>
                        <tr>
                            <td colspan="4" class="print:hidden"></td>
                            <td colspan="3" class="print:table-cell hidden"></td>
                            <td colspan="1" class="pt-8 border-b border-zinc-200 dark:border-zinc-700 print:table-cell">
                            </td>
                        </tr>
                        <tr>
                            <td colspan="5" class="pt-4 text-right font-bold text-zinc-900 dark:text-zinc-100 no-print">
                                {{ __('Total') }}
                            </td>
                            <td colspan="4"
                                class="pt-4 text-right font-bold text-zinc-900 dark:text-zinc-100 hidden print:table-cell">
                                {{ __('Total') }}
                            </td>
                        </tr>
                        <tr>
                            <td colspan="5" class="text-right font-bold text-xl text-zinc-900 dark:text-zinc-100">
                                Q{{ number_format((float) $this->batch->total_amount, 2) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

        @endif

        <!-- Footer signature -->
        <div
            class="grid grid-cols-3 gap-12 text-center items-center text-xs {{ $this->usePayScheme ? 'mt-12' : 'mt-16' }}">
            <div>
                <div class="text-zinc-500 print:text-black dark:text-zinc-400 mb-12">
                    {{ __('Received by') }}
                </div>
                <div
                    class="border-t border-zinc-300 print:border-zinc-900 dark:border-zinc-600 pt-2 text-zinc-900 dark:text-zinc-100">
                    {{ $this->batch->instrumentist->name ?? '' }}
                </div>
            </div>

            <div>
                <div class="text-zinc-500 print:text-black dark:text-zinc-400 mb-12">
                    {{ __('Paid by') }} ({{ __('Administration') }})
                </div>
                <div
                    class="border-t border-zinc-300 print:border-zinc-900 dark:border-zinc-600 pt-2 text-zinc-900 dark:text-zinc-100">
                    {{ $this->batch->paidByUser->name ?? '' }}
                </div>
            </div>

            <div>
                <div class="text-zinc-500 print:text-black dark:text-zinc-400 mb-12">
                    {{ __('Authorized Signature') }}
                </div>
                <div
                    class="border-t border-zinc-300 print:border-zinc-900 dark:border-zinc-600 pt-2 text-zinc-900 dark:text-zinc-100">
                    {{ __('Medical Director') }}
                </div>
            </div>
        </div>

        <!-- Footer note -->
        <p
            class="hidden print:block {{ $this->usePayScheme ? 'mt-10' : 'mt-18' }} text-xs text-zinc-400 dark:text-zinc-500 text-center">
            {{ __('Generated by ' . config('app.name') . '. Keep for internal control.') }}
        </p>
    </div>
</div>