<?php

use App\Models\Patient;
use Illuminate\Support\Facades\Auth;

use function Livewire\Volt\{state, mount};

state(['patient' => null]);

mount(function (string $patient) {
    abort_unless(Auth::check(), 401);
    abort_unless((bool) (Auth::user()->hasRole('admin') || Auth::user()->is_platform_admin), 403);

    // Se busca por `slug` con withTrashed(): el binding implicito por defecto excluye
    // pacientes con soft delete y devolvia 404 al ver el detalle de uno ya eliminado.
    $this->patient = Patient::withTrashed()->where('slug', $patient)->firstOrFail();
});

$estadoCivilLabel = function (?string $codigo) {
    return match ($codigo) {
        'S' => __('Soltero(a)'),
        'C' => __('Casado(a)'),
        'U' => __('Unido(a)'),
        'D' => __('Divorciado(a)'),
        'V' => __('Viudo(a)'),
        default => '—',
    };
};

?>

<div class="max-w-3xl mx-auto p-4 space-y-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">{{ $patient->nombreCompleto() }}</flux:heading>
        <flux:link href="{{ route('patients.index') }}" class="text-sm">{{ __('Volver') }}</flux:link>
    </div>

    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <flux:label>{{ __('No. Expediente') }}</flux:label>
            <p class="text-sm text-zinc-900 dark:text-zinc-100">{{ $patient->expediente_no ?: '—' }}</p>
        </div>
        <div>
            <flux:label>{{ __('No. DPI') }}</flux:label>
            <p class="text-sm text-zinc-900 dark:text-zinc-100">{{ $patient->dpi ?: '—' }}</p>
        </div>
        <div>
            <flux:label>{{ __('Teléfono') }}</flux:label>
            <p class="text-sm text-zinc-900 dark:text-zinc-100">{{ $patient->telefono ?: '—' }}</p>
        </div>
        <div>
            <flux:label>{{ __('Fecha de Nacimiento') }}</flux:label>
            <p class="text-sm text-zinc-900 dark:text-zinc-100">{{ $patient->fecha_nacimiento?->format('Y-m-d') ?: '—' }}</p>
        </div>
        <div>
            <flux:label>{{ __('Sexo') }}</flux:label>
            <p class="text-sm text-zinc-900 dark:text-zinc-100">{{ $patient->sexo ?: '—' }}</p>
        </div>
        <div>
            <flux:label>{{ __('Estado Civil') }}</flux:label>
            <p class="text-sm text-zinc-900 dark:text-zinc-100">{{ $this->estadoCivilLabel($patient->estado_civil) }}</p>
        </div>
        <div>
            <flux:label>{{ __('Lugar de Nacimiento') }}</flux:label>
            <p class="text-sm text-zinc-900 dark:text-zinc-100">{{ $patient->lugar_nacimiento ?: '—' }}</p>
        </div>
        <div>
            <flux:label>{{ __('Nacionalidad') }}</flux:label>
            <p class="text-sm text-zinc-900 dark:text-zinc-100">{{ $patient->nacionalidad ?: '—' }}</p>
        </div>
        <div class="sm:col-span-2">
            <flux:label>{{ __('Dirección Habitual') }}</flux:label>
            <p class="text-sm text-zinc-900 dark:text-zinc-100">{{ $patient->direccion_habitual ?: '—' }}</p>
        </div>
        <div>
            <flux:label>{{ __('Calle o Lugar') }}</flux:label>
            <p class="text-sm text-zinc-900 dark:text-zinc-100">{{ $patient->calle_o_lugar ?: '—' }}</p>
        </div>
        <div>
            <flux:label>{{ __('Municipio') }}</flux:label>
            <p class="text-sm text-zinc-900 dark:text-zinc-100">{{ $patient->municipio ?: '—' }}</p>
        </div>
        <div>
            <flux:label>{{ __('Departamento') }}</flux:label>
            <p class="text-sm text-zinc-900 dark:text-zinc-100">{{ $patient->departamento ?: '—' }}</p>
        </div>
        <div>
            <flux:label>{{ __('Nombre del Padre') }}</flux:label>
            <p class="text-sm text-zinc-900 dark:text-zinc-100">{{ $patient->nombre_padre ?: '—' }}</p>
        </div>
        <div>
            <flux:label>{{ __('Nombre de la Madre') }}</flux:label>
            <p class="text-sm text-zinc-900 dark:text-zinc-100">{{ $patient->nombre_madre ?: '—' }}</p>
        </div>
        <div>
            <flux:label>{{ __('Nombre del Cónyuge') }}</flux:label>
            <p class="text-sm text-zinc-900 dark:text-zinc-100">{{ $patient->nombre_conyuge ?: '—' }}</p>
        </div>
        <div>
            <flux:label>{{ __('Contacto de Emergencia') }}</flux:label>
            <p class="text-sm text-zinc-900 dark:text-zinc-100">{{ $patient->contacto_emergencia ?: '—' }}</p>
        </div>
    </div>
</div>
