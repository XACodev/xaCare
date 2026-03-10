<?php

use App\Models\Procedure;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

use function Livewire\Volt\{state, computed, mount};

state([
    'q' => '',
    'status' => 'pending', // pending|paid|all
    'instrumentist_id' => '',
    'date_from' => '',
    'date_to' => '',
]);

mount(function () {
    abort_unless(Auth::check(), 401);

    abort_unless((bool) (Auth::user()->role !== 'admin' || Auth::user()->is_super_admin), 403);
});

$instrumentists = computed(function () {
    return User::query()
        ->where('role', 'instrumentist')
        ->orderBy('name')
        ->get(['id', 'name']);
});

$procedures = computed(function () {
    $query = Procedure::query()
        ->with(['instrumentist:id,name'])
        ->orderByDesc('procedure_date')
        ->orderByDesc('id');

    if ($this->status !== 'all') {
        $query->where('status', $this->status);
    }

    if ($this->instrumentist_id) {
        $query->where('instrumentist_id', (int) $this->instrumentist_id);
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
                ->orWhere('procedure_type', 'like', "%{$term}%")
                ->orWhere('doctor_name', 'like', "%{$term}%")
                ->orWhere('circulating_name', 'like', "%{$term}%");
        });
    }

    return $query->limit(300)->get();
});

$total = computed(function () {
    return $this->procedures->sum(fn($p) => (float) $p->calculated_amount);
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
        'video_rate' => 'indigo',
        'night_rate' => 'rose',
        'long_case_rate' => 'amber',
        default => 'zinc',
    };
};

?>

<div class="max-w-6xl mx-auto p-4 space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">
                {{ __('Procedures') }}
            </flux:heading>
            <flux:subheading>
                {{ __('Admin View') }}
            </flux:subheading>
        </div>
    </div>

    <div class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-center">
            <div class="md:col-span-2 items-center">
                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">
                    {{ __('Search') }}
                </label>
                <flux:input icon="magnifying-glass" wire:model.live="q"
                    placeholder="{{ __('Patient, type, doctor, circulating...') }}" />
            </div>

            <div class="md:col-span-1 items-center">
                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">
                    {{ __('Status') }}
                </label>
                <select wire:model.live="status"
                    class="w-full rounded-lg border-zinc-200 bg-indigo-50 dark:border-zinc-600 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300 focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 p-2.5 hover:border-zinc-300 dark:hover:border-zinc-600 transition-colors">
                    <option value="pending">
                        {{ __('Pending') }}
                    </option>
                    <option value="paid">
                        {{ __('Paid') }}
                    </option>
                    <option value="all">
                        {{ __('All') }}
                    </option>
                </select>
            </div>

            <div class="md:col-span-1 items-center">
                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">
                    {{ __('Instrumentist') }}
                </label>
                <select wire:model.change="instrumentist_id"
                    class="w-full rounded-lg border-zinc-200 bg-indigo-50 dark:border-zinc-600 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300 focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 p-2.5 hover:border-zinc-300 dark:hover:border-zinc-600 transition-colors">
                    <option value="">-- {{ __('All') }} --</option>
                    @foreach($this->instrumentists as $i)
                        <option value="{{ $i->id }}">{{ $i->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="md:col-span-1 items-center">
                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">
                    {{ __('From') }}
                </label>
                <input type="date" wire:model.change="date_from"
                    class="w-full rounded-lg border-zinc-200 bg-indigo-50 dark:border-zinc-600 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300 focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 p-2 text-sm hover:border-zinc-300 dark:hover:border-zinc-600 transition-colors">
            </div>

            <div class="md:col-span-1 items-center">
                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">
                    {{ __('To') }}
                </label>
                <input type="date" wire:model.change="date_to"
                    class="w-full rounded-lg border-zinc-200 bg-indigo-50 dark:border-zinc-600 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300 focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 p-2 text-sm hover:border-zinc-300 dark:hover:border-zinc-600 transition-colors">
            </div>
        </div>

        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between text-sm gap-2">
            <div class="text-zinc-600 dark:text-zinc-400">
                {{ __('Showing') }}
                <span class="font-medium text-zinc-900 dark:text-zinc-100 mx-1">
                    {{ $this->procedures->count() }}
                </span>
                {{ __('max 300') }}
            </div>
            <div class="font-semibold text-emerald-600 dark:text-emerald-400">
                {{ __('Total') }}: Q{{ number_format($this->total, 2) }}
            </div>
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
                            <div class="font-medium text-zinc-900 dark:text-zinc-100 text-lg">
                                {{ $p->patient_name }}
                            </div>
                            <div class="text-xs text-zinc-400 font-mono">
                                {{ $p->procedure_type }}
                            </div>
                            <div class="text-xs text-zinc-500 dark:text-zinc-500">
                                {{ $p->procedure_date?->format('d/m/Y') }}
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <flux:badge size="sm" color="{{ $p->status === 'paid' ? 'green' : 'amber' }}">
                                {{ __($p->status) }}
                            </flux:badge>

                            <flux:dropdown>
                                <flux:button size="sm" icon="ellipsis-vertical" />
                                <flux:menu>
                                    <flux:menu.item href="{{ route('procedures.edit', $p->id) }}" icon="pencil">
                                        {{ __('Edit') }}
                                    </flux:menu.item>
                                    <flux:menu.separator />
                                    <flux:menu.item icon="trash" variant="danger">
                                        {{ __('Delete') }}
                                    </flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </div>
                    </div>

                    <div class="space-y-1 text-sm text-zinc-500 dark:text-zinc-400">
                        <div class="flex justify-between">
                            <span class="font-medium">
                                {{ __('Instrumentist') }}:
                            </span>
                            <span class="font-medium">
                                {{ $p->instrumentist->name ?? '—' }}
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
                            Q{{ number_format((float) $p->calculated_amount, 2) }}
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
        <div class="hidden sm:block overflow-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
            <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700 text-zinc-500 dark:text-zinc-400">
                <thead class="bg-zinc-50 dark:bg-zinc-800 text-center">
                    <tr>
                        <th class="px-4 py-3 font-medium uppercase tracking-wider">
                            <flux:label>
                                {{ __('Date') }}
                            </flux:label>
                        </th>
                        <th class="px-4 py-3 font-medium uppercase tracking-wider">
                            <flux:label>
                                {{ __('Patient') }}
                            </flux:label>
                        </th>
                        <th class="px-4 py-3 font-medium uppercase tracking-wider">
                            <flux:label>
                                {{ __('Procedure') }}
                            </flux:label>
                        </th>
                        <th class="px-4 py-3 font-medium uppercase tracking-wider">
                            <flux:label>
                                {{ __('Instrumentist') }}
                            </flux:label>
                        </th>
                        <th class="px-4 py-3 font-medium uppercase tracking-wider">
                            <div class="flex flex-row justify-between gap-1  items-center">
                                <div class="text-center">
                                    <flux:label>
                                        {{ __('Duration') }}
                                    </flux:label>
                                    <br>
                                    <span class="text-xs text-zinc-500 dark:text-zinc-400">
                                        {{ __('Start') }} - {{ __('End') }}
                                    </span>
                                </div>
                                <div>
                                    <flux:badge size="sm" color="indigo">
                                        {{ __('Rules') }}
                                    </flux:badge>
                                </div>
                            </div>
                        </th>
                        <th class="px-4 py-3 font-medium uppercase tracking-wider">
                            <flux:label>
                                {{ __('Amount') }}
                            </flux:label>
                        </th>
                        <th class="px-4 py-3 font-medium uppercase tracking-wider">
                            <flux:label>
                                {{ __('State') }}
                            </flux:label>
                        </th>

                        @if ($this->status !== 'paid')
                            <th class="px-4 py-3 font-medium uppercase tracking-wider">
                                <flux:label>
                                    {{ __('Actions') }}
                                </flux:label>
                            </th>
                        @endif
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-zinc-900 divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse($this->procedures as $p)
                        @php
                            $rule = data_get($p->pricing_snapshot, 'rule', 'default_rate');
                        @endphp
                        <tr
                            class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors text-zinc-600 dark:text-zinc-300">
                            <td class="px-4 py-3 whitespace-nowrap text-sm">
                                {{ $p->procedure_date->format('d/m/Y') }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                {{ $p->patient_name }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm">
                                {{ $p->procedure_type }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm">
                                {{ $p->instrumentist->name ?? '—' }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-center">
                                <div class="flex flex-row justify-between items-center">
                                    <div>
                                        {{ $p->duration_minutes }}
                                        <span class="text-xs text-zinc-500 dark:text-zinc-400">
                                            {{ __('min') }}
                                        </span>
                                        <br>
                                        <span class="text-xs text-zinc-500 dark:text-zinc-400">
                                            {{ \Carbon\Carbon::parse($p->start_time)->format('H:i') }}
                                            <span>
                                                -
                                            </span>
                                            {{ \Carbon\Carbon::parse($p->end_time)->format('H:i') }}
                                        </span>
                                    </div>
                                    <div>
                                        <x-procedure-rule-badge :rule="$rule" :videosurgery="$p->is_videosurgery" />
                                    </div>
                                </div>
                            </td>
                            <td
                                class="px-4 py-3 whitespace-nowrap text-right text-sm font-bold text-emerald-600 dark:text-emerald-400">
                                Q{{ number_format((float) $p->calculated_amount, 2) }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-center">
                                <flux:badge size="sm" color="{{ $p->status === 'paid' ? 'green' : 'zinc' }}">
                                    {{ __($p->status) }}
                                </flux:badge>
                            </td>
                            @if ($p->status === 'paid' && $this->status === 'all')
                                <td></td>
                            @endif
                            @if ($p->status === 'pending' && ($this->status === 'all' || $this->status === 'pending'))
                                <td class="px-4 py-3 whitespace-nowrap text-center">
                                    <div class="flex flex-row justify-center items-center gap-2">
                                        <a href="{{ route('procedures.edit', $p->id) }}"
                                            class="inline-flex items-center gap-1.5 text-sm text-indigo-500 dark:text-indigo-500 hover:text-indigo-900 dark:hover:text-indigo-900 transition-colors">
                                            <flux:icon name="pencil" size="sm" />
                                        </a>
                                        <a
                                            class="inline-flex items-center gap-1.5 text-sm text-red-500 dark:text-red-500 hover:text-red-900 dark:hover:text-red-900 transition-colors">
                                            <flux:icon name="trash" size="sm" />
                                        </a>
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-6 py-8 text-center text-sm text-zinc-500 dark:text-zinc-400 italic">
                                {{ __('No results found') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>