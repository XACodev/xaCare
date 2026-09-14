<?php

use App\Modules\QxLog\Models\PayoutBatch;
use App\Modules\QxLog\Models\PayoutItem;
use App\Modules\QxLog\Models\SurgicalAssignment;
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
    'hospitalId' => null,
]);

rules(fn () => [
    'payee_id' => ['required', 'integer', Rule::exists('users', 'id')->where('hospital_id', $this->hospitalId)],
    'selected' => ['array'],
    'selected.*' => ['integer', Rule::exists('surgical_assignments', 'id')->where('hospital_id', $this->hospitalId)],
]);

mount(function () {
    $user = Auth::user();
    abort_unless((bool) $user, 401);
    abort_unless($user->can("payouts.create"), 403);
    abort_if((bool) $user->is_platform_admin, 403, 'Administrador de plataforma es de solo lectura; usa una cuenta de hospital para operar.');
    abort_if(! $user->hospital_id, 422, 'Tu usuario no tiene un hospital asignado.');

    $this->hospitalId = $user->hospital_id;

    $this->payees = User::query()
        ->where('hospital_id', $this->hospitalId)
        ->whereHas('assignments', fn ($q) => $q->where('status', 'pending')->where('hospital_id', $this->hospitalId))
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
        ->where('hospital_id', $this->hospitalId)
        ->where('user_id', $this->payee_id)
        ->where('status', 'pending')
        ->with(['surgicalCase.procedureType', 'surgicalRole'])
        ->orderByDesc('created_at')
        ->get();
});

$pending_total = computed(function () {
    if (!$this->payee_id) return 0.0;
    return (float) SurgicalAssignment::query()
        ->where('hospital_id', $this->hospitalId)
        ->where('user_id', $this->payee_id)
        ->where('status', 'pending')
        ->sum('calculated_amount');
});

$selected_total = computed(function () {
    $ids = array_filter(array_map('intval', (array) $this->selected));
    if (!$this->payee_id || empty($ids)) return 0.0;

    return (float) SurgicalAssignment::query()
        ->where('hospital_id', $this->hospitalId)
        ->where('user_id', $this->payee_id)
        ->where('status', 'pending')
        ->whereIn('id', $ids)
        ->sum('calculated_amount');
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

    $batch = DB::transaction(function () use ($admin, $data, $selectedIds) {
        $assignments = SurgicalAssignment::query()
            ->where('hospital_id', $this->hospitalId)
            ->where('user_id', $data['payee_id'])->where('status', 'pending')
            ->whereIn('id', $selectedIds)->lockForUpdate()->get();

        if ($assignments->count() !== count($selectedIds)) {
            throw ValidationException::withMessages(['selected' => __('Selection changed. Reload and try again.')]);
        }

        $total = (float) $assignments->sum('calculated_amount');

        $batch = PayoutBatch::create([
            'hospital_id' => $this->hospitalId,
            'payee_id' => (int) $data['payee_id'],
            'paid_by_id' => $admin->id,
            'paid_at' => now(),
            'total_amount' => $total,
            'status' => 'paid',
        ]);

        foreach ($assignments as $a) {
            $item = PayoutItem::create([
                'hospital_id' => $this->hospitalId,
                'payout_batch_id' => $batch->id,
                'surgical_assignment_id' => $a->id,
                'amount' => (float) $a->calculated_amount,
                'snapshot' => [
                    'procedure_date' => $a->surgicalCase->procedure_date,
                    'patient_name' => $a->surgicalCase->patient_name,
                    'procedure_type' => $a->surgicalCase->procedureType?->name,
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

<div class="max-w-6xl mx-auto p-4 space-y-4">
    <div>
        <div class="text-xs text-zinc-500 dark:text-zinc-400 mb-1">{{ __('Pagos') }} / {{ __('Realizar pago') }}</div>
        <flux:heading size="xl">{{ __('Realizar pago') }}</flux:heading>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_320px] gap-5 items-start">
        <div class="space-y-4 min-w-0">
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-5">
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
                @error('selected')
                    <p class="text-sm font-medium text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/20 p-2 rounded">
                        {{ $message }}
                    </p>
                @enderror

                <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 overflow-hidden">
                    <div class="flex items-center justify-between px-4 py-3.5 border-b border-zinc-100 dark:border-zinc-800">
                        <span class="font-semibold text-sm">
                            {{ __(':count procedures', ['count' => $this->pending_count]) }}
                        </span>
                        <button type="button" wire:click="toggleAll" class="text-sm font-semibold text-accent hover:underline">
                            {{ __('Seleccionar todos') }}
                        </button>
                    </div>

                    {{-- Desktop Table --}}
                    <div class="hidden md:block overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="qx-table-head">
                                    <th class="w-10 pl-4"></th>
                                    <th scope="col" class="px-4 py-3 text-left font-semibold tracking-wider">{{ __('Fecha') }}</th>
                                    <th scope="col" class="px-4 py-3 text-left font-semibold tracking-wider">{{ __('Procedimiento') }}</th>
                                    <th scope="col" class="px-4 py-3 text-left font-semibold tracking-wider">{{ __('Paciente') }}</th>
                                    <th scope="col" class="px-4 py-3 text-left font-semibold tracking-wider">{{ __('Reglas') }}</th>
                                    <th scope="col" class="px-4 py-3 text-right font-semibold tracking-wider">{{ __('Monto') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($this->pending_assignments as $p)
                                    <tr wire:key="assignment-{{ $p->id }}" class="qx-table-row">
                                        <td class="pl-4">
                                            <flux:checkbox wire:model.live="selected" value="{{ $p->id }}" />
                                        </td>
                                        <td class="px-4 py-3 text-zinc-700 dark:text-zinc-300 tabular-nums">
                                            {{ $p->surgicalCase->procedure_date->format('d/m/Y') }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="font-medium truncate max-w-45" title="{{ $p->surgicalCase->procedureType?->name }}">
                                                {{ $p->surgicalCase->procedureType?->name }}
                                            </div>
                                            <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                                {{ Carbon\Carbon::parse($p->surgicalCase->start_time)->format('H:i') }}
                                                - {{ Carbon\Carbon::parse($p->surgicalCase->end_time)->format('H:i') }}
                                                · {{ $p->surgicalRole->name }}
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-zinc-700 dark:text-zinc-300 capitalize">
                                            {{ strtolower($p->surgicalCase->patient_name) }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <x-procedure-rule-badge :rule="data_get($p, 'pricing_snapshot.rule')"
                                                :videosurgery="$p->surgicalCase->is_videosurgery" />
                                        </td>
                                        <td class="px-4 py-3 text-right font-semibold tabular-nums">
                                            Q{{ number_format((float) $p->calculated_amount, 2) }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-6 py-8 text-center text-sm text-zinc-500 dark:text-zinc-400 italic">
                                            {{ __('No pending procedures.') }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{-- Mobile Cards --}}
                    <div class="md:hidden divide-y divide-zinc-100 dark:divide-zinc-800">
                        @forelse($this->pending_assignments as $p)
                            <div class="p-4 space-y-3">
                                <div class="flex items-start justify-between gap-4">
                                    <div class="flex items-center gap-3">
                                        <flux:checkbox wire:model.live="selected" value="{{ $p->id }}" />
                                        <div>
                                            <div class="font-medium text-zinc-900 dark:text-zinc-100">
                                                {{ $p->surgicalCase->patient_name }}
                                            </div>
                                            <div class="text-sm text-zinc-500 dark:text-zinc-400">
                                                {{ $p->surgicalCase->procedureType?->name }}
                                            </div>
                                            <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                                {{ $p->surgicalRole->name }}
                                            </div>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="font-semibold tabular-nums">
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
            @endif
        </div>

        @if($this->payee_id)
            <aside class="lg:sticky lg:top-4">
                <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-5 space-y-4">
                    <div class="font-semibold">{{ __('Resumen del lote') }}</div>

                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-zinc-500 dark:text-zinc-400">{{ __('Seleccionados') }}</span>
                            <span>{{ __(':count selected', ['count' => $this->selected_count]) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-zinc-500 dark:text-zinc-400">{{ __('Total pending') }}</span>
                            <span class="tabular-nums">Q{{ number_format($this->pending_total ?? 0, 2) }}</span>
                        </div>
                    </div>

                    <div class="flex justify-between items-baseline border-t border-zinc-100 dark:border-zinc-800 pt-3">
                        <span class="font-semibold">{{ __('Total a pagar') }}</span>
                        <span class="text-2xl font-semibold tracking-tight tabular-nums">
                            Q{{ number_format($this->selected_total ?? 0, 2) }}
                        </span>
                    </div>

                    <flux:button wire:click="liquidate" loading="liquidate" variant="primary" class="w-full">
                        {{ __('Liquidar y generar voucher') }}
                    </flux:button>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400 text-center leading-relaxed">
                        {{ __('Se marcarán como pagados y el voucher se puede imprimir o enviar.') }}
                    </p>
                </div>
            </aside>
        @endif
    </div>
</div>