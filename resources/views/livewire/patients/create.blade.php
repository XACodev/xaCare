<?php

use App\Models\Patient;
use Illuminate\Support\Facades\Auth;

use function Livewire\Volt\{state, mount, rules};

state([
    'primer_apellido' => '',
    'segundo_apellido' => '',
    'primer_nombre' => '',
    'segundo_nombre' => '',
    'dpi' => '',
    'fecha_nacimiento' => '',
    'sexo' => '',
    'telefono' => '',
    'expediente_no' => '',
    'success_message' => null,
]);

mount(function () {
    abort_unless(Auth::check(), 401);
    abort_unless((bool) Auth::user()->hasRole('admin'), 403);
    abort_if((bool) Auth::user()->is_super_admin, 403, 'Administrador de plataforma es de solo lectura; usa una cuenta de hospital para operar.');
});

rules([
    'primer_apellido' => ['required', 'string', 'max:255'],
    'primer_nombre' => ['required', 'string', 'max:255'],
    'segundo_apellido' => ['nullable', 'string', 'max:255'],
    'segundo_nombre' => ['nullable', 'string', 'max:255'],
    'dpi' => ['nullable', 'string', 'max:20'],
    'fecha_nacimiento' => ['nullable', 'date'],
    'sexo' => ['nullable', 'in:M,F'],
    'telefono' => ['nullable', 'string', 'max:20'],
    'expediente_no' => ['nullable', 'string', 'max:50'],
]);

$save = function () {
    abort_unless(Auth::check(), 401);
    $data = $this->validate();

    // Los inputs vacíos se guardan como null (evita colisiones de unique y casts de fecha).
    $data = array_map(fn ($v) => $v === '' ? null : $v, $data);

    Patient::create($data);

    $this->reset();
    $this->success_message = __('Paciente registrado.');
};

?>

<div class="max-w-4xl mx-auto p-4 space-y-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">{{ __('Nuevo Paciente') }}</flux:heading>
        <flux:link href="{{ route('patients.index') }}" class="text-sm">{{ __('Volver') }}</flux:link>
    </div>

    @if($success_message)
        <flux:callout variant="success" icon="check-circle" heading="{{ $success_message }}" />
    @endif

    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <flux:input wire:model="primer_apellido" label="{{ __('1er Apellido') }}" />
            <flux:input wire:model="segundo_apellido" label="{{ __('2do Apellido') }}" />
            <flux:input wire:model="primer_nombre" label="{{ __('1er Nombre') }}" />
            <flux:input wire:model="segundo_nombre" label="{{ __('2do Nombre') }}" />
            <flux:input wire:model="dpi" label="{{ __('No. DPI') }}" />
            <flux:input type="date" wire:model="fecha_nacimiento" label="{{ __('Fecha de Nacimiento') }}" />

            <div>
                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">{{ __('Sexo') }}</label>
                <select wire:model="sexo"
                    class="w-full rounded-lg border-zinc-200 dark:border-zinc-800 bg-indigo-50 dark:bg-zinc-700/60 text-zinc-900 dark:text-zinc-100 focus:ring-0 focus:border-zinc-500 p-2.5">
                    <option value="">—</option>
                    <option value="M">M</option>
                    <option value="F">F</option>
                </select>
            </div>

            <flux:input wire:model="telefono" label="{{ __('Teléfono') }}" />
            <flux:input wire:model="expediente_no" label="{{ __('No. Expediente') }}" />
        </div>

        <div class="pt-6 flex justify-end">
            <flux:button wire:click="save" variant="primary" class="w-full sm:w-auto">{{ __('Guardar') }}</flux:button>
        </div>
    </div>
</div>
