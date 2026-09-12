<?php

use App\Enums\AdmissionType;
use App\Models\Admission;
use App\Models\HospitalRoom;
use App\Models\HospitalWard;
use App\Models\Patient;
use App\Models\User;
use App\Support\AdmissionQr;
use App\Support\PatientAge;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

use function Livewire\Volt\{state, mount, computed, rules};

state([
    'isRapidMode' => false,
    'currentStep' => 1,

    // Paciente
    'patientId' => null,
    'patientQuery' => '',
    'p_expediente_no' => '',
    'p_primer_apellido' => '',
    'p_segundo_apellido' => '',
    'p_primer_nombre' => '',
    'p_segundo_nombre' => '',
    'p_es_extranjero' => false,
    'p_id_country' => 'GT',
    'p_id_type' => 'CUI / DPI',
    'p_dpi' => '',
    'p_fecha_nacimiento' => '',
    'p_sexo' => '',
    'p_nacionalidad' => 'Guatemalteco/a',
    'p_departamento' => '',
    'p_municipio' => '',
    'p_pais_nacimiento' => '',
    'p_estado_nacimiento' => '',
    'p_ciudad_nacimiento' => '',
    'p_estado_civil' => '',
    'p_es_recien_nacido' => false,
    'p_madre_paciente_id' => null,
    'p_madre_query' => '',
    'p_direccion_habitual' => '',
    'p_telefono' => '',
    'p_telefono_casa' => '',
    'p_nombre_padre' => '',
    'p_nombre_madre' => '',
    'p_nombre_conyuge' => '',
    'p_emergency_contacts' => [['nombre' => '', 'telefono' => '', 'municipio' => '', 'departamento' => '']],

    // Ingreso
    'a_tipo_atencion' => 'hospitalizacion',
    'a_va_a_quirofano' => false,
    'a_fecha_ingreso' => now()->toDateString(),
    'a_hora_ingreso' => now()->format('H:i'),
    'a_sala_ingreso' => '',
    'a_habitacion' => '',
    'a_tiene_seguro' => false,
    'a_tiene_igss' => false,
    'a_compania_seguros' => '',
    'a_poliza' => '',
    'a_certificado' => '',
    'a_referido_por' => '',
    'a_otras_hospitalizaciones' => '',
    'a_medico_responsable' => '',

    // Maternidad
    'a_maternidad_no_hijo' => '',
    'a_maternidad_fecha_nacimiento' => '',
    'a_maternidad_hora' => '',
    'a_maternidad_sexo' => '',
    'a_maternidad_condiciones_egreso' => '',

    'savedAdmission' => null,
    'lastAdmissionPatientId' => null,
]);

mount(function () {
    abort_unless(Auth::check(), 401);
    abort_unless((bool) Auth::user()->hasRole('admin'), 403);
    abort_if((bool) Auth::user()->is_platform_admin, 403, 'Administrador de plataforma es de solo lectura; usa una cuenta de hospital para operar.');
});

$patientSuggestions = computed(function () {
    $q = trim((string) $this->patientQuery);
    if ($q === '' || $this->patientId) {
        return [];
    }

    $normalizedQ = Str::ascii(Str::lower($q));

    return Patient::query()
        ->orderBy('primer_apellido')
        ->get(['id', 'primer_nombre', 'segundo_nombre', 'primer_apellido', 'segundo_apellido', 'dpi', 'fecha_nacimiento', 'sexo'])
        ->filter(fn ($p) => str_contains(Str::ascii(Str::lower($p->nombreCompleto())), $normalizedQ)
            || str_contains(Str::ascii(Str::lower((string) $p->dpi)), $normalizedQ))
        ->take(8)
        ->map(fn ($p) => [
            'id' => $p->id,
            'name' => $p->nombreCompleto(),
            'dpi' => $p->dpi,
            'sexo' => $p->sexo,
            'edad' => $p->fecha_nacimiento ? PatientAge::from($p->fecha_nacimiento)->formatted() : null,
        ])
        ->values()
        ->all();
});

$edadCalculada = computed(function () {
    if (! $this->p_fecha_nacimiento) {
        return null;
    }

    return PatientAge::from(\Carbon\Carbon::parse($this->p_fecha_nacimiento));
});

$categoriaPaciente = computed(function () {
    $age = $this->edadCalculada;

    if (! $age) {
        return null;
    }

    $hospital = \App\Models\Hospital::find(Auth::user()->hospital_id);

    return $hospital?->patientCategories()
        ->where('code', $age->category()->value)
        ->where('active', true)
        ->first()
        ?->name ?? $age->category()->label();
});

$tipoOptions = computed(fn () => AdmissionType::options());

$paises = computed(fn () => config('locations.countries', []));

$departamentos = computed(fn () => array_keys(config('locations.guatemala', [])));

$municipiosDelDepartamento = computed(function () {
    if (! $this->p_departamento) {
        return [];
    }

    return config("locations.guatemala.{$this->p_departamento}", []);
});

$tiposDocumento = computed(function () {
    return config("locations.documents.{$this->p_id_country}", [
        ['type' => 'ID nacional', 'min' => 4, 'max' => 30],
        ['type' => 'Pasaporte', 'min' => 4, 'max' => 30],
    ]);
});

$documentoValidacion = computed(function () {
    foreach ($this->tiposDocumento as $doc) {
        if ($doc['type'] === $this->p_id_type) {
            return $doc;
        }
    }

    return ['min' => 4, 'max' => 30];
});

$madreSuggestions = computed(function () {
    $q = trim((string) $this->p_madre_query);
    if ($q === '' || ! $this->p_es_recien_nacido) {
        return [];
    }

    $normalizedQ = Str::ascii(Str::lower($q));

    return Patient::query()
        ->where('sexo', 'F')
        ->where('hospital_id', Auth::user()->hospital_id)
        ->orderBy('primer_apellido')
        ->get(['id', 'primer_nombre', 'segundo_nombre', 'primer_apellido', 'segundo_apellido', 'dpi'])
        ->filter(fn ($p) => str_contains(Str::ascii(Str::lower($p->nombreCompleto())), $normalizedQ)
            || str_contains(Str::ascii(Str::lower((string) $p->dpi)), $normalizedQ))
        ->take(6)
        ->map(fn ($p) => [
            'id' => $p->id,
            'name' => $p->nombreCompleto(),
            'dpi' => $p->dpi,
        ])
        ->values()
        ->all();
});

// Sala/Habitacion: catalogos por hospital (Configuracion > Salas / Habitaciones), no
// obligatorios -- si el hospital aun no cargo el catalogo o se necesita algo puntual que
// no esta en la lista, el campo sigue siendo texto libre.
$salaSuggestions = computed(function () {
    $q = trim((string) $this->a_sala_ingreso);
    if ($q === '') {
        return [];
    }
    $normalizedQ = Str::ascii(Str::lower($q));

    return HospitalWard::query()->where('active', true)->orderBy('sort_order')->get(['id', 'name'])
        ->filter(fn ($w) => str_contains(Str::ascii(Str::lower($w->name)), $normalizedQ) && Str::lower($w->name) !== Str::lower($q))
        ->take(6)->values()->all();
});

$habitacionSuggestions = computed(function () {
    $q = trim((string) $this->a_habitacion);
    if ($q === '') {
        return [];
    }
    $normalizedQ = Str::ascii(Str::lower($q));

    return HospitalRoom::query()->where('active', true)->orderBy('sort_order')->get(['id', 'name'])
        ->filter(fn ($r) => str_contains(Str::ascii(Str::lower($r->name)), $normalizedQ) && Str::lower($r->name) !== Str::lower($q))
        ->take(6)->values()->all();
});

// Medico responsable: busca en el staff que el hospital ya marco como "aparece en
// buscadores" (mismo permiso search.appear_as_suggestion que usan procedimientos/cirugias
// para elegir personal). Si no aparece nadie, se escribe el nombre libre igual.
$medicoSuggestions = computed(function () {
    $q = trim((string) $this->a_medico_responsable);
    if ($q === '') {
        return [];
    }
    $normalizedQ = Str::ascii(Str::lower($q));

    return User::query()->appearsAsSuggestion()->get(['id', 'name'])
        ->filter(fn ($u) => str_contains(Str::ascii(Str::lower($u->name)), $normalizedQ) && Str::lower($u->name) !== Str::lower($q))
        ->take(6)->values()->all();
});

$stepLabels = computed(fn () => [
    1 => ['title' => 'Identificación', 'subtitle' => 'Busca o registra al paciente'],
    2 => ['title' => 'Datos personales', 'subtitle' => 'Nacionalidad, nombres, documento'],
    3 => ['title' => 'Contactos y seguro', 'subtitle' => 'Dirección, emergencia, seguro'],
    4 => ['title' => 'Ingreso clínico', 'subtitle' => 'Tipo de atención, sala, médico'],
]);

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
    $this->p_es_extranjero = $p->nacionalidad !== null && $p->nacionalidad !== 'Guatemalteco/a' && $p->nacionalidad !== '';
    $this->p_id_country = $p->id_country ?? ($this->p_es_extranjero ? 'OTHER' : 'GT');
    $this->p_id_type = $p->id_type ?? ($this->p_es_extranjero ? 'Pasaporte' : 'CUI / DPI');
    $this->p_dpi = $p->dpi ?? '';
    $this->p_fecha_nacimiento = $p->fecha_nacimiento?->format('Y-m-d') ?? '';
    $this->p_sexo = $p->sexo ?? '';
    $this->p_nacionalidad = $p->nacionalidad ?: 'Guatemalteco/a';
    $this->p_departamento = $p->departamento ?? '';
    $this->p_municipio = $p->municipio ?? '';
    $this->p_estado_civil = $p->estado_civil ?? '';
    $this->p_es_recien_nacido = (bool) $p->es_recien_nacido;
    $this->p_madre_paciente_id = $p->madre_paciente_id;
    $this->p_madre_query = $p->madrePaciente?->nombreCompleto() ?? '';
    $this->p_direccion_habitual = $p->direccion_habitual ?? '';
    $this->p_telefono = $p->telefono ?? '';
    $this->p_telefono_casa = $p->telefono_casa ?? '';
    $this->p_nombre_padre = $p->nombre_padre ?? '';
    $this->p_nombre_madre = $p->nombre_madre ?? '';
    $this->p_nombre_conyuge = $p->nombre_conyuge ?? '';
    $this->p_emergency_contacts = is_array($p->emergency_contacts) && count($p->emergency_contacts) > 0
        ? $p->emergency_contacts
        : [['nombre' => $p->contacto_emergencia ?? '', 'telefono' => '', 'municipio' => '', 'departamento' => '']];

    // Permitir revisar/editar los datos personales antes del ingreso clínico.
    $this->sugerirEstadoCivil();
    $this->currentStep = 2;
};

$clearPatient = function () {
    $this->patientId = null;
    $this->patientQuery = '';
    $this->p_expediente_no = '';
    $this->p_primer_apellido = '';
    $this->p_segundo_apellido = '';
    $this->p_primer_nombre = '';
    $this->p_segundo_nombre = '';
    $this->p_es_extranjero = false;
    $this->p_id_country = 'GT';
    $this->p_id_type = 'CUI / DPI';
    $this->p_dpi = '';
    $this->p_fecha_nacimiento = '';
    $this->p_sexo = '';
    $this->p_nacionalidad = 'Guatemalteco/a';
    $this->p_departamento = '';
    $this->p_municipio = '';
    $this->p_pais_nacimiento = '';
    $this->p_estado_nacimiento = '';
    $this->p_ciudad_nacimiento = '';
    $this->p_estado_civil = '';
    $this->p_es_recien_nacido = false;
    $this->p_madre_paciente_id = null;
    $this->p_madre_query = '';
    $this->p_direccion_habitual = '';
    $this->p_telefono = '';
    $this->p_telefono_casa = '';
    $this->p_nombre_padre = '';
    $this->p_nombre_madre = '';
    $this->p_nombre_conyuge = '';
    $this->p_emergency_contacts = [['nombre' => '', 'telefono' => '', 'municipio' => '', 'departamento' => '']];
    $this->currentStep = 1;
};

$newPatient = function () {
    $this->clearPatient();
    $this->currentStep = 2;
};

$selectMadre = function (int $id, string $name) {
    $this->p_madre_paciente_id = $id;
    $this->p_madre_query = $name;
};

$clearMadre = function () {
    $this->p_madre_paciente_id = null;
    $this->p_madre_query = '';
};

$addEmergencyContact = function () {
    $this->p_emergency_contacts[] = ['nombre' => '', 'telefono' => '', 'municipio' => '', 'departamento' => ''];
};

$removeEmergencyContact = function (int $index) {
    if (count($this->p_emergency_contacts) <= 1) {
        $this->p_emergency_contacts = [['nombre' => '', 'telefono' => '', 'municipio' => '', 'departamento' => '']];
        return;
    }

    unset($this->p_emergency_contacts[$index]);
    $this->p_emergency_contacts = array_values($this->p_emergency_contacts);
};

$setNow = function () {
    $this->a_fecha_ingreso = now()->toDateString();
    $this->a_hora_ingreso = now()->format('H:i');
};

$sugerirEstadoCivil = function () {
    $age = $this->edadCalculada;
    if ($age && $age->isMinor() && empty($this->p_estado_civil)) {
        $this->p_estado_civil = 'S';
    }
};

$rules = function () {
    $docMin = $this->documentoValidacion['min'] ?? 4;
    $docMax = $this->documentoValidacion['max'] ?? 30;

    if ($this->isRapidMode) {
        return [
            'p_primer_apellido' => ['required', 'string', 'max:255'],
            'p_primer_nombre' => ['required', 'string', 'max:255'],
            'p_segundo_apellido' => ['nullable', 'string', 'max:255'],
            'p_segundo_nombre' => ['nullable', 'string', 'max:255'],
            'p_sexo' => ['nullable', 'in:M,F'],
            'a_fecha_ingreso' => ['required', 'date'],
            'a_hora_ingreso' => ['nullable', 'date_format:H:i'],
            'a_sala_ingreso' => ['nullable', 'string', 'max:255'],
            'a_habitacion' => ['nullable', 'string', 'max:255'],
        ];
    }

    return match ($this->currentStep) {
        1 => [
            'patientQuery' => ['required_without:patientId'],
        ],
        2 => [
            'p_primer_apellido' => $this->p_es_recien_nacido ? ['nullable', 'string', 'max:255'] : ['required', 'string', 'max:255'],
            'p_primer_nombre' => $this->p_es_recien_nacido ? ['nullable', 'string', 'max:255'] : ['required', 'string', 'max:255'],
            'p_segundo_apellido' => ['nullable', 'string', 'max:255'],
            'p_segundo_nombre' => ['nullable', 'string', 'max:255'],
            'p_es_extranjero' => ['boolean'],
            'p_id_country' => ['nullable', 'string', 'max:10'],
            'p_id_type' => ['nullable', 'string', 'max:50'],
            'p_dpi' => ['nullable', 'string', "min:{$docMin}", "max:{$docMax}"],
            'p_fecha_nacimiento' => ['nullable', 'date'],
            'p_sexo' => ['nullable', 'in:M,F'],
            'p_nacionalidad' => ['nullable', 'string', 'max:255'],
            'p_departamento' => ['nullable', 'string', 'max:255'],
            'p_municipio' => ['nullable', 'string', 'max:255'],
            'p_pais_nacimiento' => ['nullable', 'string', 'max:255'],
            'p_estado_nacimiento' => ['nullable', 'string', 'max:255'],
            'p_ciudad_nacimiento' => ['nullable', 'string', 'max:255'],
            'p_estado_civil' => ['nullable', 'string', 'max:255'],
            'p_es_recien_nacido' => ['boolean'],
            'p_madre_paciente_id' => ['nullable', 'integer', 'exists:patients,id'],
            'p_nombre_padre' => ['nullable', 'string', 'max:255'],
            'p_nombre_madre' => ['nullable', 'string', 'max:255'],
            'p_nombre_conyuge' => ['nullable', 'string', 'max:255'],
        ],
        3 => [
            'p_direccion_habitual' => ['nullable', 'string', 'max:255'],
            'p_telefono' => ['nullable', 'string', 'max:20'],
            'p_telefono_casa' => ['nullable', 'string', 'max:20'],
            'p_emergency_contacts' => ['nullable', 'array'],
            'p_emergency_contacts.*.nombre' => ['nullable', 'string', 'max:255'],
            'p_emergency_contacts.*.telefono' => ['nullable', 'string', 'max:20'],
            'p_emergency_contacts.*.municipio' => ['nullable', 'string', 'max:255'],
            'p_emergency_contacts.*.departamento' => ['nullable', 'string', 'max:255'],
            'a_tiene_seguro' => ['boolean'],
            'a_tiene_igss' => ['boolean'],
            'a_compania_seguros' => ['nullable', 'required_if:a_tiene_seguro,true', 'string', 'max:255'],
            'a_poliza' => ['nullable', 'string', 'max:255'],
            'a_certificado' => ['nullable', 'string', 'max:255'],
        ],
        4 => [
            'a_tipo_atencion' => ['required', Rule::in(array_keys(AdmissionType::options()))],
            'a_fecha_ingreso' => ['required', 'date'],
            'a_hora_ingreso' => ['nullable', 'date_format:H:i'],
            'a_sala_ingreso' => ['nullable', 'string', 'max:255'],
            'a_habitacion' => ['nullable', 'string', 'max:255'],
            'a_medico_responsable' => ['nullable', 'string', 'max:255'],
            'a_referido_por' => ['nullable', 'string', 'max:255'],
            'a_otras_hospitalizaciones' => ['nullable', 'string'],
            'a_maternidad_no_hijo' => ['nullable', 'string', 'max:255'],
            'a_maternidad_fecha_nacimiento' => ['nullable', 'date'],
            'a_maternidad_hora' => ['nullable', 'date_format:H:i'],
            'a_maternidad_sexo' => ['nullable', 'in:M,F'],
            'a_maternidad_condiciones_egreso' => ['nullable', 'string'],
        ],
        default => [],
    };
};

$nextStep = function () {
    $this->validate();
    $this->currentStep = min(4, $this->currentStep + 1);
};

$previousStep = function () {
    $this->currentStep = max(1, $this->currentStep - 1);
};

$goToStep = function (int $step) {
    if ($step < 1 || $step > 4 || $step > $this->currentStep) {
        return;
    }
    $this->currentStep = $step;
};

$save = function () {
    $this->validate();

    abort_unless(Auth::check(), 401);

    $admission = DB::transaction(function () {
        $hospitalId = Auth::user()->hospital_id;
        $hospital = \App\Models\Hospital::find($hospitalId);

        $nacionalidad = $this->p_es_extranjero ? ($this->p_nacionalidad ?: null) : 'Guatemalteco/a';

        $lugarNacimiento = $this->p_es_extranjero
            ? trim(collect([$this->p_ciudad_nacimiento, $this->p_estado_nacimiento, $this->p_pais_nacimiento])->filter()->implode(', '))
            : trim(collect([$this->p_municipio, $this->p_departamento])->filter()->implode(', '));

        $emergencyContacts = collect($this->p_emergency_contacts)
            ->filter(fn ($c) => ! empty($c['nombre']) || ! empty($c['telefono']))
            ->values()
            ->all();

        $patientData = [
            'primer_apellido' => $this->p_es_recien_nacido ? ($this->p_primer_apellido ?: null) : $this->p_primer_apellido,
            'segundo_apellido' => $this->p_segundo_apellido ?: null,
            'primer_nombre' => $this->p_es_recien_nacido ? ($this->p_primer_nombre ?: null) : $this->p_primer_nombre,
            'segundo_nombre' => $this->p_segundo_nombre ?: null,
            'id_country' => $this->p_id_country ?: null,
            'id_type' => $this->p_id_type ?: null,
            'dpi' => $this->p_dpi ?: null,
            'fecha_nacimiento' => $this->p_fecha_nacimiento ?: null,
            'sexo' => $this->p_sexo ?: null,
            'lugar_nacimiento' => $lugarNacimiento ?: null,
            'nacionalidad' => $nacionalidad,
            'estado_civil' => $this->p_estado_civil ?: null,
            'es_recien_nacido' => (bool) $this->p_es_recien_nacido,
            'madre_paciente_id' => $this->p_es_recien_nacido ? $this->p_madre_paciente_id : null,
            'direccion_habitual' => $this->p_direccion_habitual ?: null,
            'municipio' => $this->p_es_extranjero ? null : ($this->p_municipio ?: null),
            'departamento' => $this->p_es_extranjero ? null : ($this->p_departamento ?: null),
            'telefono' => $this->p_telefono ?: null,
            'telefono_casa' => $this->p_telefono_casa ?: null,
            'emergency_contacts' => ! empty($emergencyContacts) ? $emergencyContacts : null,
            'nombre_padre' => $this->p_nombre_padre ?: null,
            'nombre_madre' => $this->p_nombre_madre ?: null,
            'nombre_conyuge' => $this->p_nombre_conyuge ?: null,
            'contacto_emergencia' => $emergencyContacts[0]['nombre'] ?? null,
        ];

        if ($this->patientId) {
            $patient = Patient::withoutGlobalScopes()->find($this->patientId);

            if (! $patient || $patient->hospital_id !== $hospitalId) {
                $this->addError('patientId', 'El paciente seleccionado no pertenece a este hospital.');

                return null;
            }

            $patient->update([
                'expediente_no' => $patient->expediente_no ?: $this->p_expediente_no ?: null,
                ...$patientData,
            ]);
        } else {
            $patient = Patient::create([
                'hospital_id' => $hospitalId,
                'expediente_no' => \App\Support\PatientExpediente::siguiente($hospital),
                ...$patientData,
            ]);
        }

        return Admission::create([
            'hospital_id' => $hospitalId,
            'patient_id' => $patient->id,
            'tipo_atencion' => $this->isRapidMode ? AdmissionType::URGENCIA->value : $this->a_tipo_atencion,
            'va_a_quirofano' => (bool) $this->a_va_a_quirofano,
            'fecha_ingreso' => $this->a_fecha_ingreso,
            'hora_ingreso' => $this->a_hora_ingreso ?: null,
            'sala_ingreso' => $this->a_sala_ingreso ?: null,
            'habitacion' => $this->a_habitacion ?: null,
            'tiene_seguro' => $this->isRapidMode ? false : (bool) $this->a_tiene_seguro,
            'tiene_igss' => $this->isRapidMode ? false : (bool) $this->a_tiene_igss,
            'compania_seguros' => $this->isRapidMode ? null : ($this->a_compania_seguros ?: null),
            'poliza' => $this->isRapidMode ? null : ($this->a_poliza ?: null),
            'certificado' => $this->isRapidMode ? null : ($this->a_certificado ?: null),
            'referido_por' => $this->isRapidMode ? null : ($this->a_referido_por ?: null),
            'otras_hospitalizaciones' => $this->isRapidMode ? null : ($this->a_otras_hospitalizaciones ?: null),
            'muestra_patologia' => false,
            'medico_responsable' => $this->a_medico_responsable ?: null,
            'maternidad_no_hijo' => $this->isRapidMode ? null : ($this->a_maternidad_no_hijo ?: null),
            'maternidad_fecha_nacimiento' => $this->isRapidMode ? null : ($this->a_maternidad_fecha_nacimiento ?: null),
            'maternidad_hora' => $this->isRapidMode ? null : ($this->a_maternidad_hora ?: null),
            'maternidad_sexo' => $this->isRapidMode ? null : ($this->a_maternidad_sexo ?: null),
            'maternidad_condiciones_egreso' => $this->isRapidMode ? null : ($this->a_maternidad_condiciones_egreso ?: null),
            'qr_token' => AdmissionQr::generateToken(),
            'completo' => ! $this->isRapidMode,
        ]);
    });

    if (! $admission) {
        return;
    }

    $this->savedAdmission = $admission;
    $this->lastAdmissionPatientId = $admission->va_a_quirofano ? $admission->patient_id : null;

    $this->dispatch('admission-saved');
};

?>

<div class="max-w-6xl mx-auto p-4 space-y-6">
    {{-- Header --}}
    <div class="flex items-center justify-between gap-4">
        <div>
            <div class="text-xs text-zinc-500 dark:text-zinc-400 mb-1">{{ __('Ingresos') }} / {{ __('Nuevo ingreso') }}</div>
            <flux:heading size="xl">
                {{ $isRapidMode ? __('Ingreso rápido') : __('Nuevo ingreso') }}
            </flux:heading>
        </div>
        <div class="flex items-center gap-3">
            @if (! $isRapidMode)
                <flux:button wire:click="$set('isRapidMode', true)" variant="outline" class="border-urgent text-urgent hover:bg-urgent-soft">
                    <span class="w-2 h-2 rounded-full bg-urgent mr-2"></span>
                    {{ __('Ingreso rápido') }}
                </flux:button>
            @else
                <flux:button wire:click="$set('isRapidMode', false); currentStep = 1" variant="ghost">
                    {{ __('Cancelar') }}
                </flux:button>
            @endif
            <flux:link href="{{ route('patients.index') }}" class="text-sm">{{ __('Volver') }}</flux:link>
        </div>
    </div>

    {{-- Confirmación + QR --}}
    @if ($savedAdmission)
        <div x-data x-init="window.scrollTo({ top: 0, behavior: 'smooth' })" class="print-area rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-6 space-y-6 shadow-lg">
            <flux:callout variant="success" icon="check-circle" heading="{{ __('Ingreso registrado correctamente') }}" />

            @if (! $savedAdmission->completo)
                <flux:callout variant="warning" icon="exclamation-triangle" heading="{{ __('Ingreso rápido pendiente de completar') }}" />
            @endif

            <div class="flex flex-col md:flex-row items-center gap-6">
                <div class="p-4 bg-white rounded-xl border border-zinc-200 shadow-sm print:shadow-none">
                    {!! App\Support\AdmissionQr::svg($savedAdmission, 180) !!}
                </div>

                <div class="flex-1 text-center md:text-left space-y-1">
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Paciente') }}</p>
                    <p class="text-lg font-semibold">{{ $savedAdmission->patient->nombreCompleto() ?: __('Recién nacido/a') }}</p>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">
                        {{ App\Enums\AdmissionType::from($savedAdmission->tipo_atencion)->label() }}
                        · {{ $savedAdmission->completo ? __('Completo') : __('Pendiente de completar') }}
                    </p>
                    @if ($savedAdmission->patient->expediente_no)
                        <p class="text-sm">
                            <span class="text-zinc-500">{{ __('Expediente') }}:</span>
                            <span class="font-semibold text-accent">{{ $savedAdmission->patient->expediente_no }}</span>
                        </p>
                    @endif
                </div>

                <div class="flex flex-col gap-3 no-print">
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

    {{-- MODO RÁPIDO --}}
    @if ($isRapidMode)
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-6 space-y-6">
            <div class="p-4 rounded-lg bg-urgent-soft border border-urgent/20 text-urgent text-sm">
                {{ __('Solo lo esencial. El resto se completa después.') }}
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <flux:input wire:model="p_primer_apellido" label="{{ __('1er. apellido') }} *" />
                <flux:input wire:model="p_segundo_apellido" label="{{ __('2do. apellido') }}" />
                <flux:input wire:model="p_primer_nombre" label="{{ __('1er. nombre') }} *" />
                <flux:input wire:model="p_segundo_nombre" label="{{ __('2do. nombre o más') }}" />

                <div>
                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">{{ __('Sexo') }}</label>
                    <div class="grid grid-cols-2 rounded-lg overflow-hidden border border-zinc-200 dark:border-zinc-700">
                        <button type="button" wire:click="$set('p_sexo', 'M')"
                            class="py-2.5 text-sm font-medium {{ $p_sexo === 'M' ? 'bg-mist text-accent-content dark:bg-accent/20 dark:text-accent' : 'bg-white dark:bg-zinc-900 text-zinc-600 dark:text-zinc-400' }}">
                            {{ __('Masculino') }}
                        </button>
                        <button type="button" wire:click="$set('p_sexo', 'F')"
                            class="py-2.5 text-sm font-medium {{ $p_sexo === 'F' ? 'bg-mist text-accent-content dark:bg-accent/20 dark:text-accent' : 'bg-white dark:bg-zinc-900 text-zinc-600 dark:text-zinc-400' }}">
                            {{ __('Femenino') }}
                        </button>
                    </div>
                </div>

                <div class="md:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-4">
                    <flux:input type="date" wire:model="a_fecha_ingreso" label="{{ __('Fecha de ingreso') }} *" />
                    <flux:input type="time" wire:model="a_hora_ingreso" label="{{ __('Hora') }}">
                        <x-slot name="suffix">
                            <button type="button" wire:click="setNow" class="text-xs font-semibold text-accent px-2">{{ __('Ahora') }}</button>
                        </x-slot>
                    </flux:input>
                </div>

                <flux:input wire:model="a_sala_ingreso" label="{{ __('Sala') }}" />
                <flux:input wire:model="a_habitacion" label="{{ __('Habitación') }}" />
            </div>

            <div class="flex items-center justify-between p-4 rounded-xl border border-zinc-200 dark:border-zinc-700">
                <div>
                    <div class="font-semibold">{{ __('Va a quirófano') }}</div>
                    <div class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Se creará la programación de cirugía al guardar') }}</div>
                </div>
                <flux:switch wire:model="a_va_a_quirofano" />
            </div>

            <div class="pt-2">
                <flux:button wire:click="save" variant="primary" class="w-full md:w-auto bg-urgent hover:bg-urgent/90">
                    {{ __('Registrar ingreso') }}
                </flux:button>
                <p class="mt-3 text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('Expediente automático · queda marcado como') }} <strong class="text-urgent">{{ __('pendiente de completar') }}</strong>.
                </p>
            </div>
        </div>
    @endif

    {{-- MODO NORMAL: WIZARD 4 PASOS --}}
    @if (! $isRapidMode)
        {{-- Stepper --}}
        <div class="grid grid-cols-4 gap-3">
            @foreach ($this->stepLabels as $step => $label)
                <button type="button" wire:click="goToStep({{ $step }})"
                    @class([
                        'flex flex-col gap-2 text-left transition-opacity',
                        'opacity-60' => $currentStep < $step,
                    ])>
                    <div @class([
                        'h-1 rounded-full',
                        'bg-accent' => $currentStep >= $step,
                        'bg-zinc-200 dark:bg-zinc-700' => $currentStep < $step,
                    ])></div>
                    <div class="flex items-center gap-2">
                        @if ($currentStep > $step)
                            <span class="size-5 rounded-full bg-accent text-white text-xs grid place-items-center">✓</span>
                        @else
                            <span @class([
                                'size-5 rounded-full text-xs grid place-items-center border',
                                'bg-accent text-accent-foreground border-accent' => $currentStep === $step,
                                'border-zinc-300 dark:border-zinc-600 text-zinc-500' => $currentStep < $step,
                            ])>{{ $step }}</span>
                        @endif
                        <div>
                            <div @class(['text-sm font-semibold', 'text-accent' => $currentStep === $step])>{{ $label['title'] }}</div>
                            <div class="text-xs text-zinc-500 dark:text-zinc-400 hidden sm:block">{{ $label['subtitle'] }}</div>
                        </div>
                    </div>
                </button>
            @endforeach
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Formulario principal --}}
            <div class="lg:col-span-2 space-y-6">
                {{-- Paso 1: Identificación --}}
                @if ($currentStep === 1)
                    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-6 space-y-6">
                        <div>
                            <flux:heading size="lg">{{ __('¿A quién vamos a ingresar?') }}</flux:heading>
                            <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">
                                {{ __('Busca por DPI, CUI, nombres o apellidos. Si ya existe, precargamos sus datos.') }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <div class="relative">
                                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-accent text-lg">⌕</span>
                                <input type="text" wire:model.live.debounce.200ms="patientQuery"
                                    placeholder="{{ __('Buscar paciente...') }}"
                                    class="block w-full rounded-xl border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-zinc-900 dark:text-zinc-100 focus:border-accent focus:ring-accent pl-11 pr-4 py-3.5" />
                            </div>

                            @if(!empty($this->patientSuggestions) && ! $patientId)
                                <div class="text-xs text-zinc-500 px-1">{{ count($this->patientSuggestions) }} {{ __('coincidencias') }}</div>
                                <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 overflow-hidden divide-y dark:divide-zinc-700">
                                    @foreach($this->patientSuggestions as $s)
                                        <div class="grid grid-cols-[44px_1.6fr_1fr_1fr_auto] gap-4 items-center px-4 py-3 hover:bg-zinc-50 dark:hover:bg-zinc-800">
                                            <div class="size-10 rounded-full bg-accent text-white grid place-items-center text-sm font-semibold">
                                                {{ collect(explode(' ', $s['name']))->map(fn($w) => mb_substr($w,0,1))->take(2)->implode('') }}
                                            </div>
                                            <div>
                                                <div class="font-semibold text-sm">{{ $s['name'] }}</div>
                                                <div class="text-xs text-zinc-500">
                                                    {{ $s['sexo'] ? ($s['sexo'] === 'M' ? 'Masculino' : 'Femenino') : '' }}
                                                    {{ $s['edad'] ? ' · ' . $s['edad'] : '' }}
                                                </div>
                                            </div>
                                            <div class="text-sm font-variant-numeric tabular-nums">
                                                <div class="text-xs text-zinc-500">{{ __('Documento') }}</div>
                                                {{ $s['dpi'] ?: '—' }}
                                            </div>
                                            <div class="text-sm">
                                                <div class="text-xs text-zinc-500">{{ __('Último ingreso') }}</div>
                                                —
                                            </div>
                                            <flux:button size="sm" wire:click="selectPatient({{ $s['id'] }})">{{ __('Ingresar') }} →</flux:button>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        <div class="flex items-center justify-between p-4 rounded-xl border border-dashed border-zinc-300 dark:border-zinc-600">
                            <div>
                                <div class="font-semibold text-sm">{{ __('¿No aparece?') }}</div>
                                <div class="text-sm text-zinc-500">{{ __('Se creará el expediente automáticamente al guardar.') }}</div>
                            </div>
                            <flux:button variant="outline" wire:click="newPatient">+ {{ __('Registrar paciente nuevo') }}</flux:button>
                        </div>

                        <div class="flex justify-end pt-2">
                            <flux:button variant="primary" wire:click="nextStep">{{ __('Siguiente: Datos personales') }} →</flux:button>
                        </div>
                    </div>
                @endif

                {{-- Paso 2: Datos personales --}}
                @if ($currentStep === 2)
                    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-6 space-y-6">
                        <flux:heading size="lg">{{ __('Datos personales') }}</flux:heading>

                        {{-- Nacionalidad primero --}}
                        <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-mist/20 dark:bg-zinc-800/20 space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-2">{{ __('¿Es guatemalteco/a?') }}</label>
                                <div class="flex flex-wrap gap-2">
                                    <button type="button" wire:click="$set('p_es_extranjero', false); $set('p_id_country', 'GT'); $set('p_id_type', 'CUI / DPI'); $set('p_nacionalidad', 'Guatemalteco/a')"
                                        class="px-4 h-10 rounded-lg text-sm border {{ ! $p_es_extranjero ? 'bg-mist border-accent text-accent-content dark:bg-accent/20 dark:text-accent font-semibold' : 'bg-white dark:bg-zinc-900 border-zinc-200 dark:border-zinc-700 text-zinc-600 dark:text-zinc-400' }}">
                                        {{ __('Sí, guatemalteco/a') }}
                                    </button>
                                    <button type="button" wire:click="$set('p_es_extranjero', true); $set('p_id_country', 'OTHER'); $set('p_id_type', 'Pasaporte'); $set('p_nacionalidad', '')"
                                        class="px-4 h-10 rounded-lg text-sm border {{ $p_es_extranjero ? 'bg-mist border-accent text-accent-content dark:bg-accent/20 dark:text-accent font-semibold' : 'bg-white dark:bg-zinc-900 border-zinc-200 dark:border-zinc-700 text-zinc-600 dark:text-zinc-400' }}">
                                        {{ __('No, es extranjero/a') }}
                                    </button>
                                </div>
                            </div>

                            @if ($p_es_extranjero)
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">{{ __('País de origen') }}</label>
                                        <select wire:model.live="p_id_country"
                                            class="w-full rounded-lg border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 text-zinc-900 dark:text-zinc-100 p-2.5">
                                            @foreach ($this->paises as $code => $country)
                                                <option value="{{ $code }}">{{ $country['name'] }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <flux:input wire:model.live="p_nacionalidad" label="{{ __('Nacionalidad') }}" placeholder="{{ __('Ej. Salvadoreño/a') }}" />
                                    <div>
                                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">{{ __('Tipo de documento') }}</label>
                                        <select wire:model.live="p_id_type"
                                            class="w-full rounded-lg border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 text-zinc-900 dark:text-zinc-100 p-2.5">
                                            @foreach ($this->tiposDocumento as $doc)
                                                <option value="{{ $doc['type'] }}">{{ $doc['type'] }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            @else
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <flux:input wire:model="p_nacionalidad" label="{{ __('Nacionalidad') }}" />
                                    <div>
                                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">{{ __('Tipo de documento') }}</label>
                                        <select wire:model.live="p_id_type"
                                            class="w-full rounded-lg border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 text-zinc-900 dark:text-zinc-100 p-2.5">
                                            @foreach ($this->tiposDocumento as $doc)
                                                <option value="{{ $doc['type'] }}">{{ $doc['type'] }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            @endif

                            <flux:input wire:model="p_dpi" label="{{ $p_es_extranjero ? __('Número de documento') : __('Número de CUI / DPI') }}" />
                        </div>

                        {{-- Nombres --}}
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <flux:input wire:model="p_primer_apellido" label="{{ __('1er. apellido') }} {{ $p_es_recien_nacido ? '' : '*' }}" />
                            <flux:input wire:model="p_segundo_apellido" label="{{ __('2do. apellido') }}" />
                            <flux:input wire:model="p_primer_nombre" label="{{ __('1er. nombre') }} {{ $p_es_recien_nacido ? '' : '*' }}" />
                            <flux:input wire:model="p_segundo_nombre" label="{{ __('2do. nombre o más') }}" />
                        </div>

                        {{-- Recién nacido --}}
                        <div class="flex items-start gap-3 p-4 rounded-xl border border-zinc-200 dark:border-zinc-700">
                            <flux:checkbox wire:model.live="p_es_recien_nacido" label="{{ __('Es recién nacido/a (aún no tiene nombre registrado)') }}" />
                        </div>

                        @if ($p_es_recien_nacido)
                            <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-mist/20 dark:bg-zinc-800/20 space-y-4">
                                <div class="relative">
                                    <flux:input wire:model.live.debounce.300ms="p_madre_query" label="{{ __('Nombre de la madre') }}" placeholder="{{ __('Buscar paciente femenino existente...') }}" autocomplete="off" />
                                    @if (count($this->madreSuggestions))
                                        <div class="absolute z-10 mt-1 w-full rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 shadow-lg overflow-hidden">
                                            @foreach ($this->madreSuggestions as $m)
                                                <button type="button" wire:click="selectMadre({{ $m['id'] }}, '{{ addslashes($m['name']) }}')"
                                                    class="w-full text-left px-3 py-2 text-sm hover:bg-zinc-50 dark:hover:bg-zinc-800">
                                                    {{ $m['name'] }} <span class="text-zinc-500">{{ $m['dpi'] ? '· '.$m['dpi'] : '' }}</span>
                                                </button>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>

                                @if ($p_madre_paciente_id && $p_madre_query)
                                    <div class="flex items-center gap-2 text-sm">
                                        <span class="text-zinc-500">{{ __('Madre vinculada:') }}</span>
                                        <span class="font-medium">{{ $p_madre_query }}</span>
                                        <button type="button" wire:click="clearMadre" class="text-red-600 hover:text-red-700 text-xs underline">{{ __('Cambiar') }}</button>
                                    </div>
                                @endif
                            </div>
                        @endif

                        {{-- Fecha de nacimiento, edad, sexo, lugar --}}
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <flux:input type="date" wire:model="p_fecha_nacimiento" wire:change="sugerirEstadoCivil" label="{{ __('Fecha de nacimiento') }}" />
                            <div>
                                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">{{ __('Edad') }}</label>
                                <div class="h-11 px-3 rounded-lg border border-dashed border-zinc-300 dark:border-zinc-600 flex items-center text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ $this->edadCalculada ? $this->edadCalculada->fullFormatted() : __('Se calcula automáticamente') }}
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">{{ __('Sexo') }}</label>
                                <div class="grid grid-cols-2 rounded-lg overflow-hidden border border-zinc-200 dark:border-zinc-700 h-11">
                                    <button type="button" wire:click="$set('p_sexo', 'M')"
                                        class="text-sm font-medium {{ $p_sexo === 'M' ? 'bg-mist text-accent-content dark:bg-accent/20 dark:text-accent' : 'bg-white dark:bg-zinc-900 text-zinc-500' }}">M</button>
                                    <button type="button" wire:click="$set('p_sexo', 'F')"
                                        class="text-sm font-medium {{ $p_sexo === 'F' ? 'bg-mist text-accent-content dark:bg-accent/20 dark:text-accent' : 'bg-white dark:bg-zinc-900 text-zinc-500' }}">F</button>
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">{{ __('Estado civil') }}</label>
                                <div class="flex flex-wrap gap-2">
                                    @foreach (['S' => 'Soltero/a', 'C' => 'Casado/a', 'U' => 'Unido/a', 'D' => 'Divorciado/a', 'V' => 'Viudo/a'] as $value => $label)
                                        <button type="button" wire:click="$set('p_estado_civil', '{{ $value }}')"
                                            class="px-3 h-9 rounded-lg text-sm border {{ $p_estado_civil === $value ? 'bg-mist border-accent text-accent-content dark:bg-accent/20 dark:text-accent font-semibold' : 'bg-white dark:bg-zinc-900 border-zinc-200 dark:border-zinc-700 text-zinc-600 dark:text-zinc-400' }}">
                                            {{ $label }}
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        {{-- Lugar de nacimiento --}}
                        <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 space-y-4">
                            <div class="font-medium text-sm">{{ __('Lugar de nacimiento') }}</div>

                            @if ($p_es_extranjero)
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <flux:input wire:model="p_pais_nacimiento" label="{{ __('País') }}" />
                                    <flux:input wire:model="p_estado_nacimiento" label="{{ __('Estado / Provincia / Departamento') }}" />
                                    <flux:input wire:model="p_ciudad_nacimiento" label="{{ __('Ciudad') }}" />
                                </div>
                            @else
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">{{ __('Departamento') }}</label>
                                        <select wire:model.live="p_departamento"
                                            class="w-full rounded-lg border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 text-zinc-900 dark:text-zinc-100 p-2.5">
                                            <option value="">-- {{ __('Seleccionar') }} --</option>
                                            @foreach ($this->departamentos as $depto)
                                                <option value="{{ $depto }}">{{ $depto }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">{{ __('Municipio') }}</label>
                                        <select wire:model="p_municipio"
                                            class="w-full rounded-lg border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 text-zinc-900 dark:text-zinc-100 p-2.5">
                                            <option value="">-- {{ __('Seleccionar') }} --</option>
                                            @foreach ($this->municipiosDelDepartamento as $muni)
                                                <option value="{{ $muni }}">{{ $muni }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            @endif
                        </div>

                        {{-- Familiares --}}
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <flux:input wire:model="p_nombre_padre" label="{{ __('Nombre del padre') }}" />
                            <flux:input wire:model="p_nombre_madre" label="{{ __('Nombre de la madre') }}" />
                            <flux:input wire:model="p_nombre_conyuge" label="{{ __('Nombre del cónyuge') }}" />
                        </div>

                        <div class="flex justify-end pt-2">
                            <flux:button variant="primary" wire:click="nextStep">{{ __('Siguiente: Contactos y seguro') }} →</flux:button>
                        </div>
                    </div>
                @endif

                {{-- Paso 3: Contactos y seguro --}}
                @if ($currentStep === 3)
                    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-6 space-y-6">
                        <flux:heading size="lg">{{ __('Contactos y seguro') }}</flux:heading>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <flux:input wire:model="p_direccion_habitual" label="{{ __('Dirección habitual') }}" />
                            <flux:input wire:model="p_telefono" label="{{ __('Teléfono del paciente') }}" />
                            <flux:input wire:model="p_telefono_casa" label="{{ __('Teléfono de casa u otro contacto del paciente') }}" />
                        </div>

                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <flux:heading size="sm">{{ __('Contactos de emergencia') }}</flux:heading>
                                <flux:button size="sm" variant="outline" wire:click="addEmergencyContact">+ {{ __('Agregar otro') }}</flux:button>
                            </div>

                            @foreach ($p_emergency_contacts as $index => $contact)
                                <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 space-y-3">
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('Contacto') }} {{ $index + 1 }}</span>
                                        <button type="button" wire:click="removeEmergencyContact({{ $index }})" class="text-xs text-red-600 hover:text-red-700 underline">
                                            {{ __('Eliminar') }}
                                        </button>
                                    </div>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <flux:input wire:model="p_emergency_contacts.{{ $index }}.nombre" label="{{ __('Nombre') }}" />
                                        <flux:input wire:model="p_emergency_contacts.{{ $index }}.telefono" label="{{ __('Teléfono') }}" />
                                        <flux:input wire:model="p_emergency_contacts.{{ $index }}.departamento" label="{{ __('Departamento') }}" />
                                        <flux:input wire:model="p_emergency_contacts.{{ $index }}.municipio" label="{{ __('Municipio') }}" />
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-2">
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

                        <div class="flex justify-between pt-2">
                            <flux:button variant="ghost" wire:click="previousStep">← {{ __('Atrás') }}</flux:button>
                            <flux:button variant="primary" wire:click="nextStep">{{ __('Siguiente: Ingreso clínico') }} →</flux:button>
                        </div>
                    </div>
                @endif

                {{-- Paso 4: Ingreso clínico --}}
                @if ($currentStep === 4)
                    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-6 space-y-6">
                        <flux:heading size="lg">{{ __('Ingreso clínico') }}</flux:heading>

                        <div>
                            <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-2">{{ __('Tipo de atención') }}</label>
                            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-2">
                                @foreach ($this->tipoOptions as $value => $label)
                                    <button type="button" wire:click="$set('a_tipo_atencion', '{{ $value }}')"
                                        class="px-3 h-12 rounded-lg text-sm border {{ $a_tipo_atencion === $value ? 'bg-mist border-accent text-accent-content dark:bg-accent/20 dark:text-accent font-semibold' : 'bg-white dark:bg-zinc-900 border-zinc-200 dark:border-zinc-700 text-zinc-600 dark:text-zinc-400' }}">
                                        {{ $label }}
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <flux:input type="date" wire:model="a_fecha_ingreso" label="{{ __('Fecha de ingreso') }} *" />
                            <flux:input type="time" wire:model="a_hora_ingreso" label="{{ __('Hora') }}">
                                <x-slot name="suffix">
                                    <button type="button" wire:click="setNow" class="text-xs font-semibold text-accent px-2">{{ __('Ahora') }}</button>
                                </x-slot>
                            </flux:input>
                            <div class="relative">
                                <flux:input wire:model.live.debounce.300ms="a_sala_ingreso" label="{{ __('Sala') }}" placeholder="{{ __('Escribe o elige del catálogo') }}" autocomplete="off" />
                                @if (count($this->salaSuggestions))
                                    <div class="absolute z-10 mt-1 w-full rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 shadow-lg overflow-hidden">
                                        @foreach ($this->salaSuggestions as $w)
                                            <button type="button" wire:click="$set('a_sala_ingreso', '{{ $w['name'] }}')"
                                                class="w-full text-left px-3 py-2 text-sm hover:bg-zinc-50 dark:hover:bg-zinc-800">
                                                {{ $w['name'] }}
                                            </button>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                            <div class="relative">
                                <flux:input wire:model.live.debounce.300ms="a_habitacion" label="{{ __('Habitación') }}" placeholder="{{ __('Escribe o elige del catálogo') }}" autocomplete="off" />
                                @if (count($this->habitacionSuggestions))
                                    <div class="absolute z-10 mt-1 w-full rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 shadow-lg overflow-hidden">
                                        @foreach ($this->habitacionSuggestions as $r)
                                            <button type="button" wire:click="$set('a_habitacion', '{{ $r['name'] }}')"
                                                class="w-full text-left px-3 py-2 text-sm hover:bg-zinc-50 dark:hover:bg-zinc-800">
                                                {{ $r['name'] }}
                                            </button>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="relative">
                                <flux:input wire:model.live.debounce.300ms="a_medico_responsable" label="{{ __('Médico responsable') }}" placeholder="{{ __('Busca en el staff o escribe libre') }}" autocomplete="off" />
                                @if (count($this->medicoSuggestions))
                                    <div class="absolute z-10 mt-1 w-full rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 shadow-lg overflow-hidden">
                                        @foreach ($this->medicoSuggestions as $doc)
                                            <button type="button" wire:click="$set('a_medico_responsable', '{{ $doc['name'] }}')"
                                                class="w-full text-left px-3 py-2 text-sm hover:bg-zinc-50 dark:hover:bg-zinc-800">
                                                {{ $doc['name'] }}
                                            </button>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                            <flux:input wire:model="a_referido_por" label="{{ __('Referido por') }}" />
                        </div>

                        <div class="flex items-center justify-between p-4 rounded-xl border border-zinc-200 dark:border-zinc-700">
                            <div>
                                <div class="font-semibold">{{ __('Va a quirófano') }}</div>
                                <div class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Se creará la programación de cirugía al guardar') }}</div>
                            </div>
                            <flux:switch wire:model="a_va_a_quirofano" />
                        </div>

                        <flux:textarea wire:model="a_otras_hospitalizaciones" label="{{ __('Otras hospitalizaciones') }}" />

                        {{-- Maternidad: solo si el paciente es femenino --}}
                        @if ($p_sexo === 'F')
                            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-mist/30 dark:bg-zinc-800/30 p-5 space-y-4">
                                <flux:heading size="sm">{{ __('Maternidad') }}</flux:heading>

                                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                    <flux:input wire:model="a_maternidad_no_hijo" label="{{ __('No. de hijo') }}" />
                                    <flux:input type="date" wire:model="a_maternidad_fecha_nacimiento" label="{{ __('Fecha de nacimiento') }}" />
                                    <flux:input type="time" wire:model="a_maternidad_hora" label="{{ __('Hora') }}" />
                                    <div>
                                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">{{ __('Sexo del recién nacido') }}</label>
                                        <div class="grid grid-cols-2 rounded-lg overflow-hidden border border-zinc-200 dark:border-zinc-700 h-11">
                                            <button type="button" wire:click="$set('a_maternidad_sexo', 'M')"
                                                class="text-sm font-medium {{ $a_maternidad_sexo === 'M' ? 'bg-mist text-accent-content dark:bg-accent/20 dark:text-accent' : 'bg-white dark:bg-zinc-900 text-zinc-500' }}">M</button>
                                            <button type="button" wire:click="$set('a_maternidad_sexo', 'F')"
                                                class="text-sm font-medium {{ $a_maternidad_sexo === 'F' ? 'bg-mist text-accent-content dark:bg-accent/20 dark:text-accent' : 'bg-white dark:bg-zinc-900 text-zinc-500' }}">F</button>
                                        </div>
                                    </div>
                                </div>

                                <flux:textarea wire:model="a_maternidad_condiciones_egreso" label="{{ __('Condiciones del egreso') }}" />
                            </div>
                        @endif

                        <div class="flex justify-between pt-2">
                            <flux:button variant="ghost" wire:click="previousStep">← {{ __('Atrás') }}</flux:button>
                            <flux:button variant="primary" wire:click="save">{{ __('Guardar ingreso') }}</flux:button>
                        </div>
                    </div>
                @endif
            </div>

            {{-- Panel lateral resumen --}}
            @if (! $isRapidMode && $currentStep > 1)
                <aside class="space-y-4">
                    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-5 space-y-4">
                        <div class="flex items-center gap-3">
                            <div class="size-11 rounded-full bg-accent text-white grid place-items-center font-semibold">
                                {{ collect([$p_primer_nombre, $p_primer_apellido])->filter()->map(fn($w) => mb_substr($w, 0, 1))->implode('') ?: '?' }}
                            </div>
                            <div class="min-w-0">
                                <div class="font-semibold truncate">
                                    {{ trim($p_primer_nombre . ' ' . $p_segundo_nombre . ' ' . $p_primer_apellido . ' ' . $p_segundo_apellido) ?: ($p_es_recien_nacido ? __('Recién nacido/a') : __('Paciente nuevo')) }}
                                </div>
                                <div class="text-sm text-zinc-500 truncate">
                                    {{ $p_sexo ? ($p_sexo === 'M' ? 'Masculino' : 'Femenino') : '' }}
                                    {{ $this->edadCalculada ? ' · ' . $this->edadCalculada->formatted() : '' }}
                                </div>
                            </div>
                        </div>

                        @if ($this->categoriaPaciente)
                            <div class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-mist text-accent-content dark:bg-accent/20 dark:text-accent">
                                {{ $this->categoriaPaciente }}
                            </div>
                        @endif

                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between"><span class="text-zinc-500">{{ __('Documento') }}</span><span>{{ $p_dpi ?: '—' }}</span></div>
                            <div class="flex justify-between"><span class="text-zinc-500">{{ __('Expediente') }}</span>
                                <span class="{{ $p_expediente_no ? 'text-accent font-semibold' : 'text-zinc-400' }}">
                                    {{ $p_expediente_no ?: __('Se asigna al guardar') }}
                                </span>
                            </div>
                            <div class="flex justify-between"><span class="text-zinc-500">{{ __('Teléfono') }}</span><span>{{ $p_telefono ?: '—' }}</span></div>
                            <div class="flex justify-between"><span class="text-zinc-500">{{ __('Nacionalidad') }}</span><span>{{ $p_nacionalidad ?: '—' }}</span></div>
                            <div class="flex justify-between"><span class="text-zinc-500">{{ __('Seguro') }}</span><span>{{ $a_tiene_seguro ? ($a_compania_seguros ?: 'Sí') : 'No' }}</span></div>
                            <div class="flex justify-between"><span class="text-zinc-500">IGSS</span><span>{{ $a_tiene_igss ? 'Sí' : 'No' }}</span></div>
                        </div>
                    </div>

                    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 divide-y dark:divide-zinc-700">
                        @foreach ($this->stepLabels as $step => $label)
                            <button type="button" wire:click="goToStep({{ $step }})"
                                class="w-full flex items-center justify-between px-4 py-3 text-sm {{ $currentStep === $step ? 'font-semibold' : '' }}">
                                <span class="flex items-center gap-2">
                                    @if ($currentStep > $step)
                                        <span class="size-5 rounded-full bg-accent text-white text-xs grid place-items-center">✓</span>
                                    @else
                                        <span class="size-5 rounded-full border-2 {{ $currentStep === $step ? 'border-accent text-accent' : 'border-zinc-300 dark:border-zinc-600 text-zinc-500' }} text-xs grid place-items-center">{{ $step }}</span>
                                    @endif
                                    {{ $label['title'] }}
                                </span>
                                @if ($currentStep > $step)
                                    <span class="text-xs text-accent">{{ __('Editar') }}</span>
                                @endif
                            </button>
                        @endforeach
                    </div>
                </aside>
            @endif
        </div>
    @endif
</div>
