<?php

use App\Modules\QxLog\Models\SurgicalCase;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Flux\Flux;

use function Livewire\Volt\{state, computed, mount};

state([
    'q' => '',
    'status' => 'pending', // pending|paid|all
    'instrumentist_id' => '',
    'date_from' => '',
    'date_to' => '',
    'procedure_to_delete' => null,
]);

mount(function () {
    abort_unless(Auth::check(), 401);

    abort_unless((bool) (Auth::user()->hasRole('admin') || Auth::user()->is_platform_admin), 403);
});

$instrumentists = computed(function () {
    return User::query()
        ->where('role', 'instrumentist')
        ->when(Auth::user()?->hospital_id, fn ($q) => $q->where('hospital_id', Auth::user()->hospital_id))
        ->orderBy('name')
        ->get(['id', 'name']);
});

$procedures = computed(function () {
    $query = SurgicalCase::query()
        ->with(['assignments.surgicalRole', 'assignments.user', 'procedureType'])
        ->orderByDesc('procedure_date')
        ->orderByDesc('id');

    if ($this->status !== 'all') {
        $query->where('status', $this->status);
    }

    if ($this->instrumentist_id) {
        // Las columnas legacy de instrumentista/doctor/circulante fueron retiradas de
        // surgical_cases (ver migración de drop): todo caso quirúrgico existente ya tiene sus
        // participantes representados como SurgicalAssignment, así que el filtro solo necesita
        // esa ruta.
        $instrumentistId = (int) $this->instrumentist_id;
        $query->whereHas('assignments', function ($a) use ($instrumentistId) {
            $a->where('user_id', $instrumentistId)
                ->whereHas('surgicalRole', fn($r) => $r->where('name', 'Instrumentista'));
        });
    }

    if ($this->date_from) {
        $query->whereDate('procedure_date', '>=', $this->date_from);
    }

    if ($this->date_to) {
        $query->whereDate('procedure_date', '<=', $this->date_to);
    }

    if ($this->q) {
        $term = trim($this->q);
        $query->where(function ($s) use ($term) {
            $s->where('patient_name', 'like', "%{$term}%")
                ->orWhereHas('procedureType', function ($pt) use ($term) {
                    $pt->where('name', 'like', "%{$term}%");
                });
        });
    }

    return $query->limit(300)->get();
});

$total = computed(function () {
    return $this->procedures->sum(fn($p) => (float) $p->assignments->sum('calculated_amount'));
});

$ruleLabel = function (?string $rule) {
    return match ($rule) {
        'video_rate' => __('Video'),
        'night_rate' => __('Night'),
        'long_case_rate' => __('Long'),
        default => __('Base Rate'),
    };
};

$ruleColor = function (?string $rule) {
    return match ($rule) {
        'video_rate' => 'accent',
        'night_rate' => 'rose',
        'long_case_rate' => 'amber',
        default => 'zinc',
    };
};

$confirmDelete = function (int $id) {
    $this->procedure_to_delete = $id;
    Flux::modal('confirm-procedure-deletion')->show();
};

$delete = function () {
    abort_if((bool) Auth::user()?->is_platform_admin, 403, 'Administrador de plataforma es de solo lectura; usa una cuenta de hospital para operar.');

    if ($this->procedure_to_delete) {
        SurgicalCase::findOrFail($this->procedure_to_delete)->delete();
        $this->procedure_to_delete = null;
        Flux::modal('confirm-procedure-deletion')->close();
    }
};

?>

<div class="max-w-6xl mx-auto p-4 space-y-4">
    <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">
                {{ __('Procedures') }}
            </flux:heading>
            <div class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">
                {{ $this->procedures->count() }} {{ __('max 300') }} ·
                <span class="font-semibold text-zinc-700 dark:text-zinc-300">Q{{ number_format($this->total, 2) }}</span>
            </div>
        </div>
        <flux:input icon="magnifying-glass" wire:model.live="q" class="sm:w-72"
            placeholder="{{ __('Patient, type, doctor, circulating...') }}" />
    </div>

    <div class="space-y-3">
        <div class="flex flex-wrap gap-2 items-center">
            @foreach (['pending' => __('Pending'), 'paid' => __('Paid'), 'all' => __('All')] as $value => $label)
                <button type="button" wire:click="$set('status', '{{ $value }}')"
                    class="h-9 px-3.5 rounded-lg text-sm border {{ $status === $value ? 'border-accent bg-mist text-accent-content dark:bg-accent/20 dark:text-accent font-semibold' : 'border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-zinc-600 dark:text-zinc-400' }}">
                    {{ $label }}
                </button>
            @endforeach

            <span class="flex-1"></span>

            <select wire:model.change="instrumentist_id"
                class="h-9 rounded-lg border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-zinc-600 dark:text-zinc-400 text-sm focus:ring-1 focus:ring-accent focus:border-accent">
                <option value="">-- {{ __('All') }} --</option>
                @foreach($this->instrumentists as $i)
                    <option value="{{ $i->id }}">{{ $i->name }}</option>
                @endforeach
            </select>

            <input type="date" wire:model.change="date_from"
                class="h-9 rounded-lg border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-zinc-600 dark:text-zinc-400 text-sm focus:ring-1 focus:ring-accent focus:border-accent">
            <input type="date" wire:model.change="date_to"
                class="h-9 rounded-lg border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-zinc-600 dark:text-zinc-400 text-sm focus:ring-1 focus:ring-accent focus:border-accent">
        </div>

        <!-- Mobile View (Cards) -->
        <div class="grid grid-cols-1 gap-4 sm:hidden">
            @forelse($this->procedures as $p)
                @php
                    $rule = data_get($p->pricing_snapshot, 'rule', 'default_rate');
                @endphp
                <div
                    class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-600 bg-white dark:bg-zinc-900 shadow-sm space-y-3">
                    <div class="flex justify-between items-start">
                        <div>
                            <div class="font-medium text-zinc-900 dark:text-zinc-100 text-lg capitalize">
                                {{ strtolower($p->patient_name) }}
                            </div>
                            <div class="text-xs text-zinc-400 font-mono">
                                {{ $p->procedureType?->name }}
                            </div>
                            <div class="text-xs text-zinc-500 dark:text-zinc-500">
                                {{ $p->procedure_date?->format('d/m/Y') }}
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <flux:badge size="sm" :variant="$p->status === 'paid' ? 'success' : 'warning'">
                                {{ __($p->status) }}
                            </flux:badge>

                            <flux:dropdown>
                                <flux:button size="sm" icon="ellipsis-vertical" />
                                <flux:menu>
                                    <flux:menu.item href="{{ route('procedures.edit', $p) }}" icon="pencil">
                                        {{ __('Edit') }}
                                    </flux:menu.item>
                                    <flux:menu.separator />
                                    <flux:menu.item icon="trash" variant="danger" wire:click="confirmDelete({{ $p->id }})">
                                        {{ __('Delete') }}
                                    </flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </div>
                    </div>

                    <div class="space-y-1 text-sm text-zinc-500 dark:text-zinc-400">
                        <div class="flex justify-between">
                            <span class="font-medium">
                                {{ __('Assignments') }}:
                            </span>
                            <span class="font-medium text-right">
                                {{ $p->assignments->map(fn($a) => $a->surgicalRole->name . ': ' . ($a->user->name ?? '—'))->implode(', ') }}
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="font-medium">
                                {{ __('Start') }} - {{ __('End') }}:
                            </span>
                            <span class="font-medium">
                                {{ \Carbon\Carbon::parse($p->start_time)->format('H:i') }}
                                <span>-</span>
                                {{ \Carbon\Carbon::parse($p->end_time)->format('H:i') }}
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="font-medium">
                                {{ __('Duration') }}:
                            </span>
                            <span class="font-medium">
                                {{ $p->duration_minutes ?? '-' }}
                                <span>
                                    {{ __('min') }}
                                </span>
                            </span>
                        </div>
                    </div>

                    <div class="pt-3 border-t border-zinc-200 dark:border-zinc-700 flex justify-between items-center">
                        <x-procedure-rule-badge :rule="$rule" :videosurgery="$p->is_videosurgery" />
                        <span class="font-bold">
                            Q{{ number_format($p->assignments->sum('calculated_amount'), 2) }}
                        </span>
                    </div>


                </div>
            @empty
                <div
                    class="p-8 text-center text-zinc-500 dark:text-zinc-400 italic bg-zinc-50 dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800">
                    {{ __('No results found') }}
                </div>
            @endforelse
        </div>

        <!-- Desktop View (Table) -->
        <div class="hidden sm:block rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="qx-table-head">
                            <th scope="col" class="px-4 py-3 text-left font-semibold tracking-wider">{{ __('Date') }}</th>
                            <th scope="col" class="px-4 py-3 text-left font-semibold tracking-wider">{{ __('Procedure') }}</th>
                            <th scope="col" class="px-4 py-3 text-left font-semibold tracking-wider">{{ __('Patient') }}</th>
                            <th scope="col" class="px-4 py-3 text-left font-semibold tracking-wider">{{ __('Assignments') }}</th>
                            <th scope="col" class="px-4 py-3 text-left font-semibold tracking-wider">{{ __('Duration') }}</th>
                            <th scope="col" class="px-4 py-3 text-right font-semibold tracking-wider">{{ __('Amount') }}</th>
                            <th scope="col" class="px-4 py-3 text-left font-semibold tracking-wider">{{ __('State') }}</th>
                            @if ($this->status !== 'paid')
                                <th scope="col" class="px-4 py-3 text-right font-semibold tracking-wider">{{ __('Actions') }}</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($this->procedures as $p)
                            @php
                                $rule = data_get($p->pricing_snapshot, 'rule', 'default_rate');
                            @endphp
                            <tr wire:key="proc-{{ $p->id }}" class="qx-table-row">
                                <td class="px-4 py-3 text-zinc-700 dark:text-zinc-300 tabular-nums whitespace-nowrap">
                                    {{ $p->procedure_date->format('d/m/Y') }}
                                </td>
                                <td class="px-4 py-3 max-w-50">
                                    <div class="font-semibold truncate" title="{{ $p->procedureType?->name }}">
                                        {{ $p->procedureType?->name }}
                                    </div>
                                    <div>
                                        <x-procedure-rule-badge :rule="$rule" :videosurgery="$p->is_videosurgery" />
                                    </div>
                                </td>
                                <td class="px-4 py-3 max-w-3xs truncate capitalize font-medium">
                                    {{ strtolower($p->patient_name) }}
                                </td>
                                <td class="px-4 py-3 truncate max-w-50 text-zinc-700 dark:text-zinc-300"
                                    title="{{ $p->assignments->map(fn($a) => $a->surgicalRole->name . ': ' . ($a->user->name ?? '—'))->implode(', ') }}">
                                    {{ $p->assignments->map(fn($a) => $a->surgicalRole->name . ': ' . ($a->user->name ?? '—'))->implode(', ') }}
                                </td>
                                <td class="px-4 py-3 text-zinc-700 dark:text-zinc-300 tabular-nums whitespace-nowrap">
                                    {{ $p->duration_minutes }} {{ __('min') }}
                                    <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                        {{ \Carbon\Carbon::parse($p->start_time)->format('H:i') }} - {{ \Carbon\Carbon::parse($p->end_time)->format('H:i') }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-right font-semibold tabular-nums">
                                    Q{{ number_format($p->assignments->sum('calculated_amount'), 2) }}
                                </td>
                                <td class="px-4 py-3">
                                    <flux:badge size="sm" :variant="$p->status === 'paid' ? 'success' : 'warning'">
                                        {{ __($p->status) }}
                                    </flux:badge>
                                </td>
                                @if ($p->status === 'paid' && $this->status === 'all')
                                    <td></td>
                                @endif
                                @if ($p->status === 'pending' && ($this->status === 'all' || $this->status === 'pending'))
                                    <td class="px-4 py-3 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <a href="{{ route('procedures.edit', $p) }}"
                                                class="inline-flex items-center gap-1.5 text-sm text-accent-content dark:text-accent hover:text-accent-content/80 dark:hover:text-accent/80 transition-colors">
                                                <flux:icon name="pencil" size="sm" />
                                            </a>
                                            <button type="button" wire:click="confirmDelete({{ $p->id }})"
                                                class="inline-flex items-center gap-1.5 text-sm text-red-500 dark:text-red-500 hover:text-red-900 dark:hover:text-red-900 transition-colors cursor-pointer">
                                                <flux:icon name="trash" size="sm" />
                                            </button>
                                        </div>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-6 py-8 text-center text-sm text-zinc-500 dark:text-zinc-400 italic">
                                    {{ __('No results found') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <flux:modal name="confirm-procedure-deletion" class="max-w-lg">
        <form wire:submit="delete" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Delete Procedure') }}</flux:heading>
                <flux:text>
                    {{ __('Are you sure you want to delete this procedure?') }}
                </flux:text>
                <br>
                <flux:text>
                    {{ __('This action cannot be undone, although it will be kept in the database history.') }}
                </flux:text>
            </div>

            <div class="flex justify-end gap-2">
                <flux:spacer />

                <flux:modal.close>
                    <flux:button wire:loading.attr="disabled" class="cursor-pointer" variant="filled">{{ __('Cancel') }}
                    </flux:button>
                </flux:modal.close>

                <flux:button wire:loading.attr="disabled" class="cursor-pointer" variant="danger" type="submit">
                    {{ __('Delete') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>