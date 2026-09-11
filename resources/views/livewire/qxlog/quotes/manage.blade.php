<?php
// resources/views/livewire/qxlog/quotes/manage.blade.php

use App\Models\Patient;
use App\Modules\QxLog\Models\SurgeryQuote;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

use function Livewire\Volt\{state, mount, computed};

state([
    'quote' => null,
    'patient_id' => null,
    'patient_query' => '',
    'staff_fee' => 0,
    'hospital_cost' => 0,
    'hospital_cost_note' => '',
]);

mount(function (?SurgeryQuote $quote = null) {
    $user = Auth::user();
    abort_unless($user && $user->can('surgeries.budget.manage'), 403);

    if ($quote && $quote->exists) {
        abort_unless($quote->hospital_id === $user->hospital_id, 403);

        $this->quote = $quote;
        $this->patient_id = $quote->patient_id;
        $this->patient_query = $quote->patient->nombreCompleto();
        $this->staff_fee = (float) $quote->staff_fee;
        $this->hospital_cost = (float) $quote->hospital_cost;
        $this->hospital_cost_note = $quote->hospital_cost_note ?? '';

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

$selectPatient = function (int $id, string $name) {
    $this->patient_id = $id;
    $this->patient_query = $name;
};

$save = function () {
    $user = Auth::user();

    $data = $this->validate([
        'patient_id' => ['required', 'integer', Rule::exists('patients', 'id')->where('hospital_id', $user->hospital_id)],
        'staff_fee' => ['required', 'numeric', 'min:0'],
        'hospital_cost' => ['required', 'numeric', 'min:0'],
        'hospital_cost_note' => ['nullable', 'string', 'max:2000'],
    ]);

    $quote = SurgeryQuote::saveDraftOrNewVersion([
        'hospital_id' => $user->hospital_id,
        'patient_id' => $data['patient_id'],
        'staff_fee' => $data['staff_fee'],
        'hospital_cost' => $data['hospital_cost'],
        'hospital_cost_note' => $data['hospital_cost_note'] ?: null,
        'created_by_id' => $user->id,
    ]);

    return $this->redirect(route('quotes.show', $quote), navigate: true);
};
?>

<div class="p-6 max-w-2xl space-y-4">
    <flux:heading size="lg">{{ $quote ? __('Editar cotización') : __('Nueva cotización') }}</flux:heading>

    <flux:field>
        <flux:label>{{ __('Paciente') }}</flux:label>
        <flux:input wire:model.live.debounce.300ms="patient_query" :disabled="(bool) $quote" />
        @if(!$quote && trim($patient_query) !== '' && !$patient_id)
            <div class="border rounded mt-1">
                @foreach(($this->patientSuggestions)($patient_query) as $suggestion)
                    <button type="button" wire:click="selectPatient({{ $suggestion['id'] }}, '{{ $suggestion['name'] }}')"
                        class="block w-full text-left px-2 py-1 hover:bg-zinc-100">
                        {{ $suggestion['name'] }}
                    </button>
                @endforeach
            </div>
        @endif
    </flux:field>

    <flux:field>
        <flux:label>{{ __('Honorario del staff') }}</flux:label>
        <flux:input type="number" step="0.01" wire:model="staff_fee" />
    </flux:field>

    <flux:field>
        <flux:label>{{ __('Costo de hospital') }}</flux:label>
        <flux:input type="number" step="0.01" wire:model="hospital_cost" />
    </flux:field>

    <flux:field>
        <flux:label>{{ __('Qué incluye (nota)') }}</flux:label>
        <flux:textarea wire:model="hospital_cost_note" />
    </flux:field>

    <flux:button variant="primary" wire:click="save">{{ __('Guardar') }}</flux:button>
</div>
