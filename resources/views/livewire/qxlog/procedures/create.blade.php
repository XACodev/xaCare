<?php

use App\Models\Admission;
use App\Models\Patient;
use App\Modules\QxLog\Models\RateModifier;
use App\Modules\QxLog\Models\SurgicalAssignment;
use App\Modules\QxLog\Models\SurgicalCase;
use App\Modules\QxLog\Models\SurgicalRole;
use App\Models\User;
use App\Modules\QxLog\Services\RateResolutionService;
use App\Support\TimeHelper;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

use function Livewire\Volt\{state, computed, mount, rules, updated};

state([
    // Form
    'procedure_date' => now()->toDateString(),
    'start_time' => now()->subHour()->format('H:i'),
    'end_time' => now()->format('H:i'),

    // Paciente: se selecciona del maestro (marcado va_a_quirofano)
    'patient_id' => null,
    'patient_query' => '',
    'patient_name' => '',

    'procedure_type' => '',
    'is_videosurgery' => false,

    // Asignaciones: array de filas [role_id, user_id, user_query, is_courtesy, note, manual_toggles(array de ids)]
    'assignments' => [],

    // UX
    'success_message' => null,
]);

$hospitalId = fn () => Auth::user()?->hospital_id;

rules(fn () => [
    'procedure_date' => ['required', 'date', 'before_or_equal:'.now()->toDateString(), 'after_or_equal:'.now()->subWeeks(2)->toDateString()],
    'start_time' => ['required', 'date_format:H:i'],
    'end_time' => ['required', 'date_format:H:i'],
    'patient_id' => ['nullable', 'integer', Rule::exists('patients', 'id')->where('hospital_id', $hospitalId())],
    'patient_query' => ['nullable', 'string', 'max:255'],
    'patient_name' => ['nullable', 'string', 'max:255'],
    'procedure_type' => ['required', 'string', 'max:255'],
    'is_videosurgery' => ['boolean'],
    'assignments' => ['array', 'min:1'],
    'assignments.*.role_id' => ['required', 'integer', Rule::exists('surgical_roles', 'id')->where('hospital_id', $hospitalId())],
    'assignments.*.user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('hospital_id', $hospitalId())],
    'assignments.*.user_query' => ['nullable', 'string', 'max:255'],
    'assignments.*.is_courtesy' => ['boolean'],
    'assignments.*.note' => ['nullable', 'string', 'max:255'],
    'assignments.*.manual_toggles' => ['nullable', 'array'],
    'assignments.*.manual_toggles.*' => ['integer'],
]);

mount(function () {
    abort_unless((bool) Auth::check(), 401, 'Unauthorized');
    abort_unless(in_array(Auth::user()->role, ['instrumentist', 'admin'], true), 403, 'No tienes permiso para registrar procedimientos.');
    abort_if((bool) Auth::user()->is_platform_admin, 403, 'Administrador de plataforma es de solo lectura; usa una cuenta de hospital para operar.');

    abort_if(! Auth::user()->hospital_id, 422, 'Tu usuario no tiene un hospital asignado. Contacta al administrador de la plataforma.');

    $roles = SurgicalRole::query()
        ->where('hospital_id', Auth::user()->hospital_id)
        ->where('active', true)
        ->orderBy('sort_order')
        ->get();
    $instrumentistRole = $roles->firstWhere('slug', 'instrumentista');

    // Fila inicial: el instrumentista logueado, en el rol Instrumentista si existe.
    $this->assignments = [[
        'role_id' => $instrumentistRole?->id,
        'user_id' => Auth::id(),
        'user_query' => Auth::user()->name,
        'is_courtesy' => false,
        'note' => '',
        'manual_toggles' => [],
    ]];
});

$roles = computed(fn () => SurgicalRole::query()
    ->where('hospital_id', Auth::user()?->hospital_id)
    ->where('active', true)
    ->orderBy('sort_order')
    ->get());

$userSuggestions = computed(function () {
    return fn (string $query) => User::query()
        ->where('hospital_id', Auth::user()?->hospital_id)
        ->when(trim($query) !== '', function ($q) use ($query) {
            $normalized = Str::ascii(Str::lower($query));
            $q->whereRaw('LOWER(name) LIKE ?', ["%{$normalized}%"]);
        })
        ->orderBy('name')
        ->limit(8)
        ->get(['id', 'name'])
        ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])
        ->all();
});

$manualModifiersFor = computed(function () {
    return fn (?int $roleId, ?int $userId, ?string $procedureType) => $roleId
        ? RateModifier::query()
            ->whereHas('roleRate', function ($q) use ($roleId, $userId, $procedureType) {
                $q->where('surgical_role_id', $roleId)
                    ->where(function ($sub) use ($userId, $procedureType) {
                        $sub->where(fn ($s) => $s->where('user_id', $userId)->where('procedure_type', $procedureType))
                            ->orWhere(fn ($s) => $s->where('user_id', $userId)->whereNull('procedure_type'))
                            ->orWhere(fn ($s) => $s->whereNull('user_id')->where('procedure_type', $procedureType))
                            ->orWhere(fn ($s) => $s->whereNull('user_id')->whereNull('procedure_type'));
                    });
            })
            ->where('trigger_type', RateModifier::TRIGGER_MANUAL_TOGGLE)
            ->where('active', true)
            ->get()
        : collect();
});

$addAssignment = function () {
    $this->assignments[] = [
        'role_id' => null, 'user_id' => null, 'user_query' => '',
        'is_courtesy' => false, 'note' => '', 'manual_toggles' => [],
    ];
};

$removeAssignment = function (int $index) {
    unset($this->assignments[$index]);
    $this->assignments = array_values($this->assignments);
};

$duration_minutes = computed(function () {
    if (!$this->procedure_date || !$this->start_time || !$this->end_time) {
        return null;
    }
    try {
        return TimeHelper::durationMinutes($this->procedure_date, $this->start_time, $this->end_time);
    } catch (\Throwable $e) {
        return null;
    }
});

$previewAmount = function (int $index) {
    $row = $this->assignments[$index] ?? null;
    if (!$row || !$row['role_id'] || !is_int($this->duration_minutes)) {
        return null;
    }

    $role = SurgicalRole::find($row['role_id']);
    if (!$role) {
        return null;
    }

    $user = $row['user_id'] ? User::find($row['user_id']) : null;

    $result = app(RateResolutionService::class)->resolve(
        role: $role,
        user: $user,
        procedureType: $this->procedure_type ?: null,
        procedureDate: $this->procedure_date,
        startTimeHHMM: $this->start_time,
        durationMinutes: $this->duration_minutes,
        isCourtesy: (bool) ($row['is_courtesy'] ?? false),
        manualToggleIds: array_map('intval', $row['manual_toggles'] ?? []),
    );

    return $result['amount'];
};

$selectAssignmentUser = function (int $index, int $userId) {
    $u = User::find($userId);
    if (!$u) {
        return;
    }
    $this->assignments[$index]['user_id'] = $u->id;
    $this->assignments[$index]['user_query'] = $u->name;
};

$save = function () {
    $this->success_message = null;
    $user = Auth::user();
    abort_unless((bool) Auth::check(), 401, 'Unauthorized');

    $data = $this->validate();

    $patientId = $data['patient_id'] ?? null;
    $patientName = $patientId ? $data['patient_name'] : trim((string) ($data['patient_query'] ?? ''));
    if (!$patientId && $patientName === '') {
        throw ValidationException::withMessages([
            'patient_query' => 'Selecciona un paciente ingresado o escribe el nombre (registro de emergencia).',
        ]);
    }

    // Un instrumentista que se auto-asigna en su propia fila no puede cambiarse a otro rol
    // (ej. Circulante) para alterar su propio pago — el rol se fuerza en servidor, sin
    // confiar en que el <select> de la vista esté deshabilitado. Un admin sí puede asignar
    // cualquier rol a cualquier persona, incluido a sí mismo.
    if ($user->role === 'instrumentist') {
        $instrumentistRoleId = SurgicalRole::query()->where('slug', 'instrumentista')->value('id');
        if ($instrumentistRoleId) {
            foreach ($data['assignments'] as $i => $row) {
                if ((int) ($row['user_id'] ?? 0) === $user->id) {
                    $data['assignments'][$i]['role_id'] = $instrumentistRoleId;
                }
            }
        }
    }

    $durationMinutes = TimeHelper::durationMinutes($data['procedure_date'], $data['start_time'], $data['end_time']);
    if ($durationMinutes <= 0) {
        throw ValidationException::withMessages(['end_time' => 'La hora de finalización debe ser posterior a la hora de inicio.']);
    }
    if ($durationMinutes > (24 * 60)) {
        throw ValidationException::withMessages(['end_time' => 'La duración no puede superar 24 horas.']);
    }

    $payableRoleIds = SurgicalRole::query()->where('is_payable', true)->pluck('id')->all();
    $hasPayableRow = collect($data['assignments'])->contains(fn ($row) => in_array((int) $row['role_id'], $payableRoleIds, true));
    if (!$hasPayableRow) {
        throw ValidationException::withMessages(['assignments' => 'Agrega al menos una asignación de un rol pagable.']);
    }

    $hospitalId = Auth::user()->hospital_id;

    DB::transaction(function () use ($data, $patientId, $patientName, $durationMinutes, $hospitalId) {
        $case = SurgicalCase::create([
            'hospital_id' => $hospitalId,
            'procedure_date' => $data['procedure_date'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'duration_minutes' => $durationMinutes,
            'patient_id' => $patientId,
            'patient_name' => $patientName,
            'procedure_type' => $data['procedure_type'],
            'is_videosurgery' => (bool) $data['is_videosurgery'],
            'status' => 'pending',
            'calculated_amount' => 0,
        ]);

        foreach ($data['assignments'] as $row) {
            $role = SurgicalRole::findOrFail($row['role_id']);
            $assignedUser = $row['user_id'] ? User::find($row['user_id']) : null;
            $isCourtesy = (bool) ($row['is_courtesy'] ?? false);

            $pricing = app(RateResolutionService::class)->resolve(
                role: $role,
                user: $assignedUser,
                procedureType: $data['procedure_type'],
                procedureDate: $data['procedure_date'],
                startTimeHHMM: $data['start_time'],
                durationMinutes: $durationMinutes,
                isCourtesy: $isCourtesy,
                manualToggleIds: array_map('intval', $row['manual_toggles'] ?? []),
            );

            SurgicalAssignment::create([
                'surgical_case_id' => $case->id,
                'surgical_role_id' => $role->id,
                'user_id' => $assignedUser?->id,
                'calculated_amount' => (float) $pricing['amount'],
                'pricing_snapshot' => $pricing['snapshot'],
                'is_courtesy' => $isCourtesy,
                'note' => $row['note'] ?: null,
                'status' => $role->is_payable ? 'pending' : 'paid',
            ]);
        }
    });

    $this->procedure_date = now()->toDateString();
    $this->patient_id = null;
    $this->patient_query = '';
    $this->patient_name = '';
    $this->procedure_type = '';
    $this->start_time = now()->subHour()->format('H:i');
    $this->end_time = now()->format('H:i');
    $this->is_videosurgery = false;
    $instrumentistRole = $this->roles->firstWhere('slug', 'instrumentista');
    $this->assignments = [[
        'role_id' => $instrumentistRole?->id, 'user_id' => Auth::id(), 'user_query' => Auth::user()->name,
        'is_courtesy' => false, 'note' => '', 'manual_toggles' => [],
    ]];

    $this->success_message = 'Procedimiento registrado (pendiente).';
    $this->dispatch('$refresh');
};

$pending_procedures = computed(function () {
    $user = Auth::user();
    if (!$user) return [];

    return SurgicalAssignment::query()
        ->where('user_id', $user->id)
        ->where('status', 'pending')
        ->whereHas('surgicalCase', fn ($q) => $q->where('hospital_id', $user->hospital_id))
        ->with('surgicalCase')
        ->orderByDesc('created_at')
        ->limit(50)
        ->get();
});

$pending_total = computed(function () {
    $user = Auth::user();
    if (!$user) return 0;

    return (float) SurgicalAssignment::query()
        ->where('user_id', $user->id)
        ->where('status', 'pending')
        ->whereHas('surgicalCase', fn ($q) => $q->where('hospital_id', $user->hospital_id))
        ->sum('calculated_amount');
});

$patient_suggestions = computed(function () {
    $q = trim((string) $this->patient_query);
    if ($q === '') {
        return [];
    }

    $normalizedQ = Str::ascii(Str::lower($q));

    // Solo pacientes con un ingreso marcado va_a_quirofano
    $patientIds = Admission::query()
        ->where('va_a_quirofano', true)
        ->pluck('patient_id')
        ->unique();

    return Patient::query()
        ->whereIn('id', $patientIds)
        ->get()
        ->filter(fn($p) => str_contains(Str::ascii(Str::lower($p->nombreCompleto())), $normalizedQ))
        ->take(8)
        ->map(fn($p) => ['id' => $p->id, 'name' => $p->nombreCompleto()])
        ->values()
        ->all();
});

$selectPatient = function (int $id) {
    $p = Patient::find($id);
    if (!$p) {
        return;
    }
    $this->patient_id = $p->id;
    $this->patient_query = $p->nombreCompleto();
    $this->patient_name = $p->nombreCompleto(); // snapshot legacy
};

?>

<div class="max-w-6xl mx-auto p-4 space-y-6">
    <div class="mb-4">
        <flux:heading size="xl">{{ __('Register Procedure') }}</flux:heading>
        <flux:subheading>xaCare • Registro de intervenciones quirúrgicas • (Instrumentista)</flux:subheading>
    </div>

    @if($success_message)
        <div
            class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-green-800 dark:bg-green-900/30 dark:border-green-800 dark:text-green-300 flex items-center gap-2">
            <flux:icon.check-circle class="size-5" />
            {{ $success_message }}
        </div>
    @endif

    <div class="rounded-xl border bg-white p-6 dark:bg-zinc-900 dark:border-zinc-700 space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div>
                <flux:label>
                    {{ __('Date') }}
                </flux:label>
                <input type="date" max="{{ now()->format('Y-m-d') }}" min="{{ now()->subWeeks(2)->format('Y-m-d') }}"
                    wire:model.live="procedure_date"
                    class="mt-2 block w-full min-w-0 max-w-full rounded-lg border-zinc-200 bg-indigo-50 py-2.5 px-3 text-sm text-zinc-900 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-hidden dark:border-zinc-700 dark:bg-zinc-700 dark:text-zinc-100 dark:focus:border-indigo-400 hover:border-zinc-300 dark:hover:border-zinc-600 transition-colors" />

                @error('procedure_date') <p class="text-sm text-red-600 dark:text-red-400 mt-1">
                        {{ $message }}
                    </p>
                @enderror
            </div>

            <div>
                <flux:label>
                    {{ __('Start Time') }}
                </flux:label>
                <input type="time" wire:model.live="start_time"
                    class="mt-2 block w-full min-w-0 max-w-full rounded-lg border-zinc-200 bg-indigo-50 py-2.5 px-3 text-sm text-zinc-900 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-hidden dark:border-zinc-700 dark:bg-zinc-700 dark:text-zinc-100 dark:focus:border-indigo-400 hover:border-zinc-300 dark:hover:border-zinc-600 transition-colors" />
                @error('start_time') <p class="text-sm text-red-600 dark:text-red-400 mt-1">
                        {{ $message }}
                    </p>
                @enderror
            </div>

            <div>
                <flux:label>
                    {{ __('End Time') }}
                </flux:label>
                <input type="time" wire:model.live="end_time"
                    class="mt-2 block w-full min-w-0 max-w-full rounded-lg border-zinc-200 bg-indigo-50 py-2.5 px-3 text-sm text-zinc-900 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-hidden dark:border-zinc-700 dark:bg-zinc-700 dark:text-zinc-100 dark:focus:border-indigo-400 hover:border-zinc-300 dark:hover:border-zinc-600 transition-colors" />
                @error('end_time') <p class="text-sm text-red-600 dark:text-red-400 mt-1">
                        {{ $message }}
                    </p>
                @enderror
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="space-y-2">
                <flux:label>
                    {{ __('Patient') }}
                </flux:label>

                <div class="relative">
                    <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-zinc-400">
                        <flux:icon.magnifying-glass class="size-5" />
                    </div>
                    <input type="text" wire:model.live.debounce.200ms="patient_query"
                        placeholder="{{ __('Search admitted patient or type name for emergency cases...') }}"
                        class="mt-2 block w-full rounded-lg border-zinc-200 bg-indigo-50 py-2.5 pl-10 pr-3 text-sm text-zinc-900 placeholder-zinc-400 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-hidden dark:border-zinc-700 dark:bg-zinc-700 dark:text-zinc-100 dark:focus:border-indigo-400 dark:placeholder-zinc-400 hover:border-zinc-300 dark:hover:border-zinc-600 transition-colors" />

                    @if(!empty($this->patient_suggestions))
                        <div
                            class="absolute z-20 mt-1 w-full rounded-lg border border-zinc-200 bg-white shadow-lg dark:bg-zinc-700 dark:border-indigo-400 overflow-hidden">
                            @foreach($this->patient_suggestions as $s)
                                <button type="button"
                                    class="block w-full text-left px-4 py-2.5 hover:bg-zinc-50 dark:hover:bg-indigo-400/50 text-zinc-700 dark:text-zinc-200 transition-colors border-b border-zinc-100 dark:border-indigo-400 last:border-0"
                                    wire:click="selectPatient({{ $s['id'] }})">
                                    {{ $s['name'] }}
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>

                @error('patient_id') <p class="text-sm text-red-600 dark:text-red-400 mt-1">
                        {{ $message }}
                    </p>
                @enderror
            </div>

            <div>
                <flux:label>
                    {{ __('Procedure') }}
                </flux:label>
                <input type="text" wire:model="procedure_type" placeholder="{{ __('Procedure Name') }}"
                    class="mt-2 block w-full rounded-lg border-zinc-200 bg-indigo-50 py-2.5 px-3 text-sm text-zinc-900 placeholder-zinc-400 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-hidden dark:border-zinc-700 dark:bg-zinc-700 dark:text-zinc-100 dark:focus:border-indigo-400 dark:placeholder-zinc-400 hover:border-zinc-300 dark:hover:border-zinc-600 transition-colors" />
                @error('procedure_type') <p class="text-sm text-red-600 dark:text-red-400 mt-1">
                        {{ $message }}
                    </p>
                @enderror
            </div>
        </div>

        <hr class="border-indigo-300 dark:border-zinc-600">

        <div class="w-full flex flex-col md:flex-row justify-center md:justify-between sm:px-6 gap-10 md:w-auto">
            <flux:checkbox wire:model.change="is_videosurgery" label="{{ __('Videosurgery') }}"
                description="{{ __('Check if the procedure was by video.') }}" />
        </div>

        <hr class="border-indigo-300 dark:border-zinc-600">

        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <flux:heading size="lg">{{ __('Assignments') }}</flux:heading>
                <flux:button type="button" wire:click="addAssignment" size="sm" variant="filled">
                    {{ __('Add role') }}
                </flux:button>
            </div>

            @error('assignments') <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror

            @foreach($assignments as $index => $row)
                @php($isOwnAssignment = Auth::user()->role === 'instrumentist' && (int) ($row['user_id'] ?? 0) === Auth::id())
                <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-4 space-y-3">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <flux:select wire:model.live="assignments.{{ $index }}.role_id" label="{{ __('Role') }}"
                            placeholder="{{ __('Select role') }}" :disabled="$isOwnAssignment">
                            @foreach($this->roles as $r)
                                <flux:select.option value="{{ $r->id }}">{{ $r->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        @if($isOwnAssignment)
                            <p class="text-xs text-zinc-500 dark:text-zinc-400 -mt-2 md:col-span-1">
                                {{ __('You cannot change your own assigned role.') }}
                            </p>
                        @endif

                        <div class="space-y-2 relative">
                            <flux:label>{{ __('Person') }}</flux:label>
                            <input type="text" wire:model.live.debounce.200ms="assignments.{{ $index }}.user_query"
                                placeholder="{{ __('Search person...') }}"
                                class="mt-2 block w-full rounded-lg border-zinc-200 bg-indigo-50 py-2.5 px-3 text-sm dark:border-zinc-700 dark:bg-zinc-700" />
                            @if(!empty($row['user_query']))
                                <div class="absolute z-20 mt-1 w-full rounded-lg border bg-white shadow-lg dark:bg-zinc-700">
                                    @foreach(($this->userSuggestions)($row['user_query']) as $s)
                                        <button type="button" class="block w-full text-left px-4 py-2 hover:bg-zinc-50 dark:hover:bg-indigo-400/50"
                                            wire:click="selectAssignmentUser({{ $index }}, {{ $s['id'] }})">
                                            {{ $s['name'] }}
                                        </button>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        <div class="flex items-end gap-4">
                            <div class="text-right flex-1">
                                <span class="text-xs text-zinc-500 uppercase">{{ __('Amount') }}</span>
                                <div class="text-lg font-bold text-indigo-600">
                                    @php($preview = $this->previewAmount($index))
                                    Q{{ is_numeric($preview) ? number_format($preview, 2) : '0.00' }}
                                </div>
                            </div>
                            @if(count($assignments) > 1)
                                <flux:button type="button" wire:click="removeAssignment({{ $index }})" size="sm" variant="danger" icon="trash">
                                </flux:button>
                            @endif
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-6">
                        <flux:checkbox wire:model.live="assignments.{{ $index }}.is_courtesy" label="{{ __('Courtesy') }}" />

                        @if($row['role_id'])
                            @foreach(($this->manualModifiersFor)($row['role_id'], $row['user_id'], $procedure_type) as $modifier)
                                <flux:checkbox wire:model.live="assignments.{{ $index }}.manual_toggles" value="{{ $modifier->id }}"
                                    label="{{ $modifier->name }}" />
                            @endforeach
                        @endif
                    </div>

                    <flux:input wire:model.live="assignments.{{ $index }}.note" label="{{ __('Note (optional)') }}"
                        placeholder="{{ __('e.g. +Q200 due to complication') }}" />
                </div>
            @endforeach
        </div>

        <div
            class="flex flex-col sm:flex-row items-center justify-between gap-6 bg-indigo-100 dark:bg-indigo-900/40 p-4 rounded-lg border border-indigo-100 dark:border-indigo-700/50">
            <div class="flex flex-col sm:flex-row items-center justify-center gap-4 sm:gap-8 w-full sm:w-auto">
                @if (Auth::user()->use_pay_scheme)
                    <div class="flex flex-col items-start">
                        <span class="text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">
                            {{ __('Duration') }}
                        </span>
                        <span class="text-xl font-bold text-zinc-900 dark:text-zinc-100">
                            {{ is_int($this->duration_minutes) ? $this->duration_minutes . ' min' : '--' }}
                        </span>
                    </div>
                    <div class="w-full h-px sm:w-px sm:h-12 bg-indigo-300 dark:bg-indigo-600"></div>
                @endif

                <div class="flex flex-col items-center sm:items-start">
                    <span class="text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">
                        {{ __('Amount') }}
                    </span>
                    <span class="text-xl font-bold text-indigo-600 dark:text-zinc-100">
                        @php($totalPreview = collect($assignments)->keys()->sum(fn ($i) => (float) ($this->previewAmount($i) ?? 0)))
                        Q{{ number_format($totalPreview, 2) }}
                    </span>
                </div>
            </div>

            <flux:button wire:click="save" wire:target="save" wire:loading.class="opacity-50 cursor-not-allowed"
                wire:loading.class.remove="opacity-50 cursor-not-allowed" color="indigo" loading="save"
                variant="primary" class="w-full font-bold sm:w-1/3 cursor-pointer uppercase">
                {{ __('Save') }}
            </flux:button>
        </div>
    </div>

    <div class="mt-8 space-y-4">
        <div class="flex flex-col md:flex-row gap-2 items-center justify-between">
            <h2 class="text-lg font-semibold text-zinc-700 dark:text-zinc-200">
                {{ __('Pending Procedures') }}
            </h2>

            <div
                class="flex items-center gap-2 text-md bg-indigo-100 dark:bg-indigo-500/60 px-4 py-2 rounded-full border border-indigo-50 dark:border-indigo-800 shadow-sm">
                <span class="text-indigo-500 dark:text-zinc-400">
                    {{ __('Total') }}:
                </span>
                <span class="text-xl font-bold text-indigo-600 dark:text-zinc-200">
                    Q{{ number_format($this->pending_total ?? 0, 2) }}
                </span>
            </div>
        </div>

        <div
            class="rounded-xl border border-zinc-200 bg-white shadow-sm dark:bg-zinc-800 dark:border-zinc-700 overflow-hidden">
            {{-- Desktop --}}
            <div class="hidden md:block overflow-x-auto">
                <table class="min-w-full overflow-auto table-auto text-sm whitespace-nowrap">
                    <thead
                        class="bg-indigo-100 dark:bg-indigo-900/40 text-zinc-500 dark:text-zinc-300 transition-colors">
                        <tr>
                            <th class="px-6 py-4 font-medium text-left">
                                <flux:label for="procedure_date">
                                    {{ __('Date') }}
                                </flux:label>
                            </th>
                            @if (Auth::user()->use_pay_scheme)
                                <th class="px-6 py-4 font-medium text-center">
                                    <flux:label for="procedure_time">
                                        {{ __('Schedule') }}
                                    </flux:label>
                                </th>
                            @endif
                            <th class="px-6 py-4 font-medium text-left">
                                <flux:label for="patient_id">
                                    {{ __('Patient') }}
                                </flux:label>
                            </th>
                            <th class="px-6 py-4 font-medium text-left">
                                <flux:label for="procedure_id">
                                    {{ __('Procedure') }}
                                </flux:label>
                            </th>
                            <th class="px-6 py-4 font-medium text-left">
                                <flux:label for="rule">
                                    {{ __('Rule') }}
                                </flux:label>
                            </th>
                            <th class="px-6 py-4 font-medium text-right">
                                <flux:label for="amount">
                                    {{ __('Amount') }}
                                </flux:label>
                            </th>
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-indigo-200 dark:divide-zinc-700 whitespace-nowrap text-zinc-600 dark:text-zinc-400 transition-colors">
                        @forelse($this->pending_procedures as $p)
                            @php($case = $p->surgicalCase)
                            <tr class=" hover:bg-indigo-50 dark:hover:bg-indigo-800/30 transition-colors">
                                <td class="px-6 py-3 font-medium text-left">
                                    {{ $case?->procedure_date?->format('d/m/Y') }}
                                </td>
                                @if(Auth::user()->use_pay_scheme)
                                    <td class="px-6 py-3 whitespace-nowrap items-center text-center">
                                        <div class="flex flex-col items-center gap-0.5">
                                            <div>
                                                {{ $case?->start_time ? Carbon\Carbon::parse($case->start_time)->format('H:i') : '' }}
                                                <span class="text-xs text-zinc-400 dark:text-zinc-500">-</span>
                                                {{ $case?->end_time ? Carbon\Carbon::parse($case->end_time)->format('H:i') : '' }}
                                            </div>
                                            <div>
                                                <span class="text-xs text-zinc-400 dark:text-zinc-500">
                                                    {{ $case?->duration_minutes }} min
                                                </span>
                                            </div>
                                        </div>
                                    </td>
                                @endif
                                <td class="px-6 py-3 text-left capitalize font-bold text-zinc-600 dark:text-zinc-200/90">
                                    {{ strtolower((string) $case?->patient_name) }}
                                </td>
                                <td class="px-6 py-3 truncate capitalize max-w-35">
                                    {{ strtolower((string) $case?->procedure_type) }}
                                </td>
                                <td class="px-6 py-3">
                                    @if (Auth::user()->use_pay_scheme)
                                        <x-procedure-rule-badge :rule="data_get($p, 'pricing_snapshot.rule')"
                                            :videosurgery="$case?->is_videosurgery" />
                                    @else
                                        @if ($case?->is_videosurgery)
                                            <flux:badge color="indigo" size="sm">{{ __('Video') }}</flux:badge>
                                        @endif
                                        @if ($p->is_courtesy)
                                            <flux:badge color="lime" size="sm">{{ __('Courtesy') }}</flux:badge>
                                        @endif
                                    @endif
                                </td>
                                <td class="px-6 py-3 text-right font-bold text-zinc-600 dark:text-zinc-200/90">
                                    Q{{ number_format((float) $p->calculated_amount, 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ Auth::user()->use_pay_scheme ? 5 : 4 }}" class="text-center px-4 py-6">
                                    <div class="flex flex-col items-center gap-2">
                                        <flux:icon.document-text class="size-6 opacity-50" />
                                        <p class="text-sm">
                                            No tienes procedimientos pendientes todavía.
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Mobile --}}
            <div class="md:hidden divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse($this->pending_procedures as $p)
                    @php($case = $p->surgicalCase)
                    <div class="p-4 bg-white dark:bg-zinc-900 space-y-4">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <div class="font-medium text-zinc-900 dark:text-zinc-100">
                                    {{ $case?->patient_name }}
                                </div>
                                <div class="text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ $case?->procedure_type }}
                                </div>
                            </div>
                            <div class="text-right shrink-0">
                                <div class="font-mono font-medium text-zinc-600 dark:text-zinc-200/80">
                                    Q{{ number_format((float) $p->calculated_amount, 2) }}
                                </div>
                                <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ $case?->procedure_date?->format('d/m/Y') }}
                                </div>
                            </div>
                        </div>

                        @if(Auth::user()->use_pay_scheme)
                            <div class="flex items-center justify-between gap-2 text-sm pt-2 text-zinc-500 dark:text-zinc-400">
                                <div class="flex flex-col flex-wrap items-center">
                                    <div class="flex flex-wrap justify-between items-center gap-1">
                                        <div class="flex items-center">
                                            {{ $case?->start_time ? Carbon\Carbon::parse($case->start_time)->format('H:i') : '' }} <span
                                                class="text-xs text-zinc-400 dark:text-zinc-500 ml-1">hrs</span>
                                        </div>

                                        <span class="text-xs text-zinc-400 dark:text-zinc-500">-</span>

                                        <div class="flex items-center">
                                            {{ $case?->end_time ? Carbon\Carbon::parse($case->end_time)->format('H:i') : '' }} <span
                                                class="text-xs text-zinc-400 dark:text-zinc-500 ml-1">hrs</span>
                                        </div>
                                    </div>

                                    <span class="text-xs text-zinc-400 dark:text-zinc-500">
                                        {{ $case?->duration_minutes }} min
                                    </span>
                                </div>
                                <x-procedure-rule-badge :rule="data_get($p, 'pricing_snapshot.rule')"
                                    :videosurgery="$case?->is_videosurgery" />
                            </div>
                        @else
                            @if ($p->is_courtesy)
                                <flux:badge color="lime" size="sm">{{ __('Courtesy') }}</flux:badge>
                            @endif
                        @endif
                    </div>
                @empty
                    <div class="p-8 text-center text-zinc-500 dark:text-zinc-400">
                        <div class="flex flex-col items-center gap-2">
                            <flux:icon.document-text class="size-6 opacity-50" />
                            <p class="text-sm">
                                {{ __('You have no pending procedures yet.') }}
                            </p>
                        </div>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>