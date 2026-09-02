<?php

use App\Models\PayoutBatch;
use App\Models\PayoutItem;
use App\Models\SurgicalAssignment;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

use function Livewire\Volt\{state, computed, mount, rules, updated};

state([
    'payee_id' => '',
    'payees' => [],
    'selected' => [],
]);

rules(fn () => [
    'payee_id' => ['required', 'integer', Rule::exists('users', 'id')->when(Auth::user()?->hospital_id, fn ($rule) => $rule->where('hospital_id', Auth::user()->hospital_id))],
    'selected' => ['array'],
    'selected.*' => ['integer', Rule::exists('surgical_assignments', 'id')->when(Auth::user()?->hospital_id, fn ($rule) => $rule->where('hospital_id', Auth::user()->hospital_id))],
]);

mount(function () {
    $user = Auth::user();
    abort_unless((bool) $user, 401);
    abort_unless($user->can("payouts.create"), 403);
    abort_if((bool) $user->is_super_admin, 403, 'Super admin es de solo lectura; usa una cuenta de hospital para operar.');

    $this->payees = User::query()
        ->whereHas('assignments', fn ($q) => $q->where('status', 'pending'))
        ->orderBy('name')
        ->get(['id', 'name'])
        ->map(fn($u) => ['id' => $u->id, 'name' => $u->name]);

    $preselected = request()->integer('payee_id');
    if ($preselected && $this->payees->contains('id', $preselected)) {
        $this->payee_id = $preselected;
    }
});

updated(['payee_id' => function () { $this->selected = []; }]);

$pending_assignments = computed(function () {
    if (!$this->payee_id) return collect();

    return SurgicalAssignment::query()
        ->where('user_id', $this->payee_id)
        ->where('status', 'pending')
        ->with(['surgicalCase', 'surgicalRole'])
        ->orderByDesc('created_at')
        ->get();
});

$pending_total = computed(function () {
    if (!$this->payee_id) return 0.0;
    return (float) SurgicalAssignment::query()->where('user_id', $this->payee_id)->where('status', 'pending')->sum('calculated_amount');
});

$selected_total = computed(function () {
    $ids = array_filter(array_map('intval', (array) $this->selected));
    if (!$this->payee_id || empty($ids)) return 0.0;

    return (float) SurgicalAssignment::query()
        ->where('user_id', $this->payee_id)->where('status', 'pending')->whereIn('id', $ids)->sum('calculated_amount');
});

$pending_count = computed(fn () => $this->pending_assignments->count());
$selected_count = computed(fn () => count(array_filter(array_map('intval', (array) $this->selected))));

$toggleAll = function () {
    $list = $this->pending_assignments;
    if ($list->isEmpty()) { $this->selected = []; return; }

    $allIds = $list->pluck('id')->map(fn($v) => (int) $v)->all();
    $current = array_map('intval', (array) $this->selected);
    $allSelected = count(array_diff($allIds, $current)) === 0 && count($allIds) === count($current);
    $this->selected = $allSelected ? [] : $allIds;
};

$liquidate = function () {
    $admin = Auth::user();
    abort_unless((bool) $admin, 401);
    abort_unless($admin->can("payouts.create"), 403);

    $data = $this->validate();
    $selectedIds = array_values(array_unique(array_map('intval', (array) $data['selected'])));
    if (empty($selectedIds)) {
        throw ValidationException::withMessages(['selected' => __('Select an item to liquidate.')]);
    }

    $assignments = SurgicalAssignment::query()
        ->where('user_id', $data['payee_id'])->where('status', 'pending')
        ->whereIn('id', $selectedIds)->lockForUpdate()->get();

    if ($assignments->count() !== count($selectedIds)) {
        throw ValidationException::withMessages(['selected' => __('Selection changed. Reload and try again.')]);
    }

    $total = (float) $assignments->sum('calculated_amount');

    $batch = DB::transaction(function () use ($admin, $data, $assignments, $total) {
        $batch = PayoutBatch::create([
            'payee_id' => (int) $data['payee_id'],
            'paid_by_id' => $admin->id,
            'paid_at' => now(),
            'total_amount' => $total,
            'status' => 'paid',
        ]);

        foreach ($assignments as $a) {
            $item = PayoutItem::create([
                'payout_batch_id' => $batch->id,
                'surgical_assignment_id' => $a->id,
                'amount' => (float) $a->calculated_amount,
                'snapshot' => [
                    'procedure_date' => $a->surgicalCase->procedure_date,
                    'patient_name' => $a->surgicalCase->patient_name,
                    'procedure_type' => $a->surgicalCase->procedure_type,
                    'role' => $a->surgicalRole->name,
                    'calculated_amount' => (float) $a->calculated_amount,
                    'pricing_snapshot' => $a->pricing_snapshot,
                ],
            ]);

            $a->update(['status' => 'paid', 'payout_item_id' => $item->id]);
        }

        return $batch;
    });

    $this->redirectRoute('payouts.voucher', ['batch' => $batch->id], navigate: true);
};

?>

<div class="max-w-6xl mx-auto p-4 space-y-6">
    <div class="mb-4">
        <flux:heading size="xl">{{ __('Liquidate Procedures') }}</flux:heading>
        <flux:subheading>{{ __('Generate payout batch') }}</flux:subheading>
    </div>

    <div class="rounded-xl border bg-white p-6 dark:bg-zinc-900 dark:border-zinc-700 space-y-6">
        <div>
            <flux:select wire:model.change="payee_id" label="{{ __('Instrumentist') }}"
                placeholder="{{ __('Select instrumentist') }}" empty="{{ __('Not found') }}">
                @foreach($this->payees as $i)
                    <flux:select.option value="{{ $i['id'] }}">
                        {{ $i['name'] }}
                    </flux:select.option>
                @endforeach
            </flux:select>
        </div>

        @if($this->payee_id)
            <div
                class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-4 bg-zinc-50 dark:bg-zinc-800/50 rounded-lg border border-zinc-100 dark:border-zinc-700/50">
                <div class="space-y-1">
                    <div class="text-sm text-zinc-500 dark:text-zinc-400">
                        {{ __('Total pending') }}
                    </div>
                    <div class="text-xl font-bold text-emerald-600 dark:text-emerald-400">
                        Q{{ number_format($this->pending_total ?? 0, 2) }}
                    </div>
                    <div class="text-xs text-zinc-500 dark:text-zinc-400">
                        {{ __(':count procedures', ['count' => $this->pending_count]) }}
                    </div>
                </div>

                <div class="space-y-1">
                    <div class="text-sm text-zinc-500 dark:text-zinc-400">
                        {{ __('Total selected') }}
                    </div>
                    <div class="text-xl font-bold text-emerald-600 dark:text-emerald-400">
                        Q{{ number_format($this->selected_total ?? 0, 2) }}
                    </div>
                    <div class="text-xs text-zinc-500 dark:text-zinc-400">
                        {{ __(':count selected', ['count' => $this->selected_count]) }}
                    </div>
                </div>

                <flux:button wire:click="toggleAll" variant="filled" size="sm">
                    {{ __('Select / Unselect all') }}
                </flux:button>
            </div>

            @error('selected')
                <p class="text-sm font-medium text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/20 p-2 rounded">
                    {{ $message }}
                </p>
            @enderror

            <div class="overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-700">

                {{-- Desktop Table --}}
                <div class="hidden md:block overflow-x-auto overflow-y-auto">
                    <table
                        class="min-w-full text-sm divide-y divide-zinc-200 dark:divide-zinc-700 text-zinc-500 dark:text-zinc-400">
                        <thead class="bg-zinc-50 dark:bg-zinc-800 text-center">
                            <tr>
                                <th class="px-4 py-3 font-medium text-left">
                                    <flux:checkbox wire:click="toggleAll" />
                                </th>
                                <th class="px-4 py-3 font-medium">
                                    <flux:label>
                                        {{ __('Date') }}
                                    </flux:label>
                                </th>
                                <th class="px-4 py-3 font-medium">
                                    <div class="flex items-center justify-between">
                                        <flux:label>
                                            {{ __('Duration') }}
                                        </flux:label>
                                        <flux:badge size="sm" color="indigo">
                                            {{ __('Rules') }}
                                        </flux:badge>
                                    </div>
                                </th>
                                <th class="px-4 py-3 font-medium">
                                    <flux:label>
                                        {{ __('Patient') }}
                                    </flux:label>
                                </th>
                                <th class="px-4 py-3 font-medium">
                                    <flux:label>
                                        {{ __('Surgery') }}
                                    </flux:label>
                                </th>
                                <th class="px-4 py-3 font-medium">
                                    <flux:label>
                                        {{ __('Role') }}
                                    </flux:label>
                                </th>
                                <th class="px-4 py-3 font-medium text-right">
                                    <flux:label>
                                        {{ __('Amount') }}
                                    </flux:label>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700 bg-white dark:bg-zinc-900">
                            @forelse($this->pending_assignments as $p)
                                <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors text-sm">
                                    <td class="px-4 py-3">
                                        <flux:checkbox wire:model.live="selected" value="{{ $p->id }}" />
                                    </td>
                                    <td class="px-4 py-3">
                                        {{ $p->surgicalCase->procedure_date->format('d/m/Y') }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-center">
                                        <div class="flex flex-row justify-between items-center">
                                            <div class="flex flex-col items-center">
                                                <div>
                                                    {{ $p->surgicalCase->duration_minutes }}
                                                    <span class="text-xs">
                                                        {{ __('min') }}
                                                    </span>
                                                </div>
                                                <span class="text-xs">
                                                    {{ Carbon\Carbon::parse($p->surgicalCase->start_time)->format('H:i') }}
                                                    -
                                                    {{ Carbon\Carbon::parse($p->surgicalCase->end_time)->format('H:i') }}
                                                </span>
                                            </div>
                                            <div>
                                                <x-procedure-rule-badge :rule="data_get($p, 'pricing_snapshot.rule')"
                                                    :videosurgery="$p->surgicalCase->is_videosurgery" />
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 font-medium capitalize text-zinc-900 dark:text-zinc-100">
                                        {{ strtolower($p->surgicalCase->patient_name) }}
                                    </td>
                                    <td class="px-4 py-3 truncate max-w-45" title="{{ $p->surgicalCase->procedure_type }}">
                                        {{ $p->surgicalCase->procedure_type }}
                                    </td>
                                    <td class="px-4 py-3">
                                        {{ $p->surgicalRole->name }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-bold">
                                        Q{{ number_format((float) $p->calculated_amount, 2) }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-8 text-center text-zinc-500 dark:text-zinc-400">
                                        {{ __('No pending procedures.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Mobile Cards --}}
                <div class="md:hidden divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse($this->pending_assignments as $p)
                        <div class="p-4 bg-white dark:bg-zinc-900 space-y-3">
                            <div class="flex items-start justify-between gap-4">
                                <div class="flex items-center gap-3">
                                    <flux:checkbox wire:model.live="selected" value="{{ $p->id }}" />
                                    <div>
                                        <div class="font-medium text-zinc-900 dark:text-zinc-100">
                                            {{ $p->surgicalCase->patient_name }}
                                        </div>
                                        <div class="text-sm text-zinc-500 dark:text-zinc-400">
                                            {{ $p->surgicalCase->procedure_type }}
                                        </div>
                                        <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                            {{ $p->surgicalRole->name }}
                                        </div>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="font-mono font-medium text-emerald-600 dark:text-emerald-400">
                                        Q{{ number_format((float) $p->calculated_amount, 2) }}
                                    </div>
                                    <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                        {{ $p->surgicalCase->procedure_date->format('d/m/Y') }}
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center justify-between text-sm text-zinc-500 dark:text-zinc-400 pl-8">
                                <div>
                                    {{ __('Duration') }}: {{ $p->surgicalCase->duration_minutes }} {{ __('min') }}
                                    <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                        {{ Carbon\Carbon::parse($p->surgicalCase->start_time)->format('H:i') }} -
                                        {{ Carbon\Carbon::parse($p->surgicalCase->end_time)->format('H:i') }}
                                    </div>
                                </div>
                                <x-procedure-rule-badge :rule="data_get($p, 'pricing_snapshot.rule')"
                                    :videosurgery="$p->surgicalCase->is_videosurgery" />
                            </div>
                        </div>
                    @empty
                        <div class="p-8 text-center text-zinc-500 dark:text-zinc-400">
                            {{ __('No pending procedures.') }}
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="flex justify-end pt-2">
                <flux:button wire:click="liquidate" loading="liquidate" variant="primary">
                    {{ __('Liquidate selected') }}
                </flux:button>
            </div>
        @endif
    </div>
</div>