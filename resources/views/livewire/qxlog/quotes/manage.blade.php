<?php
// resources/views/livewire/qxlog/quotes/manage.blade.php

use App\Models\Patient;
use App\Modules\QxLog\Models\ProcedureType;
use App\Modules\QxLog\Models\SurgeryQuote;
use App\Modules\QxLog\Models\SurgicalRole;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

use function Livewire\Volt\{state, mount, computed};

state([
    'quote' => null,
    'patient_id' => null,
    'patient_query' => '',
    'patient_free_text' => null,
    'procedure_query' => '',
    'specialties' => [],
    'specialty_input' => '',
    'line_items' => [],
    'hospital_cost' => 0,
    'hospital_cost_note' => '',
    'internal_note' => '',
]);

mount(function (?SurgeryQuote $quote = null) {
    $user = Auth::user();
    abort_unless($user && $user->can('surgeries.budget.manage'), 403);

    if ($quote && $quote->exists) {
        abort_unless($quote->hospital_id === $user->hospital_id, 403);

        $this->quote = $quote;
        $this->patient_id = $quote->patient_id;
        $this->patient_query = $quote->patient->nombreCompleto();
        $this->procedure_query = $quote->procedureType?->name ?? '';
        $this->specialties = $quote->specialties ?? [];
        $this->hospital_cost = (float) $quote->hospital_cost;
        $this->hospital_cost_note = $quote->hospital_cost_note ?? '';
        $this->internal_note = $quote->internal_note ?? '';
        $this->line_items = $quote->lineItems->map(fn ($item) => [
            'surgical_role_id' => $item->surgical_role_id,
            'label' => $item->label,
            'amount' => (float) $item->amount,
        ])->all();

        return;
    }

    $prefillPatientId = request()->integer('patient_id') ?: null;
    if ($prefillPatientId) {
        $patient = Patient::query()->where('id', $prefillPatientId)
            ->where('hospital_id', $user->hospital_id)->first();
        if ($patient) {
            $this->patient_id = $patient->id;
            $this->patient_query = $patient->nombreCompleto();
        }
    }
});

$patientSuggestions = computed(function () {
    return fn (string $query) => Patient::query()
        ->where('hospital_id', Auth::user()?->hospital_id)
        ->when(trim($query) !== '', fn ($q) => $q->where(function ($q2) use ($query) {
            $q2->where('primer_nombre', 'like', "%{$query}%")
                ->orWhere('primer_apellido', 'like', "%{$query}%");
        }))
        ->orderBy('primer_apellido')
        ->limit(8)
        ->get()
        ->map(fn ($p) => ['id' => $p->id, 'name' => $p->nombreCompleto()])
        ->all();
});

$surgicalRoles = computed(fn () => SurgicalRole::query()
    ->where('hospital_id', Auth::user()?->hospital_id)
    ->where('active', true)
    ->orderBy('sort_order')
    ->get(['id', 'name']));

$selectPatient = function (int $id, string $name) {
    $this->patient_id = $id;
    $this->patient_free_text = null;
    $this->patient_query = $name;
};

$useFreeTextPatient = function () {
    $this->patient_id = null;
    $this->patient_free_text = trim($this->patient_query) ?: null;
};

$addLineItem = function () {
    $this->line_items[] = ['surgical_role_id' => null, 'label' => '', 'amount' => 0];
};

$removeLineItem = function (int $index) {
    unset($this->line_items[$index]);
    $this->line_items = array_values($this->line_items);
};

$addSpecialty = function () {
    $value = trim($this->specialty_input);
    if ($value !== '' && ! in_array($value, $this->specialties, true)) {
        $this->specialties[] = $value;
    }
    $this->specialty_input = '';
};

$removeSpecialty = function (int $index) {
    unset($this->specialties[$index]);
    $this->specialties = array_values($this->specialties);
};

$lineItemsTotal = computed(fn () => collect($this->line_items)->sum(fn ($item) => (float) ($item['amount'] ?? 0)));

$save = function () {
    $user = Auth::user();

    $data = $this->validate([
        'line_items' => ['required', 'array', 'min:1'],
        'line_items.*.label' => ['required', 'string', 'max:255'],
        'line_items.*.amount' => ['required', 'numeric', 'min:0'],
        'line_items.*.surgical_role_id' => ['nullable', 'integer', Rule::exists('surgical_roles', 'id')->where('hospital_id', $user->hospital_id)],
        'hospital_cost' => ['required', 'numeric', 'min:0'],
        'hospital_cost_note' => ['nullable', 'string', 'max:2000'],
        'internal_note' => ['nullable', 'string', 'max:2000'],
        'procedure_query' => ['nullable', 'string', 'max:255'],
        'specialties' => ['array'],
        'specialties.*' => ['string', 'max:100'],
    ]);

    if (! $this->patient_id && ! $this->patient_free_text) {
        $this->addError('patient_id', __('Selecciona un paciente o escribe su nombre.'));

        return;
    }

    $patientId = $this->patient_id;
    if (! $patientId && $this->patient_free_text) {
        $patientId = Patient::query()->firstOrCreate(
            ['hospital_id' => $user->hospital_id, 'primer_nombre' => $this->patient_free_text, 'primer_apellido' => ''],
        )->id;
    }

    $procedureTypeId = null;
    if (trim($this->procedure_query) !== '') {
        $procedureTypeId = ProcedureType::resolveOrCreateFor($user->hospital_id, $this->procedure_query)->id;
    }

    $quote = SurgeryQuote::saveDraftOrNewVersion([
        'hospital_id' => $user->hospital_id,
        'patient_id' => $patientId,
        'surgical_case_id' => $this->quote?->surgical_case_id,
        'procedure_type_id' => $procedureTypeId,
        'hospital_cost' => $data['hospital_cost'],
        'hospital_cost_note' => $data['hospital_cost_note'] ?: null,
        'internal_note' => $data['internal_note'] ?: null,
        'specialties' => $this->specialties,
        'created_by_id' => $user->id,
    ], $data['line_items']);

    return $this->redirect(route('quotes.show', $quote), navigate: true);
};
?>

<div class="p-6 max-w-2xl space-y-6">
    <flux:heading size="lg">{{ $quote ? __('Editar cotización') : __('Nueva cotización') }}</flux:heading>

    <flux:field class="relative">
        <flux:label>{{ __('Paciente') }}</flux:label>
        <flux:input wire:model.live.debounce.300ms="patient_query" :disabled="(bool) $quote" />
        @if(!$quote && trim($patient_query) !== '' && !$patient_id)
            <div class="absolute z-20 left-0 right-0 top-full mt-1 border rounded bg-white dark:bg-zinc-900 shadow-lg">
                @foreach(($this->patientSuggestions)($patient_query) as $suggestion)
                    <button type="button" wire:click="selectPatient({{ $suggestion['id'] }}, '{{ $suggestion['name'] }}')"
                        class="block w-full text-left px-2 py-1 hover:bg-zinc-100 dark:hover:bg-zinc-800">
                        {{ $suggestion['name'] }}
                    </button>
                @endforeach
                <button type="button" wire:click="useFreeTextPatient"
                    class="block w-full text-left px-2 py-1 text-sm font-medium text-teal-700 dark:text-teal-400 border-t hover:bg-zinc-100 dark:hover:bg-zinc-800">
                    {{ __('Usar ":name" como paciente nuevo', ['name' => $patient_query]) }}
                </button>
            </div>
        @endif
    </flux:field>

    <flux:field>
        <flux:label>{{ __('Procedimiento') }}</flux:label>
        <flux:input wire:model.live.debounce.300ms="procedure_query" />
    </flux:field>

    <flux:field>
        <flux:label>{{ __('Especialidades participantes') }}</flux:label>
        <div class="flex flex-wrap gap-2 mb-2">
            @foreach($specialties as $index => $specialty)
                <flux:badge wire:key="specialty-{{ $index }}">
                    {{ $specialty }}
                    <button type="button" wire:click="removeSpecialty({{ $index }})" class="ml-1">&times;</button>
                </flux:badge>
            @endforeach
        </div>
        <div class="flex gap-2">
            <flux:input wire:model="specialty_input" wire:keydown.enter.prevent="addSpecialty" placeholder="{{ __('Ej. Traumatología') }}" />
            <flux:button type="button" wire:click="addSpecialty">{{ __('Agregar') }}</flux:button>
        </div>
    </flux:field>

    <div class="space-y-2">
        <flux:label>{{ __('Honorarios') }}</flux:label>
        @foreach($line_items as $index => $item)
            <div class="flex gap-2 items-end" wire:key="line-item-{{ $index }}">
                <flux:field class="flex-1">
                    <flux:select wire:model="line_items.{{ $index }}.surgical_role_id">
                        <option value="">{{ __('Renglón libre') }}</option>
                        @foreach($this->surgicalRoles as $role)
                            <option value="{{ $role->id }}">{{ $role->name }}</option>
                        @endforeach
                    </flux:select>
                </flux:field>
                <flux:field class="flex-1">
                    <flux:input wire:model="line_items.{{ $index }}.label" placeholder="{{ __('Descripción') }}" />
                </flux:field>
                <flux:field class="w-32">
                    <flux:input type="number" step="0.01" wire:model="line_items.{{ $index }}.amount" />
                </flux:field>
                <flux:button type="button" variant="ghost" wire:click="removeLineItem({{ $index }})">&times;</flux:button>
            </div>
        @endforeach
        <flux:button type="button" wire:click="addLineItem">{{ __('+ Agregar renglón') }}</flux:button>
        @error('line_items') <flux:error>{{ $message }}</flux:error> @enderror
        <div class="font-bold text-right">{{ __('Total honorarios') }}: Q{{ number_format($this->lineItemsTotal, 2) }}</div>
    </div>

    <flux:field>
        <flux:label>{{ __('Costo de hospital') }}</flux:label>
        <flux:input type="number" step="0.01" wire:model="hospital_cost" />
    </flux:field>

    <flux:field>
        <flux:label>{{ __('Notas') }}</flux:label>
        <flux:textarea wire:model="hospital_cost_note" />
    </flux:field>

    <flux:field>
        <flux:label class="text-amber-700 dark:text-amber-500">{{ __('Nota interna (no se imprime)') }}</flux:label>
        <flux:textarea wire:model="internal_note" class="border-amber-400" />
    </flux:field>

    <div class="font-bold text-xl text-right">{{ __('Total') }}: Q{{ number_format($this->lineItemsTotal + (float) $hospital_cost, 2) }}</div>

    <flux:button variant="primary" wire:click="save">{{ __('Guardar') }}</flux:button>
</div>
