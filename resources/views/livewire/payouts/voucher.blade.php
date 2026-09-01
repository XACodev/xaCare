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
    'org_logo_url' => null,
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

    $orgSettings = OrganizationSetting::forHospital($b->hospital_id);
    $this->org_name = $orgSettings->org_name;
    $this->voucher_legend = $orgSettings->voucher_legend;
    $this->org_logo_url = $orgSettings->logoUrl();

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
    @import url('https://fonts.googleapis.com/css2?family=Source+Serif+4:opsz,wght@8..60,500;8..60,600;8..60,700&display=swap');

    @page {
        size: letter portrait;
    }

    #print-content {
        --paper: #ffffff;
        --ink: #14181f;
        --ink-soft: #5a6472;
        --line: #d8dee2;
        --accent-teal: #1f9d86;
        --accent-blue: #3f6fd6;
        --stamp: #0d1420;
    }

    .dark #print-content {
        --paper: #18181b;
        --ink: #f4f4f5;
        --ink-soft: #a1a1aa;
        --line: #3f3f46;
    }

    .voucher-serif {
        font-family: 'Source Serif 4', Georgia, 'Times New Roman', serif;
    }

    .voucher-mono {
        font-family: ui-monospace, 'SFMono-Regular', Menlo, Consolas, monospace;
        font-variant-numeric: tabular-nums;
    }

    .voucher-card {
        background: var(--paper);
        border: 1px solid var(--line);
        position: relative;
    }

    .voucher-card::before {
        content: '';
        position: absolute;
        inset: 0 0 auto 0;
        height: 4px;
        background: linear-gradient(90deg, var(--accent-teal), var(--accent-blue));
    }

    .voucher-stamp {
        background: linear-gradient(135deg, var(--stamp), #1b2534);
        border: 1px solid transparent;
        background-clip: padding-box;
    }

    .voucher-stamp::before {
        content: '';
        position: absolute;
        inset: -1px;
        border-radius: inherit;
        padding: 1px;
        background: linear-gradient(135deg, var(--accent-teal), var(--accent-blue));
        -webkit-mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
        -webkit-mask-composite: xor;
        mask-composite: exclude;
    }

    .voucher-stamp-label {
        color: rgba(255, 255, 255, 0.6);
    }

    .voucher-stamp-value {
        color: #ffffff;
    }

    .voucher-total-row {
        background: linear-gradient(90deg, rgba(31, 157, 134, 0.08), rgba(63, 111, 214, 0.08));
    }

    /*
     * El cuerpo del voucher (tablas, lineas) se imprime en negro/gris para
     * ahorrar tinta, pero el encabezado (logo, nombre y sello de folio)
     * conserva sus colores originales para que se vea igual que en el modulo.
     */
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
            display: flex;
            flex-direction: column;
            --paper: #ffffff;
            --ink: #000000;
            --ink-soft: #3f3f46;
            --line: #000000;
        }

        /*
         * El card se estira al alto completo de la hoja (en vez de quedar
         * chico arriba con toda la hoja vacia abajo) y se vuelve columna
         * flex para poder anclar la firma al margen inferior con
         * margin-top:auto. La tabla en si NO crece (eso se veia feo).
         */
        .voucher-card {
            box-shadow: none !important;
            flex: 1 1 auto;
            display: flex;
            flex-direction: column;
        }

        .voucher-signatures {
            margin-top: auto !important;
        }

        /*
         * El folio, el logo y el nombre de la organizacion se imprimen
         * a color (igual que en el modulo) porque son el sello de
         * identidad del documento. La barra superior degradada, en
         * cambio, no aporta nada visualmente y se elimina.
         */
        .voucher-card::before {
            content: none;
        }

        .voucher-total-row {
            background: transparent !important;
        }

        /*
         * El total en pantalla usa text-lg/text-xl para destacar; impreso
         * eso se veia desproporcionado frente al resto de la tabla. Se
         * iguala al tamano de las filas normales y la jerarquia se logra
         * con un filete doble en vez de tamano de fuente. Sin recuadro:
         * combinado con el filete se veia saturado.
         */
        .voucher-total-amount {
            display: inline-block;
            font-size: 13px !important;
            padding-bottom: 2px;
            border-bottom: 3px double var(--ink);
        }

        /*
         * El encabezado (logo/nombre a la izquierda, folio/fecha a la
         * derecha) usa el breakpoint md: en pantalla, pero el area
         * imprimible de una hoja carta con margenes es mas angosta que
         * ese breakpoint, asi que sin esto se apila en columna, duplica
         * su altura y desborda el voucher a una segunda hoja.
         */
        .voucher-header-row {
            flex-direction: row !important;
            align-items: center !important;
            padding-bottom: 1.5rem !important;
        }

        .voucher-header-right {
            flex-direction: column !important;
            align-items: flex-end !important;
        }

        .voucher-header-title {
            text-align: left !important;
            align-items: flex-start !important;
        }

        /*
         * El sello con fondo oscuro y borde degradado no tiene sentido en
         * papel: se aplana a texto simple, alineado con el resto del
         * encabezado.
         */
        .voucher-stamp {
            background: none !important;
            border: none !important;
            box-shadow: none !important;
            padding: 0 !important;
            border-radius: 0 !important;
            text-align: right !important;
        }

        .voucher-stamp::before {
            content: none !important;
        }

        .voucher-stamp-label {
            color: var(--ink-soft) !important;
        }

        .voucher-stamp-value {
            color: var(--ink) !important;
        }

        .voucher-pay-to-box {
            padding-top: 0.75rem !important;
            padding-bottom: 0.75rem !important;
        }

        .voucher-card {
            padding: 1.5rem !important;
        }

        table thead th,
        table tbody td,
        table tfoot td {
            padding-top: 0.5rem !important;
            padding-bottom: 0.5rem !important;
        }

        #print-content .mt-6 {
            margin-top: 1rem !important;
        }

        .voucher-signatures .voucher-signature-label {
            margin-bottom: 1.5rem !important;
        }
    }

    /* El atajo de teclado solo tiene sentido con teclado fisico (no touch/movil) */
    @media (pointer: coarse) {
        .print-shortcut-hint {
            display: none;
        }
    }
</style>

<div id="print-content" class="max-w-4xl mx-auto p-4 print-wrap print:text-black print:visible">
    <div
        class="no-print sticky top-0 z-10 -mx-4 mb-6 flex flex-wrap items-center justify-between gap-3 border-b border-zinc-200 bg-white/90 px-4 py-3 backdrop-blur dark:border-zinc-700 dark:bg-zinc-900/90">
        <a href="{{ route('payouts.index') }}"
            class="text-md text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-200 transition-colors flex items-center gap-1">
            <flux:icon.arrow-left size="sm" class="mr-2" />
            {{ __('Back') }}
        </a>

        <div class="flex flex-wrap items-center gap-4">
            <div class="flex items-center gap-1 rounded-full border border-zinc-200 bg-zinc-100 p-1 dark:border-zinc-700 dark:bg-zinc-800">
                <a href="{{ route('payouts.voucher', ['batch' => $this->batch->id, 'mode' => 'summary']) }}">
                    <flux:button size="sm" variant="{{ $this->mode === 'summary' ? 'primary' : 'ghost' }}">
                        {{ __('Summary') }}
                    </flux:button>
                </a>

                <a href="{{ route('payouts.voucher', ['batch' => $this->batch->id, 'mode' => 'detailed']) }}">
                    <flux:button size="sm" variant="{{ $this->mode === 'detailed' ? 'primary' : 'ghost' }}">
                        {{ __('Detailed') }}
                    </flux:button>
                </a>
            </div>

            <div class="flex items-center gap-2 border-l border-zinc-200 pl-4 dark:border-zinc-700">
                <flux:button onclick="window.print()" variant="primary">
                    <flux:icon.printer class="size-4 mr-2" />
                    {{ __('Print') }}
                    <span class="print-shortcut-hint ms-1 text-xs opacity-70" x-data="{ mac: /Mac|iPhone|iPad|iPod/.test(navigator.userAgent) }"
                        x-text="mac ? '(⌘P)' : '(Ctrl+P)'"></span>
                </flux:button>

                @if($this->remaining_pending_count > 0 && Auth::user()->can('payouts.create'))
                    <flux:button
                        href="{{ route('payouts.create', ['instrumentist_id' => $this->batch->instrumentist_id]) }}"
                        variant="ghost">
                        {{ __('Liquidate again') }} ({{ $this->remaining_pending_count }})
                    </flux:button>
                @endif
            </div>
        </div>
    </div>

    <div class="voucher-card rounded-xl p-8 shadow-sm print:shadow-none print:rounded-none">
        <div class="voucher-header-row flex flex-col md:flex-row items-center justify-between gap-6 pb-6">
            <div class="flex items-center gap-4">
                @if($this->org_logo_url)
                    <img src="{{ $this->org_logo_url }}" alt="{{ $this->org_name }}"
                        class="voucher-logo size-14 object-contain shrink-0" />
                @endif

                <div class="voucher-header-title items-center text-center md:text-left md:items-start">
                    <h1 class="voucher-serif text-3xl font-semibold tracking-tight" style="color: var(--ink)">
                        {{ __('Payment Voucher') }}
                    </h1>
                    <h2 class="text-lg font-medium" style="color: var(--ink-soft)">
                        {{ $this->org_name }}
                    </h2>
                    <p class="text-sm no-print" style="color: var(--ink-soft)">
                       {{ __('Surgery Registry') }}
                    </p>
                </div>
            </div>

            <div class="voucher-header-right flex flex-col items-center md:items-end gap-2">
                <div class="voucher-stamp relative rounded-lg px-4 py-2 text-center md:text-right shadow-sm print:shadow-none">
                    <div class="voucher-stamp-label text-[10px] font-semibold uppercase tracking-widest">
                        {{ __('Folio') }}
                    </div>
                    <div class="voucher-stamp-value voucher-mono text-sm font-semibold">
                        {{ $this->folio }}
                    </div>
                </div>

                <div class="flex flex-col items-center md:items-end text-sm">
                    <span style="color: var(--ink-soft)">
                        {{ __('Payment Date') }}:
                    </span>
                    <span class="voucher-mono font-semibold" style="color: var(--ink)">
                        {{ optional($this->batch->paid_at)->format('Y-m-d') ?? Carbon\Carbon::parse($this->batch->paid_at)->format('Y-m-d') }}
                    </span>
                </div>

                <div class="no-print text-sm">
                    <span style="color: var(--ink-soft)">
                        {{ __('Time') }}:
                    </span>
                    <span class="voucher-mono font-semibold" style="color: var(--ink)">
                        {{ optional($this->batch->paid_at)->format('H:i') ?? Carbon\Carbon::parse($this->batch->paid_at)->format('H:i a') }}
                    </span>
                </div>
            </div>
        </div>

        <div class="voucher-pay-to-box flex flex-col gap-3 justify-between rounded-lg py-6 px-6" style="border: 1px solid var(--line)">
            <div class="flex gap-1">
                <flux:label class="min-w-3/12">
                    {{ __('Pay to') }}:
                </flux:label>
                <span class="min-w-9/12 font-medium capitalize" style="color: var(--ink)">
                    {{ $this->batch->instrumentist->name }}
                </span>
            </div>
            <div class="flex gap-1">
                <flux:label class="min-w-3/12">
                    {{ __('Amount') }} ({{ __('In letters') }}):
                </flux:label>
                <span class="voucher-serif min-w-9/12 italic" style="color: var(--ink)">
                    {{ App\Support\MoneyToWords::spell((float) $this->batch->total_amount) }}
                </span>
            </div>
            <div class="flex gap-1">
                <flux:label class="min-w-3/12">
                    {{ __('Payment Method') }}:
                </flux:label>
                <span class="min-w-9/12 font-medium capitalize pb-1 border-b" style="color: var(--ink); border-color: var(--line)">
                    {{ $this->batch->payment_method }}
                </span>
            </div>
        </div>

        @if($this->mode === 'summary')

            <!-- Tabla resumida -->
            <div class="mt-6 rounded-lg overflow-hidden" style="border: 1px solid var(--line)">
                <table class="w-full text-sm">
                    <colgroup>
                        <col style="width: 46%">
                        <col style="width: 16%">
                        <col style="width: 19%">
                        <col style="width: 19%">
                    </colgroup>
                    <thead style="border-bottom: 1px solid var(--line); color: var(--ink-soft)">
                        <tr>
                            <th class="py-4 px-6 text-left text-xs font-semibold uppercase tracking-wider">
                                {{ __('Concept') }}
                            </th>
                            <th class="py-4 px-6 text-center text-xs font-semibold uppercase tracking-wider">
                                {{ __('Quantity') }}
                            </th>
                            <th class="py-4 px-6 text-right text-xs font-semibold uppercase tracking-wider">
                                {{ __('Unit Price') }}
                            </th>
                            <th class="py-4 px-6 text-right text-xs font-semibold uppercase tracking-wider">
                                {{ __('Subtotal') }}
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr>
                            <td colspan="4"
                                class="{{ $this->usePayScheme ? 'py-3' : 'py-6' }} px-6 text-justify text-sm italic"
                                style="color: var(--ink-soft); border-bottom: 1px solid var(--line)">
                                {{ $this->voucher_legend }}
                            </td>
                        </tr>
                        @foreach($this->summaryRows as $key => $row)
                            <tr style="border-bottom: 1px solid var(--line)">
                                <td class="{{ $this->usePayScheme ? 'py-3' : 'py-6' }} px-6" style="color: var(--ink)">
                                    {{ $row['label'] }}
                                </td>
                                <td class="{{ $this->usePayScheme ? 'py-3' : 'py-6' }} px-6 text-center voucher-mono" style="color: var(--ink)">
                                    {{ $row['count'] }}
                                </td>
                                <td class="{{ $this->usePayScheme ? 'py-3' : 'py-6' }} px-6 text-right voucher-mono" style="color: var(--ink)">
                                    Q{{ number_format((float) $row['unit'], 2) }}
                                </td>
                                <td class="{{ $this->usePayScheme ? 'py-3' : 'py-6' }} px-6 text-right voucher-mono" style="color: var(--ink)">
                                    Q{{ number_format((float) $row['amount'], 2) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>

                    <tfoot>
                        <tr class="voucher-total-row">
                            <td colspan="2" class="{{ $this->usePayScheme ? 'py-3' : 'py-4' }} px-6"></td>
                            <td colspan="1"
                                class="{{ $this->usePayScheme ? 'py-3' : 'py-4' }} px-6 text-center font-semibold uppercase tracking-wide text-xs"
                                style="color: var(--ink)">
                                {{ __('Total') }}
                            </td>
                            <td colspan="1"
                                class="{{ $this->usePayScheme ? 'py-3' : 'py-4' }} px-6 text-right voucher-mono font-bold text-lg"
                                style="color: var(--ink)">
                                <span class="voucher-total-amount">Q{{ number_format((float) $this->batch->total_amount, 2) }}</span>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @else

            <!-- Tabla detallada -->
            <div class="mt-6 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead style="border-bottom: 1px solid var(--line); color: var(--ink-soft)">
                        <tr>
                            <th class="py-3 pr-3 text-left text-xs font-semibold uppercase tracking-wider">
                                {{ __('Date') }}
                            </th>
                            <th class="py-3 pr-3 font-semibold uppercase tracking-wider text-xs no-print text-center">
                                {{ __('Duration') }}
                            </th>
                            <th class="py-3 pr-3 text-center text-xs font-semibold uppercase tracking-wider">
                                {{ __('Patient') }}
                            </th>
                            <th class="py-3 pr-3 text-center text-xs font-semibold uppercase tracking-wider">
                                {{ __('Surgery') }}
                            </th>
                            <th class="py-3 text-right text-xs font-semibold uppercase tracking-wider">
                                {{ __('Subtotal') }}
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach($this->items as $it)
                            @php
                                $p = $it->procedure;
                            @endphp
                            <tr style="border-bottom: 1px solid var(--line)">
                                <td class="py-3 pr-3" style="color: var(--ink-soft)">
                                    {{ $p->procedure_date->format('d/m/Y') ?? '-' }}
                                </td>
                                <td class="py-3 pr-3 no-print text-center voucher-mono" style="color: var(--ink-soft)">
                                    {{ $p->duration_minutes ?? '-' }} min
                                </td>
                                <td class="py-3 pr-3 font-medium text-center" style="color: var(--ink)">
                                    {{ $p->patient_name ?? '-' }}
                                </td>
                                <td class="py-3 pr-3" style="color: var(--ink-soft)">
                                    {{ $p->procedure_type ?? '-' }}
                                    @if(($p->is_videosurgery ?? false) === true)
                                        <span
                                            class="ml-2 inline-flex items-center rounded-full bg-zinc-100 dark:bg-zinc-800 px-2 py-0.5 text-xs font-medium text-zinc-800 dark:text-zinc-200 no-print">
                                            {{ __('Video') }}
                                        </span>
                                    @endif
                                </td>
                                <td class="py-3 text-right voucher-mono font-medium" style="color: var(--ink)">
                                    Q{{ number_format((float) $it->amount, 2) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>

                    <tfoot>
                        <tr>
                            <td colspan="4" class="print:hidden"></td>
                            <td colspan="3" class="print:table-cell hidden"></td>
                            <td colspan="1" class="pt-8 print:table-cell" style="border-bottom: 1px solid var(--line)">
                            </td>
                        </tr>
                        <tr>
                            <td colspan="5" class="pt-4 text-right font-semibold uppercase tracking-wide text-xs no-print" style="color: var(--ink-soft)">
                                {{ __('Total') }}
                            </td>
                            <td colspan="4"
                                class="pt-4 text-right font-semibold uppercase tracking-wide text-xs hidden print:table-cell" style="color: var(--ink-soft)">
                                {{ __('Total') }}
                            </td>
                        </tr>
                        <tr>
                            <td colspan="5" class="text-right font-bold text-xl" style="color: var(--ink)">
                                <span class="voucher-total-amount voucher-mono">Q{{ number_format((float) $this->batch->total_amount, 2) }}</span>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

        @endif

        <!-- Footer signature -->
        <div
            class="voucher-signatures grid grid-cols-3 gap-12 text-center items-center text-xs {{ $this->usePayScheme ? 'mt-12' : 'mt-16' }}">
            <div>
                <div class="voucher-signature-label mb-12" style="color: var(--ink-soft)">
                    {{ __('Received by') }}
                </div>
                <div class="pt-2" style="border-top: 1px solid var(--line); color: var(--ink)">
                    {{ $this->batch->instrumentist->name ?? '' }}
                </div>
            </div>

            <div>
                <div class="voucher-signature-label mb-12" style="color: var(--ink-soft)">
                    {{ __('Paid by') }} ({{ __('Administration') }})
                </div>
                <div class="pt-2" style="border-top: 1px solid var(--line); color: var(--ink)">
                    {{ $this->batch->paidByUser->name ?? '' }}
                </div>
            </div>

            <div>
                <div class="voucher-signature-label mb-12" style="color: var(--ink-soft)">
                    {{ __('Authorized Signature') }}
                </div>
                <div class="pt-2" style="border-top: 1px solid var(--line); color: var(--ink)">
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