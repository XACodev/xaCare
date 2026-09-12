<?php
// resources/views/livewire/qxlog/quotes/verify.blade.php

use App\Modules\QxLog\Models\SurgeryQuote;
use Illuminate\Support\Facades\Auth;

use function Livewire\Volt\{state, mount};

state(['summary' => []]);

mount(function (SurgeryQuote $quote) {
    abort_unless($quote->hospital_id === Auth::user()?->hospital_id, 404);

    $this->summary = $quote->verificationSummary();
});
?>

<div class="p-6 max-w-md mx-auto text-center space-y-4">
    <flux:heading size="lg">{{ __('Verificación de cotización') }}</flux:heading>
    <div class="text-sm text-zinc-500">{{ __('Folio') }}: <span class="font-mono">{{ $summary['folio'] }}</span></div>
    <flux:badge>{{ __(ucfirst($summary['status'])) }}</flux:badge>
    <div class="text-sm">{{ __('Generada por') }}: {{ $summary['generated_by'] }}</div>
    <div class="text-sm text-zinc-500">{{ $summary['generated_at'] }}</div>
</div>
