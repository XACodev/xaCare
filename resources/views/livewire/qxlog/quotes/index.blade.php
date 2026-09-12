<?php
// resources/views/livewire/qxlog/quotes/index.blade.php

use App\Modules\QxLog\Models\SurgeryQuote;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

use function Livewire\Volt\{state, mount, computed};

state(['canViewTotal' => false, 'canManage' => false, 'search' => '']);

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

    $query = SurgeryQuote::query()->with(['patient', 'surgicalCase', 'procedureType'])->latest();

    if (! $this->canViewTotal) {
        $query->whereHas('surgicalCase.assignments', fn ($q) => $q->where('user_id', $user->id));
    }

    $results = $query->get();

    $needle = Str::ascii(Str::lower(trim($this->search)));
    if ($needle === '') {
        return $results;
    }

    return $results->filter(function ($quote) use ($needle) {
        $haystacks = [
            $quote->patient?->nombreCompleto() ?? '',
            $quote->slug,
            $quote->procedureType?->name ?? '',
        ];

        foreach ($haystacks as $haystack) {
            if (str_contains(Str::ascii(Str::lower($haystack)), $needle)) {
                return true;
            }
        }

        return false;
    })->values();
});

$deleteQuote = function (int $id) {
    $user = Auth::user();
    abort_unless($user && $user->can('surgeries.budget.manage'), 403);

    $quote = SurgeryQuote::where('id', $id)->firstOrFail();
    $quote->delete();
};
?>

<div class="p-6 space-y-4">
    <div class="flex items-center justify-between gap-4">
        <flux:heading size="lg">{{ __('Cotizaciones') }}</flux:heading>
        <flux:input wire:model.live.debounce.300ms="search" placeholder="{{ __('Buscar por paciente, folio o procedimiento') }}" class="max-w-xs" />
        <div class="flex items-center gap-2">
            <x-qr-scanner-button />
            @if($canManage)
                <flux:button variant="primary" :href="route('quotes.create')" wire:navigate>
                    {{ __('Nueva cotización') }}
                </flux:button>
            @endif
        </div>
    </div>

    <div class="overflow-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
        <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700 text-zinc-500 dark:text-zinc-400">
            <thead class="bg-zinc-50 dark:bg-zinc-800">
                <tr>
                    <th class="px-4 py-3 font-medium uppercase tracking-wider text-left"><flux:label>{{ __('Paciente') }}</flux:label></th>
                    <th class="px-4 py-3 font-medium uppercase tracking-wider text-left"><flux:label>{{ __('Folio') }}</flux:label></th>
                    <th class="px-4 py-3 font-medium uppercase tracking-wider text-left"><flux:label>{{ __('Estado') }}</flux:label></th>
                    <th class="px-4 py-3 font-medium uppercase tracking-wider text-left"><flux:label>{{ __('Generada') }}</flux:label></th>
                    <th class="px-4 py-3 font-medium uppercase tracking-wider text-right"><flux:label>{{ $canViewTotal ? __('Total') : __('Mi honorario') }}</flux:label></th>
                    <th></th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-zinc-900 divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse($this->quotes as $quote)
                    @php($isStale = $quote->created_at->diffInDays(now()) >= 30)
                    <tr wire:key="{{ $quote->id }}" @if($isStale) data-stale="{{ $quote->id }}" @endif class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors">
                        <td class="px-4 py-3 text-sm font-medium text-zinc-900 dark:text-zinc-100">
                            <a href="{{ route('quotes.show', $quote) }}" wire:navigate class="underline">{{ $quote->patient?->nombreCompleto() }}</a>
                        </td>
                        <td class="px-4 py-3 text-xs font-mono">{{ $quote->slug }}</td>
                        <td class="px-4 py-3 text-sm">{{ __(ucfirst($quote->status)) }}</td>
                        <td class="px-4 py-3 text-xs">
                            {{ __('hace :n días', ['n' => $quote->created_at->diffInDays(now())]) }}
                            @if($isStale)
                                <flux:badge color="amber">{{ __('30+ días') }}</flux:badge>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm font-bold text-right">
                            Q{{ number_format((float) ($canViewTotal ? $quote->total : $quote->staff_fee), 2) }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            @if($canManage)
                                <flux:button size="sm" variant="danger" wire:click="deleteQuote({{ $quote->id }})" wire:confirm="{{ __('¿Eliminar esta cotización?') }}">
                                    {{ __('Eliminar') }}
                                </flux:button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-6 py-8 text-center text-sm italic">{{ __('No hay cotizaciones para mostrar') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
