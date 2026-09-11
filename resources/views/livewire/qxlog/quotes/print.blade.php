<?php
// resources/views/livewire/qxlog/quotes/print.blade.php

use App\Models\OrganizationSetting;
use App\Modules\QxLog\Models\SurgeryQuote;
use Illuminate\Support\Facades\Auth;

use function Livewire\Volt\{state, mount};

state(['quote' => null, 'org_name' => null]);

mount(function (SurgeryQuote $quote) {
    $user = Auth::user();
    abort_unless($quote->hospital_id === $user->hospital_id, 403);
    abort_unless($user->can('surgeries.budget.view_total'), 403);

    $this->quote = $quote;
    $this->org_name = OrganizationSetting::forHospital($quote->hospital_id)->org_name;
});
?>

<div class="p-6">
    <style>
        @media print {
            .no-print { display: none !important; }
            body * { visibility: hidden !important; }
            #print-content, #print-content * { visibility: visible !important; }
            #print-content { position: absolute; left: 0; top: 0; width: 100%; }
        }
    </style>

    <div class="no-print mb-4">
        <flux:button onclick="window.print()">{{ __('Imprimir') }}</flux:button>
    </div>

    <div id="print-content" class="max-w-xl mx-auto space-y-4">
        <h1 class="text-xl font-bold">{{ $org_name }}</h1>
        <p>{{ __('Cotización para') }}: {{ $quote->patient->nombreCompleto() }}</p>
        <p>{{ __('Fecha') }}: {{ $quote->created_at->format('d/m/Y') }}</p>

        <div class="text-2xl font-bold">
            {{ __('Total') }}: Q{{ number_format((float) $quote->total, 2) }}
        </div>

        @if($quote->hospital_cost_note)
            <p>{{ $quote->hospital_cost_note }}</p>
        @endif
    </div>
</div>
