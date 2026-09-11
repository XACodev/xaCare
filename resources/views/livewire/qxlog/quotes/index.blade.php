<?php
// resources/views/livewire/qxlog/quotes/index.blade.php

use App\Modules\QxLog\Models\SurgeryQuote;
use Illuminate\Support\Facades\Auth;

use function Livewire\Volt\{state, mount, computed};

state(['canViewTotal' => false, 'canManage' => false]);

mount(function () {
    $user = Auth::user();
    $canTotal = (bool) $user?->can('surgeries.budget.view_total');
    $canOwn = (bool) $user?->can('surgeries.budget.view_own');
    $canManage = (bool) $user?->can('surgeries.budget.manage');
    abort_unless($canTotal || $canOwn || $canManage, 403);

    $this->canViewTotal = $canTotal;
    $this->canManage = $canManage;
});

$quotes = computed(function () {
    $user = Auth::user();

    $query = SurgeryQuote::query()->with(['patient', 'surgicalCase'])->latest();

    if (! $this->canViewTotal) {
        $query->whereHas('surgicalCase.assignments', fn ($q) => $q->where('user_id', $user->id));
    }

    return $query->get();
});
?>

<div class="p-6 space-y-4">
    <div class="flex items-center justify-between">
        <flux:heading size="lg">{{ __('Cotizaciones') }}</flux:heading>
        @if($canManage)
            <flux:button variant="primary" :href="route('quotes.create')" wire:navigate>
                {{ __('Nueva cotización') }}
            </flux:button>
        @endif
    </div>

    <div class="overflow-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
        <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700 text-zinc-500 dark:text-zinc-400">
            <thead class="bg-zinc-50 dark:bg-zinc-800">
                <tr>
                    <th class="px-4 py-3 font-medium uppercase tracking-wider text-left">
                        <flux:label>{{ __('Paciente') }}</flux:label>
                    </th>
                    <th class="px-4 py-3 font-medium uppercase tracking-wider text-left">
                        <flux:label>{{ __('Estado') }}</flux:label>
                    </th>
                    <th class="px-4 py-3 font-medium uppercase tracking-wider text-right">
                        <flux:label>{{ $canViewTotal ? __('Total') : __('Mi honorario') }}</flux:label>
                    </th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-zinc-900 divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse($this->quotes as $quote)
                    <tr wire:key="{{ $quote->id }}" class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors">
                        <td class="px-4 py-3 text-sm font-medium text-zinc-900 dark:text-zinc-100">
                            <a href="{{ route('quotes.show', $quote) }}" wire:navigate class="underline">
                                {{ $quote->patient->nombreCompleto() }}
                            </a>
                        </td>
                        <td class="px-4 py-3 text-sm">{{ __(ucfirst($quote->status)) }}</td>
                        <td class="px-4 py-3 text-sm font-bold text-right">
                            Q{{ number_format((float) ($canViewTotal ? $quote->total : $quote->staff_fee), 2) }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-6 py-8 text-center text-sm italic">
                            {{ __('No hay cotizaciones para mostrar') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
