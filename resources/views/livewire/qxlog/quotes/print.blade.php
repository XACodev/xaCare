<?php
// resources/views/livewire/qxlog/quotes/print.blade.php

use App\Models\OrganizationSetting;
use App\Modules\QxLog\Models\SurgeryQuote;
use App\Support\QrCodeSvg;
use Illuminate\Support\Facades\Auth;

use function Livewire\Volt\{state, mount};

state(['quote' => null, 'org' => null, 'view' => 'internal', 'format' => 'letter', 'qrSvg' => '']);

mount(function (SurgeryQuote $quote) {
    $user = Auth::user();
    abort_unless($quote->hospital_id === $user->hospital_id, 403);
    abort_unless($user->can('surgeries.budget.view_total'), 403);

    $this->quote = $quote;
    $this->org = OrganizationSetting::forHospital($quote->hospital_id);
    $this->qrSvg = QrCodeSvg::inline(route('quotes.verify', $quote), 120);
});
?>

<div class="p-6">
    <div class="no-print mb-4 flex gap-2 items-center">
        <flux:button onclick="window.print()">{{ __('Imprimir') }}</flux:button>
        <flux:button onclick="window.xacareCopyPrintImage('print-content')">{{ __('Copiar imagen') }}</flux:button>
        <select wire:model.live="view" class="border rounded px-2 py-1 text-sm">
            <option value="internal">{{ __('Copia interna') }}</option>
            <option value="patient">{{ __('Copia paciente') }}</option>
        </select>
        <select wire:model.live="format" class="border rounded px-2 py-1 text-sm">
            <option value="letter">{{ __('Carta') }}</option>
            <option value="thermal-80">{{ __('Térmica 80mm') }}</option>
            <option value="thermal-58">{{ __('Térmica 58mm') }}</option>
        </select>
    </div>

    <style>
        @media print {
            .no-print { display: none !important; }
            body * { visibility: hidden !important; }
            #print-content, #print-content * { visibility: visible !important; }
            #print-content { position: absolute; left: 0; top: 0; width: 100%; }
        }
        .format-letter { max-width: 640px; margin: 0 auto; }
        .format-thermal-80, .format-thermal-58 { font-family: ui-monospace, monospace; margin: 0 auto; }
        .format-thermal-80 { max-width: 80mm; }
        .format-thermal-58 { max-width: 58mm; font-size: 11px; }
    </style>

    <div id="print-content" class="format-{{ $format }} space-y-3">
        @if($format === 'letter')
            <div class="flex justify-between items-start border-b-2 border-teal-700 pb-3 mb-3">
                <div class="flex items-center gap-3">
                    @if($org->logoUrl())
                        <img src="{{ $org->logoUrl() }}" alt="" class="w-10 h-10 rounded object-cover" />
                    @endif
                    <div>
                        <div class="font-bold">{{ $org->org_name }}</div>
                        <div class="text-xs text-zinc-500">{{ __('Cotización de procedimiento quirúrgico') }}</div>
                    </div>
                </div>
                <div class="text-xs text-right text-zinc-500">
                    @if($org->website)<div>{{ $org->website }}</div>@endif
                    @if($org->phone)<div>{{ $org->phone }}</div>@endif
                </div>
            </div>
        @else
            <div class="text-center font-bold">{{ $org->org_name }}</div>
            <div class="text-center">- - - - - - - - - -</div>
        @endif

        <div class="{{ $format === 'letter' ? 'text-2xl font-bold' : 'font-bold' }}">{{ $quote->patient->nombreCompleto() }}</div>
        @if($quote->procedureType)
            <div>{{ $quote->procedureType->name }}</div>
        @endif
        @if(!empty($quote->specialties))
            <div class="flex flex-wrap gap-1">
                @foreach($quote->specialties as $specialty)
                    <flux:badge size="sm">{{ $specialty }}</flux:badge>
                @endforeach
            </div>
        @endif

        @if($view === 'internal')
            <table class="w-full text-sm">
                <thead><tr class="text-left text-xs uppercase text-zinc-500"><th>{{ __('Concepto') }}</th><th class="text-right">{{ __('Monto') }}</th></tr></thead>
                <tbody>
                    @foreach($quote->lineItems as $item)
                        <tr><td>{{ $item->label }}</td><td class="text-right">Q{{ number_format((float) $item->amount, 2) }}</td></tr>
                    @endforeach
                    <tr><td>{{ __('Costo de hospital') }}</td><td class="text-right">Q{{ number_format((float) $quote->hospital_cost, 2) }}</td></tr>
                </tbody>
            </table>
        @endif

        <div class="{{ $format === 'letter' ? 'text-xl' : '' }} font-bold text-right">{{ __('Total') }}: Q{{ number_format((float) $quote->total, 2) }}</div>

        @if($quote->hospital_cost_note)
            <div class="text-sm bg-zinc-100 dark:bg-zinc-800 rounded p-2"><b class="block text-xs uppercase text-zinc-500">{{ __('Notas') }}</b>{{ $quote->hospital_cost_note }}</div>
        @endif

        @if($view === 'internal' && $quote->internal_note)
            <div class="text-sm bg-amber-100 dark:bg-amber-900/40 border border-amber-300 dark:border-amber-700 rounded p-2">
                <b class="block text-xs uppercase text-amber-700 dark:text-amber-400">{{ __('Nota interna') }}</b>{{ $quote->internal_note }}
            </div>
        @endif

        <div class="flex justify-between items-center border-t border-dashed pt-3 mt-3">
            <div class="w-24 h-24">{!! $qrSvg !!}</div>
            <div class="text-xs text-right">
                <div class="font-bold">{{ __('Verificación') }}</div>
                <div>{{ __('Sin lector, use el folio') }}:</div>
                <div class="font-mono">{{ $quote->slug }}</div>
            </div>
        </div>
    </div>
</div>
