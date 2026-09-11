<?php
// resources/views/livewire/qxlog/quotes/show.blade.php

use App\Modules\QxLog\Models\SurgeryQuote;
use Illuminate\Support\Facades\Auth;

use function Livewire\Volt\{state, mount};

state(['quote' => null, 'canViewTotal' => false, 'canManage' => false, 'canScheduleFromQuote' => false]);

mount(function (SurgeryQuote $quote) {
    $user = Auth::user();
    abort_unless($quote->hospital_id === $user->hospital_id, 403);

    $canTotal = (bool) $user->can('surgeries.budget.view_total');
    $canOwn = (bool) $user->can('surgeries.budget.view_own');
    $canManage = (bool) $user->can('surgeries.budget.manage');
    $assigned = $quote->surgical_case_id
        && $quote->surgicalCase->assignments()->where('user_id', $user->id)->exists();

    abort_unless($canTotal || ($canOwn && $assigned) || $canManage, 403);

    $this->quote = $quote;
    $this->canViewTotal = $canTotal;
    $this->canManage = $canManage;
    $this->canScheduleFromQuote = $this->canManage && (bool) $user->can('surgeries.schedule');
});

$issue = function () {
    $user = Auth::user();
    abort_unless($user->can('surgeries.budget.manage'), 403);
    abort_unless($this->quote->status === 'draft', 422, 'Solo una cotización en borrador puede emitirse.');

    $this->quote->markIssued();
    $this->quote->refresh();
};
?>

<div class="p-6 max-w-2xl space-y-4">
    <div class="flex items-center justify-between">
        <flux:heading size="lg">{{ $quote->patient->nombreCompleto() }}</flux:heading>
        <flux:badge>{{ __(ucfirst($quote->status)) }} · v{{ $quote->version }}</flux:badge>
    </div>

    <div class="space-y-2">
        <div>{{ __('Honorario del staff') }}: Q{{ number_format((float) $quote->staff_fee, 2) }}</div>
        @if($canViewTotal)
            <div>{{ __('Costo de hospital') }}: Q{{ number_format((float) $quote->hospital_cost, 2) }}</div>
            <div class="font-bold">{{ __('Total') }}: Q{{ number_format((float) $quote->total, 2) }}</div>
        @endif
        @if($quote->hospital_cost_note)
            <p class="text-sm text-zinc-500">{{ $quote->hospital_cost_note }}</p>
        @endif
    </div>

    <div class="flex gap-2">
        @if($canManage && $quote->status === 'draft')
            <flux:button wire:click="issue">{{ __('Marcar como emitida') }}</flux:button>
            <flux:button :href="route('quotes.edit', $quote)" wire:navigate>{{ __('Editar') }}</flux:button>
        @endif
        @if($canManage && $quote->status === 'issued')
            <flux:button :href="route('quotes.edit', $quote)" wire:navigate>
                {{ __('Nueva versión') }}
            </flux:button>
        @endif
        @if($canScheduleFromQuote && !$quote->surgical_case_id)
            <flux:button variant="primary"
                :href="route('surgeries.schedule.create', ['patient_id' => $quote->patient_id, 'quote_id' => $quote->id])"
                wire:navigate>
                {{ __('Agendar cirugía') }}
            </flux:button>
        @endif
        @if($canViewTotal)
            <flux:button :href="route('quotes.print', $quote)" wire:navigate>{{ __('Imprimir') }}</flux:button>
        @endif
    </div>
</div>
