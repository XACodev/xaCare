<?php

use App\Models\Admission;
use App\Models\Patient;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

use function Livewire\Volt\{state, mount, rules, computed};

state([
    'patient_id' => null,
    'patient_query' => '',
    'va_a_quirofano' => false,
    'fecha_ingreso' => now()->toDateString(),
    'hora_ingreso' => now()->format('H:i'),
    'sala_ingreso' => '',
    'tiene_seguro' => false,
    'compania_seguros' => '',
    'poliza' => '',
    'certificado' => '',
    'impresion_clinica' => '',
    'medico_responsable' => '',
    'success_message' => null,
]);

rules([
    'patient_id' => ['required', 'integer', 'exists:patients,id'],
    'va_a_quirofano' => ['boolean'],
    'fecha_ingreso' => ['required', 'date'],
    'hora_ingreso' => ['nullable', 'date_format:H:i'],
    'sala_ingreso' => ['nullable', 'string', 'max:255'],
    'tiene_seguro' => ['boolean'],
    'compania_seguros' => ['nullable', 'string', 'max:255'],
    'poliza' => ['nullable', 'string', 'max:255'],
    'certificado' => ['nullable', 'string', 'max:255'],
    'impresion_clinica' => ['nullable', 'string'],
    'medico_responsable' => ['nullable', 'string', 'max:255'],
]);

mount(function () {
    abort_unless(Auth::check(), 401);
    abort_unless((bool) Auth::user()->hasRole('admin'), 403);
    abort_if((bool) Auth::user()->is_super_admin, 403, 'Super admin es de solo lectura; usa una cuenta de hospital para operar.');
});

$patient_suggestions = computed(function () {
    $q = trim((string) $this->patient_query);
    if ($q === '') {
        return [];
    }

    $normalizedQ = Str::ascii(Str::lower($q));

    return Patient::query()
        ->orderBy('primer_apellido')
        ->get(['id', 'primer_nombre', 'segundo_nombre', 'primer_apellido', 'segundo_apellido'])
        ->filter(fn ($p) => str_contains(Str::ascii(Str::lower($p->nombreCompleto())), $normalizedQ))
        ->take(8)
        ->map(fn ($p) => ['id' => $p->id, 'name' => $p->nombreCompleto()])
        ->values()
        ->all();
});

$selectPatient = function (int $id) {
    $p = Patient::find($id);
    if (! $p) {
        return;
    }
    $this->patient_id = $p->id;
    $this->patient_query = $p->nombreCompleto();
};

$save = function () {
    abort_unless(Auth::check(), 401);
    $data = $this->validate();

    Admission::create([
        'patient_id' => $data['patient_id'],
        'va_a_quirofano' => (bool) $data['va_a_quirofano'],
        'fecha_ingreso' => $data['fecha_ingreso'],
        'hora_ingreso' => $data['hora_ingreso'] ?: null,
        'sala_ingreso' => $data['sala_ingreso'] ?: null,
        'tiene_seguro' => (bool) $data['tiene_seguro'],
        'compania_seguros' => $data['compania_seguros'] ?: null,
        'poliza' => $data['poliza'] ?: null,
        'certificado' => $data['certificado'] ?: null,
        'impresion_clinica' => $data['impresion_clinica'] ?: null,
        'medico_responsable' => $data['medico_responsable'] ?: null,
    ]);

    $this->reset();
    $this->fecha_ingreso = now()->toDateString();
    $this->hora_ingreso = now()->format('H:i');
    $this->success_message = __('Ingreso registrado.');
};

?>

<div class="max-w-4xl mx-auto p-4 space-y-6">
    <flux:heading size="xl">{{ __('Nuevo Ingreso') }}</flux:heading>

    @if($success_message)
        <flux:callout variant="success" icon="check-circle" heading="{{ $success_message }}" />
    @endif

    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-6 space-y-6">
        <div class="space-y-2">
            <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('Paciente') }}</label>
            <input type="text" wire:model.live.debounce.200ms="patient_query"
                placeholder="{{ __('Buscar paciente...') }}"
                class="block w-full rounded-lg border-zinc-200 dark:border-zinc-800 bg-indigo-50 dark:bg-zinc-700/60 text-zinc-900 dark:text-zinc-100 focus:ring-0 focus:border-zinc-500 p-2.5" />
            @if(!empty($this->patient_suggestions))
                <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-700 divide-y dark:divide-zinc-600">
                    @foreach($this->patient_suggestions as $s)
                        <button type="button" class="block w-full text-left px-4 py-2 hover:bg-zinc-50 dark:hover:bg-zinc-600"
                            wire:click="selectPatient({{ $s['id'] }})">{{ $s['name'] }}</button>
                    @endforeach
                </div>
            @endif
            @error('patient_id') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <flux:input type="date" wire:model="fecha_ingreso" label="{{ __('Fecha de Ingreso') }}" />
            <flux:input type="time" wire:model="hora_ingreso" label="{{ __('Hora') }}" />
            <flux:input wire:model="sala_ingreso" label="{{ __('Sala') }}" />
        </div>

        <flux:checkbox wire:model="va_a_quirofano" label="{{ __('Va a quirófano') }}" />

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <flux:checkbox wire:model="tiene_seguro" label="{{ __('Tiene seguro') }}" />
            <flux:input wire:model="compania_seguros" label="{{ __('Compañía de Seguros') }}" />
            <flux:input wire:model="poliza" label="{{ __('Póliza') }}" />
            <flux:input wire:model="certificado" label="{{ __('Certificado') }}" />
        </div>

        <flux:textarea wire:model="impresion_clinica" label="{{ __('Impresión Clínica de Ingreso') }}" />
        <flux:input wire:model="medico_responsable" label="{{ __('Médico Responsable') }}" />

        <div class="pt-2 flex justify-end">
            <flux:button wire:click="save" variant="primary" class="w-full sm:w-auto">{{ __('Guardar ingreso') }}</flux:button>
        </div>
    </div>
</div>
