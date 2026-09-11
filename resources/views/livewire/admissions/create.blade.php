<?php

use App\Enums\AdmissionType;
use App\Models\Admission;
use App\Models\Patient;
use App\Support\AdmissionQr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

use function Livewire\Volt\{state, mount, computed, rules};

state([
    'currentStep' => 1,
    'tipoAtencion' => '',

    // Paciente
    'patientId' => null,
    'patientQuery' => '',
    'p_expediente_no' => '',
    'p_primer_apellido' => '',
    'p_segundo_apellido' => '',
    'p_primer_nombre' => '',
    'p_segundo_nombre' => '',
    'p_dpi' => '',
    'p_fecha_nacimiento' => '',
    'p_sexo' => '',
    'p_lugar_nacimiento' => '',
    'p_nacionalidad' => '',
    'p_estado_civil' => '',
    'p_direccion_habitual' => '',
    'p_calle_o_lugar' => '',
    'p_municipio' => '',
    'p_departamento' => '',
    'p_telefono' => '',
    'p_nombre_padre' => '',
    'p_nombre_madre' => '',
    'p_nombre_conyuge' => '',
    'p_contacto_emergencia' => '',

    // Ingreso
    'a_va_a_quirofano' => false,
    'a_fecha_ingreso' => now()->toDateString(),
    'a_hora_ingreso' => now()->format('H:i'),
    'a_tiene_seguro' => false,
    'a_tiene_igss' => false,
    'a_compania_seguros' => '',
    'a_poliza' => '',
    'a_certificado' => '',
    'a_impresion_clinica' => '',
    'a_sala_ingreso' => '',
    'a_referido_por' => '',
    'a_otras_hospitalizaciones' => '',
    'a_muestra_patologia' => false,
    'a_medico_responsable' => '',

    'savedAdmission' => null,
]);

mount(function () {
    abort_unless(Auth::check(), 401);
    abort_unless((bool) Auth::user()->hasRole('admin'), 403);
    abort_if((bool) Auth::user()->is_platform_admin, 403, 'Administrador de plataforma es de solo lectura; usa una cuenta de hospital para operar.');
});

$isEmergency = computed(fn () => $this->tipoAtencion === AdmissionType::EMERGENCIA->value);

$patientSuggestions = computed(function () {
    $q = trim((string) $this->patientQuery);
    if ($q === '' || $this->patientId) {
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

$tipoOptions = computed(fn () => AdmissionType::options());

$selectPatient = function (int $id) {
    $p = Patient::find($id);
    if (! $p) {
        return;
    }

    $this->patientId = $p->id;
    $this->patientQuery = $p->nombreCompleto();
    $this->p_expediente_no = $p->expediente_no ?? '';
    $this->p_primer_apellido = $p->primer_apellido ?? '';
    $this->p_segundo_apellido = $p->segundo_apellido ?? '';
    $this->p_primer_nombre = $p->primer_nombre ?? '';
    $this->p_segundo_nombre = $p->segundo_nombre ?? '';
    $this->p_dpi = $p->dpi ?? '';
    $this->p_fecha_nacimiento = $p->fecha_nacimiento?->format('Y-m-d') ?? '';
    $this->p_sexo = $p->sexo ?? '';
    $this->p_lugar_nacimiento = $p->lugar_nacimiento ?? '';
    $this->p_nacionalidad = $p->nacionalidad ?? '';
    $this->p_estado_civil = $p->estado_civil ?? '';
    $this->p_direccion_habitual = $p->direccion_habitual ?? '';
    $this->p_calle_o_lugar = $p->calle_o_lugar ?? '';
    $this->p_municipio = $p->municipio ?? '';
    $this->p_departamento = $p->departamento ?? '';
    $this->p_telefono = $p->telefono ?? '';
    $this->p_nombre_padre = $p->nombre_padre ?? '';
    $this->p_nombre_madre = $p->nombre_madre ?? '';
    $this->p_nombre_conyuge = $p->nombre_conyuge ?? '';
    $this->p_contacto_emergencia = $p->contacto_emergencia ?? '';
};

$clearPatient = function () {
    $this->patientId = null;
    $this->patientQuery = '';
    $this->p_expediente_no = '';
    $this->p_primer_apellido = '';
    $this->p_segundo_apellido = '';
    $this->p_primer_nombre = '';
    $this->p_segundo_nombre = '';
    $this->p_dpi = '';
    $this->p_fecha_nacimiento = '';
    $this->p_sexo = '';
    $this->p_lugar_nacimiento = '';
    $this->p_nacionalidad = '';
    $this->p_estado_civil = '';
    $this->p_direccion_habitual = '';
    $this->p_calle_o_lugar = '';
    $this->p_municipio = '';
    $this->p_departamento = '';
    $this->p_telefono = '';
    $this->p_nombre_padre = '';
    $this->p_nombre_madre = '';
    $this->p_nombre_conyuge = '';
    $this->p_contacto_emergencia = '';
};

$rules = fn () => match ($this->currentStep) {
    1 => [
        'tipoAtencion' => ['required', Rule::in(array_keys(AdmissionType::options()))],
    ],
    2 => [
        'patientQuery' => ['required_without:patientId'],
        'p_primer_apellido' => ['required_without:patientId', 'string', 'max:255'],
        'p_primer_nombre' => ['required_without:patientId', 'string', 'max:255'],
        'p_segundo_apellido' => ['nullable', 'string', 'max:255'],
        'p_segundo_nombre' => ['nullable', 'string', 'max:255'],
        'p_dpi' => $this->isEmergency ? ['nullable', 'string', 'max:20'] : ['nullable', 'string', 'max:20'],
        'p_fecha_nacimiento' => $this->isEmergency ? ['nullable', 'date'] : ['nullable', 'date'],
        'p_sexo' => $this->isEmergency ? ['nullable', 'in:M,F'] : ['nullable', 'in:M,F'],
        'p_lugar_nacimiento' => $this->isEmergency ? [] : ['nullable', 'string', 'max:255'],
        'p_nacionalidad' => $this->isEmergency ? [] : ['nullable', 'string', 'max:255'],
        'p_estado_civil' => $this->isEmergency ? [] : ['nullable', 'string', 'max:255'],
        'p_direccion_habitual' => $this->isEmergency ? [] : ['nullable', 'string', 'max:255'],
        'p_telefono' => $this->isEmergency ? [] : ['nullable', 'string', 'max:20'],
    ],
    3 => $this->isEmergency
        ? [
            'a_fecha_ingreso' => ['required', 'date'],
            'a_hora_ingreso' => ['nullable', 'date_format:H:i'],
            'a_impresion_clinica' => ['nullable', 'string'],
            'a_medico_responsable' => ['nullable', 'string', 'max:255'],
        ]
        : [
            'a_fecha_ingreso' => ['required', 'date'],
            'a_hora_ingreso' => ['nullable', 'date_format:H:i'],
            'a_sala_ingreso' => ['nullable', 'string', 'max:255'],
            'a_tiene_seguro' => ['boolean'],
            'a_tiene_igss' => ['boolean'],
            'a_compania_seguros' => ['nullable', 'required_if:a_tiene_seguro,true', 'string', 'max:255'],
            'a_poliza' => ['nullable', 'string', 'max:255'],
            'a_certificado' => ['nullable', 'string', 'max:255'],
            'a_impresion_clinica' => ['nullable', 'string'],
            'a_referido_por' => ['nullable', 'string', 'max:255'],
            'a_otras_hospitalizaciones' => ['nullable', 'string'],
            'a_muestra_patologia' => ['boolean'],
            'a_medico_responsable' => ['nullable', 'string', 'max:255'],
        ],
    default => [],
};

$nextStep = function () {
    $this->validate();

    if ($this->currentStep === 2 && $this->isEmergency) {
        $this->currentStep = 3;
        return;
    }

    $this->currentStep = min(4, $this->currentStep + 1);
};

$previousStep = function () {
    if ($this->currentStep === 3 && $this->isEmergency) {
        $this->currentStep = 2;
        return;
    }

    $this->currentStep = max(1, $this->currentStep - 1);
};

$setTipoAtencion = function (string $value) {
    $this->tipoAtencion = $value;
    $this->validateOnly('tipoAtencion');
    $this->currentStep = 2;
};

$save = function () {
    $this->validate();

    abort_unless(Auth::check(), 401);

    $admission = DB::transaction(function () {
        $hospitalId = Auth::user()->hospital_id;

        if ($this->patientId) {
            $patient = Patient::findOrFail($this->patientId);
        } else {
            $patient = Patient::create([
                'hospital_id' => $hospitalId,
                'expediente_no' => $this->p_expediente_no ?: null,
                'primer_apellido' => $this->p_primer_apellido,
                'segundo_apellido' => $this->p_segundo_apellido ?: null,
                'primer_nombre' => $this->p_primer_nombre,
                'segundo_nombre' => $this->p_segundo_nombre ?: null,
                'dpi' => $this->p_dpi ?: null,
                'fecha_nacimiento' => $this->p_fecha_nacimiento ?: null,
                'sexo' => $this->p_sexo ?: null,
                'lugar_nacimiento' => $this->p_lugar_nacimiento ?: null,
                'nacionalidad' => $this->p_nacionalidad ?: null,
                'estado_civil' => $this->p_estado_civil ?: null,
                'direccion_habitual' => $this->p_direccion_habitual ?: null,
                'calle_o_lugar' => $this->p_calle_o_lugar ?: null,
                'municipio' => $this->p_municipio ?: null,
                'departamento' => $this->p_departamento ?: null,
                'telefono' => $this->p_telefono ?: null,
                'nombre_padre' => $this->p_nombre_padre ?: null,
                'nombre_madre' => $this->p_nombre_madre ?: null,
                'nombre_conyuge' => $this->p_nombre_conyuge ?: null,
                'contacto_emergencia' => $this->p_contacto_emergencia ?: null,
            ]);
        }

        return Admission::create([
            'hospital_id' => $hospitalId,
            'patient_id' => $patient->id,
            'tipo_atencion' => $this->tipoAtencion,
            'va_a_quirofano' => $this->tipoAtencion === AdmissionType::EMERGENCIA->value ? false : (bool) $this->a_va_a_quirofano,
            'fecha_ingreso' => $this->a_fecha_ingreso,
            'hora_ingreso' => $this->a_hora_ingreso ?: null,
            'tiene_seguro' => (bool) $this->a_tiene_seguro,
            'tiene_igss' => (bool) $this->a_tiene_igss,
            'compania_seguros' => $this->a_compania_seguros ?: null,
            'poliza' => $this->a_poliza ?: null,
            'certificado' => $this->a_certificado ?: null,
            'impresion_clinica' => $this->a_impresion_clinica ?: null,
            'sala_ingreso' => $this->a_sala_ingreso ?: null,
            'referido_por' => $this->a_referido_por ?: null,
            'otras_hospitalizaciones' => $this->a_otras_hospitalizaciones ?: null,
            'muestra_patologia' => (bool) $this->a_muestra_patologia,
            'medico_responsable' => $this->a_medico_responsable ?: null,
            'qr_token' => AdmissionQr::generateToken(),
        ]);
    });

    $this->savedAdmission = $admission;
    $this->currentStep = 4;
};

?>

<div class="max-w-5xl mx-auto p-4 space-y-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">{{ __('Nuevo Ingreso') }}</flux:heading>
        <flux:link href="{{ route('patients.index') }}" class="text-sm">{{ __('Volver') }}</flux:link>
    </div>

    {{-- Indicador de pasos --}}
    <div class="flex items-center justify-between">
        @foreach ([1 => __('Tipo'), 2 => __('Paciente'), 3 => __('Episodio'), 4 => __('QR')] as $step => $label)
            <div class="flex flex-col items-center gap-2">
                <div @class([
                    'size-10 rounded-full flex items-center justify-center text-sm font-semibold',
                    'bg-accent text-white' => $currentStep >= $step,
                    'bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400' => $currentStep < $step,
                ])>
                    {{ $step }}
                </div>
                <span @class([
                    'text-xs font-medium',
                    'text-accent dark:text-accent' => $currentStep >= $step,
                    'text-zinc-500 dark:text-zinc-400' => $currentStep < $step,
                ])>{{ $label }}</span>
            </div>
        @endforeach
    </div>

    {{-- Paso 1: Tipo de atención --}}
    @if ($currentStep === 1)
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-6 space-y-6">
            <flux:heading size="lg">{{ __('¿Qué tipo de atención es?') }}</flux:heading>

            @error('tipoAtencion')
                <flux:callout variant="danger" icon="exclamation-triangle" :heading="$message" />
            @enderror

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach ($this->tipoOptions as $value => $label)
                    <button type="button" wire:click="setTipoAtencion('{{ $value }}')"
                        @class([
                            'relative flex flex-col items-start gap-3 rounded-xl border p-5 text-left transition-colors',
                            'border-accent bg-mist dark:bg-accent/10 dark:border-accent' => $tipoAtencion === $value,
                            'border-zinc-200 hover:border-zinc-300 dark:border-zinc-700 dark:hover:border-zinc-600' => $tipoAtencion !== $value,
                        ])>
                        <span class="text-lg font-semibold">{{ $label }}</span>
                        @if ($value === AdmissionType::EMERGENCIA->value)
                            <span class="text-xs text-urgent font-medium">{{ __('Solo datos esenciales') }}</span>
                        @endif
                    </button>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Paso 2: Paciente --}}
    @if ($currentStep === 2)
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-6 space-y-6">
            <div class="flex items-center justify-between">
                <flux:heading size="lg">{{ __('Datos del paciente') }}</flux:heading>
                @if ($patientId)
                    <flux:button variant="ghost" size="sm" wire:click="clearPatient">{{ __('Limpiar selección') }}</flux:button>
                @endif
            </div>

            <div class="space-y-2">
                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('Buscar paciente existente') }}</label>
                <input type="text" wire:model.live.debounce.200ms="patientQuery"
                    placeholder="{{ __('Nombre, apellido o DPI...') }}"
                    class="block w-full rounded-lg border-zinc-200 dark:border-zinc-800 bg-mist dark:bg-zinc-700/60 text-zinc-900 dark:text-zinc-100 focus:ring-0 focus:border-zinc-500 p-2.5"
                    @disabled($patientId) />

                @if(!empty($this->patientSuggestions) && !$patientId)
                    <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-700 divide-y dark:divide-zinc-600">
                        @foreach($this->patientSuggestions as $s)
                            <button type="button" class="block w-full text-left px-4 py-2 hover:bg-zinc-50 dark:hover:bg-zinc-600"
                                wire:click="selectPatient({{ $s['id'] }})">{{ $s['name'] }}</button>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <flux:input wire:model="p_expediente_no" label="{{ __('No. Expediente') }}" />
                <div></div>

                <flux:input wire:model="p_primer_apellido" label="{{ __('1er Apellido') }} *" />
                <flux:input wire:model="p_segundo_apellido" label="{{ __('2do Apellido') }}" />
                <flux:input wire:model="p_primer_nombre" label="{{ __('1er Nombre') }} *" />
                <flux:input wire:model="p_segundo_nombre" label="{{ __('2do Nombre') }}" />

                <flux:input wire:model="p_dpi" label="{{ __('DPI') }}" />
                <flux:input type="date" wire:model="p_fecha_nacimiento" label="{{ __('Fecha de nacimiento') }}" />

                <div>
                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">{{ __('Sexo') }}</label>
                    <select wire:model="p_sexo"
                        class="w-full rounded-lg border-zinc-200 dark:border-zinc-800 bg-mist dark:bg-zinc-700/60 text-zinc-900 dark:text-zinc-100 focus:ring-0 focus:border-zinc-500 p-2.5">
                        <option value="">—</option>
                        <option value="M">M</option>
                        <option value="F">F</option>
                    </select>
                </div>

                @if (!$isEmergency)
                    <flux:input wire:model="p_lugar_nacimiento" label="{{ __('Lugar de nacimiento') }}" />
                    <flux:input wire:model="p_nacionalidad" label="{{ __('Nacionalidad') }}" />

                    <div>
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">{{ __('Estado civil') }}</label>
                        <select wire:model="p_estado_civil"
                            class="w-full rounded-lg border-zinc-200 dark:border-zinc-800 bg-mist dark:bg-zinc-700/60 text-zinc-900 dark:text-zinc-100 focus:ring-0 focus:border-zinc-500 p-2.5">
                            <option value="">—</option>
                            <option value="S">Soltero(a)</option>
                            <option value="C">Casado(a)</option>
                            <option value="U">Unido(a)</option>
                            <option value="D">Divorciado(a)</option>
                            <option value="V">Viudo(a)</option>
                        </select>
                    </div>

                    <flux:input wire:model="p_direccion_habitual" label="{{ __('Dirección habitual') }}" />
                    <flux:input wire:model="p_telefono" label="{{ __('Teléfono') }}" />
                    <flux:input wire:model="p_calle_o_lugar" label="{{ __('Calle o lugar') }}" />
                    <flux:input wire:model="p_municipio" label="{{ __('Municipio') }}" />
                    <flux:input wire:model="p_departamento" label="{{ __('Departamento') }}" />
                    <flux:input wire:model="p_nombre_padre" label="{{ __('Nombre del padre') }}" />
                    <flux:input wire:model="p_nombre_madre" label="{{ __('Nombre de la madre') }}" />
                    <flux:input wire:model="p_nombre_conyuge" label="{{ __('Nombre del cónyuge') }}" />
                    <flux:input wire:model="p_contacto_emergencia" label="{{ __('En caso de emergencia llamar a') }}" />
                @endif
            </div>

            <div class="flex justify-between pt-4">
                <flux:button variant="ghost" wire:click="previousStep">{{ __('Anterior') }}</flux:button>
                <flux:button variant="primary" wire:click="nextStep">{{ __('Continuar') }}</flux:button>
            </div>
        </div>
    @endif

    {{-- Paso 3: Datos del episodio --}}
    @if ($currentStep === 3)
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-6 space-y-6">
            <flux:heading size="lg">{{ __('Datos del ingreso') }}</flux:heading>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <flux:input type="date" wire:model="a_fecha_ingreso" label="{{ __('Fecha de ingreso') }} *" />
                <flux:input type="time" wire:model="a_hora_ingreso" label="{{ __('Hora') }}" />
                <flux:input wire:model="a_sala_ingreso" label="{{ __('Sala / Cama') }}" />
            </div>

            @if (!$isEmergency)
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <flux:checkbox wire:model.live="a_tiene_seguro" label="{{ __('Tiene seguro') }}" />
                    <flux:checkbox wire:model.live="a_tiene_igss" label="{{ __('IGSS') }}" />
                </div>

                @if ($a_tiene_seguro)
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <flux:input wire:model="a_compania_seguros" label="{{ __('Compañía de seguros') }}" />
                        <flux:input wire:model="a_poliza" label="{{ __('Póliza') }}" />
                        <flux:input wire:model="a_certificado" label="{{ __('Certificado') }}" />
                    </div>
                @endif

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <flux:input wire:model="a_referido_por" label="{{ __('Referido por') }}" />
                    <flux:input wire:model="a_medico_responsable" label="{{ __('Médico responsable') }}" />
                </div>

                <flux:textarea wire:model="a_otras_hospitalizaciones" label="{{ __('Otras hospitalizaciones') }}" />
                <flux:checkbox wire:model="a_muestra_patologia" label="{{ __('Muestra para patología') }}" />
                <flux:checkbox wire:model="a_va_a_quirofano" label="{{ __('Va a quirófano') }}" />
            @else
                <flux:input wire:model="a_medico_responsable" label="{{ __('Médico responsable') }}" />
            @endif

            <flux:textarea wire:model="a_impresion_clinica" label="{{ __('Impresión clínica de ingreso') }}" />

            <div class="flex justify-between pt-4">
                <flux:button variant="ghost" wire:click="previousStep">{{ __('Anterior') }}</flux:button>
                <flux:button variant="primary" wire:click="save">{{ __('Guardar ingreso') }}</flux:button>
            </div>
        </div>
    @endif

    {{-- Paso 4: Confirmación + QR --}}
    @if ($currentStep === 4 && $savedAdmission)
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-6 space-y-6">
            <flux:callout variant="success" icon="check-circle" heading="{{ __('Ingreso registrado correctamente') }}" />

            <div class="flex flex-col items-center gap-4">
                <div class="p-4 bg-white rounded-xl border border-zinc-200 shadow-sm">
                    {!! App\Support\AdmissionQr::svg($savedAdmission, 240) !!}
                </div>

                <div class="text-center space-y-1">
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Paciente') }}</p>
                    <p class="text-lg font-semibold">{{ $savedAdmission->patient->nombreCompleto() }}</p>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Tipo de atención') }}: {{ App\Enums\AdmissionType::from($savedAdmission->tipo_atencion)->label() }}</p>
                </div>

                <div class="flex gap-3">
                    <flux:button href="{{ $savedAdmission->qrUrl() }}" target="_blank" variant="outline" icon="arrow-top-right-on-square">
                        {{ __('Ver expediente') }}
                    </flux:button>
                    <flux:button onclick="window.print()" variant="primary" icon="printer">
                        {{ __('Imprimir QR') }}
                    </flux:button>
                </div>
            </div>
        </div>
    @endif
</div>
