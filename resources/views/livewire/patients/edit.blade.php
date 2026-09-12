<?php

use App\Models\Patient;
use Illuminate\Support\Facades\Auth;

use function Livewire\Volt\{state, mount, rules};

state([
    'patient' => null,
    'expediente_no' => '',
    'primer_apellido' => '',
    'segundo_apellido' => '',
    'primer_nombre' => '',
    'segundo_nombre' => '',
    'dpi' => '',
    'fecha_nacimiento' => '',
    'sexo' => '',
    'lugar_nacimiento' => '',
    'es_extranjero' => false,
    'nacionalidad' => 'Guatemalteco/a',
    'estado_civil' => '',
    'direccion_habitual' => '',
    'calle_o_lugar' => '',
    'municipio' => '',
    'departamento' => '',
    'telefono' => '',
    'nombre_padre' => '',
    'nombre_madre' => '',
    'nombre_conyuge' => '',
    'contacto_emergencia' => '',
    'saved' => false,
]);

mount(function (string $patient) {
    abort_unless(Auth::check(), 401);
    abort_unless((bool) Auth::user()->hasRole('admin'), 403);
    abort_if((bool) Auth::user()->is_platform_admin, 403, 'Administrador de plataforma es de solo lectura; usa una cuenta de hospital para operar.');

    $patient = Patient::where('slug', $patient)->firstOrFail();
    $this->patient = $patient;
    $this->expediente_no = $patient->expediente_no ?? '';
    $this->primer_apellido = $patient->primer_apellido ?? '';
    $this->segundo_apellido = $patient->segundo_apellido ?? '';
    $this->primer_nombre = $patient->primer_nombre ?? '';
    $this->segundo_nombre = $patient->segundo_nombre ?? '';
    $this->dpi = $patient->dpi ?? '';
    $this->fecha_nacimiento = $patient->fecha_nacimiento?->format('Y-m-d') ?? '';
    $this->sexo = $patient->sexo ?? '';
    $this->lugar_nacimiento = $patient->lugar_nacimiento ?? '';
    $this->es_extranjero = $patient->nacionalidad !== null && $patient->nacionalidad !== 'Guatemalteco/a' && $patient->nacionalidad !== '';
    $this->nacionalidad = $patient->nacionalidad ?: 'Guatemalteco/a';
    $this->estado_civil = $patient->estado_civil ?? '';
    $this->direccion_habitual = $patient->direccion_habitual ?? '';
    $this->calle_o_lugar = $patient->calle_o_lugar ?? '';
    $this->municipio = $patient->municipio ?? '';
    $this->departamento = $patient->departamento ?? '';
    $this->telefono = $patient->telefono ?? '';
    $this->nombre_padre = $patient->nombre_padre ?? '';
    $this->nombre_madre = $patient->nombre_madre ?? '';
    $this->nombre_conyuge = $patient->nombre_conyuge ?? '';
    $this->contacto_emergencia = $patient->contacto_emergencia ?? '';
});

rules([
    'expediente_no' => ['nullable', 'string', 'max:50'],
    'primer_apellido' => ['required', 'string', 'max:255'],
    'segundo_apellido' => ['nullable', 'string', 'max:255'],
    'primer_nombre' => ['required', 'string', 'max:255'],
    'segundo_nombre' => ['nullable', 'string', 'max:255'],
    'dpi' => ['nullable', 'string', 'max:20'],
    'fecha_nacimiento' => ['nullable', 'date'],
    'sexo' => ['nullable', 'in:M,F'],
    'lugar_nacimiento' => ['nullable', 'string', 'max:255'],
    'nacionalidad' => ['nullable', 'string', 'max:255'],
    'estado_civil' => ['nullable', 'string', 'max:255'],
    'direccion_habitual' => ['nullable', 'string', 'max:255'],
    'calle_o_lugar' => ['nullable', 'string', 'max:255'],
    'municipio' => ['nullable', 'string', 'max:255'],
    'departamento' => ['nullable', 'string', 'max:255'],
    'telefono' => ['nullable', 'string', 'max:20'],
    'nombre_padre' => ['nullable', 'string', 'max:255'],
    'nombre_madre' => ['nullable', 'string', 'max:255'],
    'nombre_conyuge' => ['nullable', 'string', 'max:255'],
    'contacto_emergencia' => ['nullable', 'string', 'max:255'],
]);

$save = function () {
    $data = $this->validate();

    $data['nacionalidad'] = $this->es_extranjero ? ($this->nacionalidad ?: null) : 'Guatemalteco/a';

    $this->patient->update([
        'expediente_no' => $data['expediente_no'] ?: null,
        'primer_apellido' => $data['primer_apellido'],
        'segundo_apellido' => $data['segundo_apellido'] ?: null,
        'primer_nombre' => $data['primer_nombre'],
        'segundo_nombre' => $data['segundo_nombre'] ?: null,
        'dpi' => $data['dpi'] ?: null,
        'fecha_nacimiento' => $data['fecha_nacimiento'] ?: null,
        'sexo' => $data['sexo'] ?: null,
        'lugar_nacimiento' => $data['lugar_nacimiento'] ?: null,
        'nacionalidad' => $data['nacionalidad'],
        'estado_civil' => $data['estado_civil'] ?: null,
        'direccion_habitual' => $data['direccion_habitual'] ?: null,
        'calle_o_lugar' => $data['calle_o_lugar'] ?: null,
        'municipio' => $data['municipio'] ?: null,
        'departamento' => $data['departamento'] ?: null,
        'telefono' => $data['telefono'] ?: null,
        'nombre_padre' => $data['nombre_padre'] ?: null,
        'nombre_madre' => $data['nombre_madre'] ?: null,
        'nombre_conyuge' => $data['nombre_conyuge'] ?: null,
        'contacto_emergencia' => $data['contacto_emergencia'] ?: null,
    ]);

    $this->saved = true;
};

?>

<div class="max-w-5xl mx-auto p-4 space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Editar paciente') }}</flux:heading>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ $patient?->nombreCompleto() }}</p>
        </div>
        <flux:link href="{{ route('patients.index') }}" class="text-sm">{{ __('Volver') }}</flux:link>
    </div>

    @if ($saved)
        <flux:callout variant="success" icon="check-circle" heading="{{ __('Paciente actualizado correctamente') }}" />
    @endif

    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-6 space-y-6">
        <flux:heading size="lg">{{ __('Datos personales') }}</flux:heading>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <flux:input wire:model="primer_apellido" label="{{ __('1er. apellido') }} *" />
            <flux:input wire:model="segundo_apellido" label="{{ __('2do. apellido') }}" />
            <flux:input wire:model="primer_nombre" label="{{ __('1er. nombre') }} *" />
            <flux:input wire:model="segundo_nombre" label="{{ __('2do. nombre') }}" />
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <flux:input type="date" wire:model="fecha_nacimiento" label="{{ __('Fecha de nacimiento') }}" />
            <flux:input wire:model="expediente_no" label="{{ __('No. expediente') }}" />
            <flux:input wire:model="dpi" label="{{ __('DPI / CUI') }}" />
            <div>
                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">{{ __('Sexo') }}</label>
                <div class="grid grid-cols-2 rounded-lg overflow-hidden border border-zinc-200 dark:border-zinc-700 h-11">
                    <button type="button" wire:click="$set('sexo', 'M')"
                        class="text-sm font-medium {{ $sexo === 'M' ? 'bg-mist text-accent-content dark:bg-accent/20 dark:text-accent' : 'bg-white dark:bg-zinc-900 text-zinc-500' }}">M</button>
                    <button type="button" wire:click="$set('sexo', 'F')"
                        class="text-sm font-medium {{ $sexo === 'F' ? 'bg-mist text-accent-content dark:bg-accent/20 dark:text-accent' : 'bg-white dark:bg-zinc-900 text-zinc-500' }}">F</button>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <flux:input wire:model="lugar_nacimiento" label="{{ __('Lugar de nacimiento') }}" />
            <div>
                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">{{ __('Nacionalidad') }}</label>
                <div class="flex items-center gap-3 h-11">
                    <button type="button" wire:click="$set('es_extranjero', false); $set('nacionalidad', 'Guatemalteco/a')"
                        class="px-3 h-9 rounded-lg text-sm border {{ ! $es_extranjero ? 'bg-mist border-accent text-accent-content dark:bg-accent/20 dark:text-accent font-semibold' : 'bg-white dark:bg-zinc-900 border-zinc-200 dark:border-zinc-700 text-zinc-600 dark:text-zinc-400' }}">
                        {{ __('Guatemalteco/a') }}
                    </button>
                    <button type="button" wire:click="$set('es_extranjero', true); $set('nacionalidad', '')"
                        class="px-3 h-9 rounded-lg text-sm border {{ $es_extranjero ? 'bg-mist border-accent text-accent-content dark:bg-accent/20 dark:text-accent font-semibold' : 'bg-white dark:bg-zinc-900 border-zinc-200 dark:border-zinc-700 text-zinc-600 dark:text-zinc-400' }}">
                        {{ __('Extranjero/a') }}
                    </button>
                </div>
            </div>
            <flux:input wire:model="nacionalidad" label="{{ __('Especificar nacionalidad') }}" :disabled="! $es_extranjero" />
        </div>

        <div>
            <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">{{ __('Estado civil') }}</label>
            <div class="flex flex-wrap gap-2">
                @foreach (['S' => 'Soltero/a', 'C' => 'Casado/a', 'U' => 'Unido/a', 'D' => 'Divorciado/a', 'V' => 'Viudo/a'] as $value => $label)
                    <button type="button" wire:click="$set('estado_civil', '{{ $value }}')"
                        class="px-3 h-9 rounded-lg text-sm border {{ $estado_civil === $value ? 'bg-mist border-accent text-accent-content dark:bg-accent/20 dark:text-accent font-semibold' : 'bg-white dark:bg-zinc-900 border-zinc-200 dark:border-zinc-700 text-zinc-600 dark:text-zinc-400' }}">
                        {{ $label }}
                    </button>
                @endforeach
            </div>
        </div>

        <flux:heading size="lg" class="pt-4">{{ __('Contacto') }}</flux:heading>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <flux:input wire:model="direccion_habitual" label="{{ __('Dirección habitual') }}" />
            <flux:input wire:model="telefono" label="{{ __('Teléfono') }}" />
            <flux:input wire:model="calle_o_lugar" label="{{ __('Calle o lugar') }}" />
            <flux:input wire:model="contacto_emergencia" label="{{ __('En caso de emergencia llamar a') }}" />
            <flux:input wire:model="municipio" label="{{ __('Municipio') }}" />
            <flux:input wire:model="departamento" label="{{ __('Departamento') }}" />
        </div>

        <flux:heading size="lg" class="pt-4">{{ __('Familiares') }}</flux:heading>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <flux:input wire:model="nombre_padre" label="{{ __('Nombre del padre') }}" />
            <flux:input wire:model="nombre_madre" label="{{ __('Nombre de la madre') }}" />
            <flux:input wire:model="nombre_conyuge" label="{{ __('Nombre del cónyuge') }}" />
        </div>

        <div class="flex justify-end pt-4">
            <flux:button wire:click="save" variant="primary">{{ __('Guardar cambios') }}</flux:button>
        </div>
    </div>
</div>
