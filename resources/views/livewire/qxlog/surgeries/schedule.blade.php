<?php

use App\Models\Patient;
use App\Models\User;
use App\Modules\QxLog\Models\OperatingRoom;
use App\Modules\QxLog\Models\SurgeryStatus;
use App\Modules\QxLog\Models\SurgicalAssignment;
use App\Modules\QxLog\Models\SurgicalCase;
use App\Modules\QxLog\Models\SurgicalRole;
use App\Modules\QxLog\Services\OperatingRoomAvailabilityService;
use App\Support\TimeHelper;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

use function Livewire\Volt\{state, mount, computed, rules};

state([
    'surgery' => null,
    'patient_id' => null,
    'patient_query' => '',
    'patient_name' => '',
    'procedure_type_query' => '',
    'procedure_date' => null,
    'start_time' => '',
    'end_time' => '',
    'operating_room_id' => null,
    'surgery_status_id' => null,
    'assignments' => [],
    'success_message' => null,
]);

$hospitalId = fn () => Auth::user()?->hospital_id;

$baseRules = function () use ($hospitalId) {
    return [
        'procedure_date' => ['required', 'date'],
        'start_time' => ['nullable', 'date_format:H:i'],
        'end_time' => ['nullable', 'date_format:H:i'],
        'patient_id' => ['nullable', 'integer', Rule::exists('patients', 'id')->where('hospital_id', $hospitalId())],
        'patient_query' => ['nullable', 'string', 'max:255'],
        'patient_name' => ['nullable', 'string', 'max:255'],
        'procedure_type_query' => ['nullable', 'string', 'max:255'],
        'operating_room_id' => ['nullable', 'integer', Rule::exists('operating_rooms', 'id')->where('hospital_id', $hospitalId())],
        'surgery_status_id' => ['nullable', 'integer', Rule::exists('surgery_statuses', 'id')->where('hospital_id', $hospitalId())],
        'assignments' => ['array'],
        'assignments.*.id' => ['nullable', 'integer'],
        'assignments.*.role_id' => ['nullable', 'integer', Rule::exists('surgical_roles', 'id')->where('hospital_id', $hospitalId())],
        'assignments.*.user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('hospital_id', $hospitalId())],
        'assignments.*.user_query' => ['nullable', 'string', 'max:255'],
        'assignments.*.note' => ['nullable', 'string', 'max:255'],
    ];
};

rules($baseRules);

mount(function (?SurgicalCase $surgery = null) {
    $user = Auth::user();
    abort_unless($user && $user->can('surgeries.schedule'), 403);

    if ($surgery && $surgery->exists) {
        $this->surgery = $surgery;
        $this->patient_id = $surgery->patient_id;
        $this->patient_query = $surgery->patient_name ?? '';
        $this->patient_name = $surgery->patient_name ?? '';
        $this->procedure_type_query = $surgery->procedureType?->name ?? '';
        $this->procedure_date = optional($surgery->procedure_date)->format('Y-m-d');
        $this->start_time = $surgery->start_time ? substr($surgery->start_time, 0, 5) : '';
        $this->end_time = $surgery->end_time ? substr($surgery->end_time, 0, 5) : '';
        $this->operating_room_id = $surgery->operating_room_id;
        $this->surgery_status_id = $surgery->surgery_status_id;
        $this->assignments = $surgery->assignments()->with('user')->get()->map(fn (SurgicalAssignment $a) => [
            'id' => $a->id,
            'role_id' => $a->surgical_role_id,
            'user_id' => $a->user_id,
            'user_query' => $a->user?->name ?? '',
            'note' => $a->note ?? '',
        ])->all();

        return;
    }

    $this->procedure_date = now()->toDateString();

    $prefillPatientId = request()->integer('patient_id') ?: null;
    if ($prefillPatientId) {
        $prefillPatient = Patient::query()->where('id', $prefillPatientId)
            ->where('hospital_id', $user->hospital_id)->first();
        if ($prefillPatient) {
            $this->patient_id = $prefillPatient->id;
            $this->patient_query = $prefillPatient->nombreCompleto();
            $this->patient_name = $prefillPatient->nombreCompleto();
        }
    }

    $defaultRoom = OperatingRoom::query()->where('active', true)
        ->orderByDesc('is_default')->orderBy('sort_order')->first();
    $this->operating_room_id = $defaultRoom?->id;

    $defaultStatus = SurgeryStatus::query()->where('active', true)->where('is_default', true)->first();
    $this->surgery_status_id = $defaultStatus?->id;
});

$rooms = computed(fn () => OperatingRoom::query()->where('active', true)->orderBy('sort_order')->get());

$showRoomSelector = computed(fn () => $this->rooms->count() > 1);

$statuses = computed(fn () => SurgeryStatus::query()->where('active', true)->orderBy('sort_order')->get());

$roles = computed(fn () => SurgicalRole::query()->where('active', true)->orderBy('sort_order')->get());

$patient_suggestions = computed(function () {
    $q = trim((string) $this->patient_query);
    if ($q === '') {
        return [];
    }

    if ($this->patient_id) {
        $selected = Patient::find($this->patient_id);
        if ($selected && Str::lower(trim($selected->nombreCompleto())) === Str::lower($q)) {
            return [];
        }
    }

    $normalizedQ = Str::ascii(Str::lower($q));

    return Patient::query()->get()
        ->filter(fn ($p) => str_contains(Str::ascii(Str::lower($p->nombreCompleto())), $normalizedQ))
        ->take(8)
        ->map(fn ($p) => ['id' => $p->id, 'name' => $p->nombreCompleto()])
        ->values()
        ->all();
});

$userSuggestions = computed(function () {
    return function (string $query, ?int $selectedUserId = null) {
        $q = trim($query);
        if ($q === '') {
            return [];
        }

        if ($selectedUserId) {
            $selected = User::find($selectedUserId);
            if ($selected && Str::lower(trim($selected->name)) === Str::lower($q)) {
                return [];
            }
        }

        $normalized = Str::ascii(Str::lower($q));

        return User::query()
            ->where('hospital_id', Auth::user()?->hospital_id)
            ->appearsAsSuggestion()
            ->whereRaw('LOWER(name) LIKE ?', ["%{$normalized}%"])
            ->orderBy('name')
            ->limit(8)
            ->get(['id', 'name'])
            ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])
            ->all();
    };
});

$selectPatient = function (int $id) {
    $p = Patient::find($id);
    if (! $p) {
        return;
    }
    $this->patient_id = $p->id;
    $this->patient_query = $p->nombreCompleto();
    $this->patient_name = $p->nombreCompleto();
};

$selectAssignmentUser = function (int $index, int $userId) {
    $u = User::find($userId);
    if (! $u) {
        return;
    }
    $this->assignments[$index]['user_id'] = $u->id;
    $this->assignments[$index]['user_query'] = $u->name;
};

$addAssignment = function () {
    $this->assignments[] = ['role_id' => null, 'user_id' => null, 'user_query' => '', 'note' => ''];
};

$removeAssignment = function (int $index) {
    unset($this->assignments[$index]);
    $this->assignments = array_values($this->assignments);
};

$procedureTypeSuggestions = computed(function () {
    $q = trim($this->procedure_type_query);
    if ($q === '') {
        return collect();
    }

    return \App\Modules\QxLog\Models\ProcedureType::query()
        ->where('active', true)
        ->where('name', 'like', '%'.$q.'%')
        ->orderBy('name')
        ->limit(8)
        ->get();
});

$selectProcedureType = function (int $id) {
    $type = \App\Modules\QxLog\Models\ProcedureType::findOrFail($id);
    $this->procedure_type_query = $type->name;
};

$resolveProcedureType = function (): ?\App\Modules\QxLog\Models\ProcedureType {
    $name = trim((string) $this->procedure_type_query);
    if ($name === '') {
        return null;
    }

    return \App\Modules\QxLog\Models\ProcedureType::resolveOrCreateFor(Auth::user()->hospital_id, $name);
};

$persist = function (array $data, bool $isDraft) {
    $hospitalId = Auth::user()->hospital_id;

    $patientId = $data['patient_id'] ?? null;
    $patientName = $patientId ? ($data['patient_name'] ?? '') : trim((string) ($data['patient_query'] ?? ''));

    $payload = [
        'hospital_id' => $hospitalId,
        'procedure_date' => $data['procedure_date'],
        'start_time' => $data['start_time'] ?: null,
        'end_time' => $data['end_time'] ?: null,
        'duration_minutes' => ($data['start_time'] ?? null) && ($data['end_time'] ?? null)
            ? TimeHelper::durationMinutes($data['procedure_date'], $data['start_time'], $data['end_time'])
            : null,
        'patient_id' => $patientId,
        'patient_name' => $patientName !== '' ? $patientName : null,
        'procedure_type_id' => $this->resolveProcedureType()?->id,
        'operating_room_id' => $data['operating_room_id'] ?? null,
        'surgery_status_id' => $data['surgery_status_id'] ?? null,
        'is_draft' => $isDraft,
        'calculated_amount' => 0,
    ];

    if ($this->surgery && $this->surgery->exists) {
        $case = SurgicalCase::query()->where('id', $this->surgery->id)->lockForUpdate()->firstOrFail();
        $case->update($payload);
    } else {
        $case = SurgicalCase::create($payload);
        $this->surgery = $case;
    }

    $existingIds = $case->assignments()->pluck('id')->all();
    $keepIds = [];

    foreach ($data['assignments'] ?? [] as $row) {
        if (empty($row['role_id'])) {
            continue;
        }

        $assignment = ! empty($row['id']) ? SurgicalAssignment::find($row['id']) : null;

        if ($assignment && $assignment->surgical_case_id !== $case->id) {
            abort(403, 'Una asignación no pertenece a esta cirugía.');
        }

        $attributes = [
            'hospital_id' => $hospitalId,
            'surgical_case_id' => $case->id,
            'surgical_role_id' => $row['role_id'],
            'user_id' => $row['user_id'] ?? null,
            'note' => ($row['note'] ?? null) ?: null,
        ];

        $assignment = $assignment
            ? tap($assignment)->update($attributes)
            : SurgicalAssignment::create($attributes);

        $keepIds[] = $assignment->id;
    }

    $toDelete = array_diff($existingIds, $keepIds);
    if ($toDelete) {
        SurgicalAssignment::whereIn('id', $toDelete)->delete();
    }

    return $case;
};

$saveDraft = function () use ($baseRules) {
    $this->success_message = null;
    $user = Auth::user();
    abort_unless($user && $user->can('surgeries.schedule'), 403);

    $data = $this->validate($baseRules());

    $this->persist($data, isDraft: true);

    $this->success_message = 'Borrador guardado.';
};

$schedule = function () use ($baseRules) {
    $this->success_message = null;
    $user = Auth::user();
    abort_unless($user && $user->can('surgeries.schedule'), 403);

    $data = $this->validate(array_merge($baseRules(), [
        'start_time' => ['required', 'date_format:H:i'],
        'end_time' => ['required', 'date_format:H:i'],
        'procedure_type_query' => ['required', 'string', 'max:255'],
    ]));

    if ($data['start_time'] >= $data['end_time']) {
        throw ValidationException::withMessages(['end_time' => 'La hora de finalización debe ser posterior a la hora de inicio.']);
    }

    if (! empty($data['operating_room_id'])) {
        $room = OperatingRoom::findOrFail($data['operating_room_id']);
        $available = app(OperatingRoomAvailabilityService::class)->isAvailable(
            $room,
            $data['procedure_date'],
            $data['start_time'],
            $data['end_time'],
            excludeCaseId: $this->surgery?->id,
        );

        if (! $available) {
            throw ValidationException::withMessages(['operating_room_id' => 'El quirófano ya tiene otra cirugía programada en ese horario.']);
        }
    }

    $this->persist($data, isDraft: false);

    $this->success_message = $this->surgery->wasRecentlyCreated ? 'Cirugía programada.' : 'Cirugía actualizada.';
};

$cancel = function () {
    $user = Auth::user();
    abort_unless($user && $user->can('surgeries.cancel'), 403);
    abort_unless($this->surgery && $this->surgery->exists, 404);

    $cancelledStatus = SurgeryStatus::query()->where('is_cancelled', true)->first();

    $this->surgery->update(['surgery_status_id' => $cancelledStatus?->id]);
    $this->surgery_status_id = $this->surgery->surgery_status_id;
    $this->success_message = 'Cirugía cancelada.';
};

$delete = function () {
    $user = Auth::user();
    abort_unless($user && $user->can('surgeries.delete'), 403);
    abort_unless($this->surgery && $this->surgery->exists, 404);

    $this->surgery->delete();

    $this->success_message = 'Cirugía eliminada.';
};

?>

<div class="max-w-4xl mx-auto p-4 space-y-6">
    <div class="mb-4 flex items-start justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Schedule Surgery') }}</flux:heading>
            <flux:subheading>xaCare • {{ __('Surgery scheduling board') }}</flux:subheading>
        </div>
        <x-back-link :fallback="route('surgeries.board')" />
    </div>

    @if($success_message)
        <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-green-800 dark:bg-green-900/30 dark:border-green-800 dark:text-green-300 flex items-center gap-2">
            <flux:icon.check-circle class="size-5" />
            {{ $success_message }}
        </div>
    @endif

    <div class="rounded-xl border bg-white p-6 dark:bg-zinc-900 dark:border-zinc-700 space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <flux:field>
                <flux:label>{{ __('Date') }}</flux:label>
                <flux:input type="date" wire:model="procedure_date" />
                @error('procedure_date') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Start Time') }}</flux:label>
                <flux:input type="time" wire:model="start_time" />
                @error('start_time') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
            </flux:field>

            <flux:field>
                <flux:label>{{ __('End Time') }}</flux:label>
                <flux:input type="time" wire:model="end_time" />
                @error('end_time') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
            </flux:field>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="space-y-2 relative">
                <flux:label>{{ __('Patient') }}</flux:label>
                <flux:input type="text" wire:model.live.debounce.200ms="patient_query"
                    placeholder="{{ __('Search patient or type name for emergency cases...') }}" />
                @if(!empty($this->patient_suggestions))
                    <div class="absolute z-20 mt-1 w-full rounded-lg border bg-white shadow-lg dark:bg-zinc-700">
                        @foreach($this->patient_suggestions as $s)
                            <button type="button" class="block w-full text-left px-4 py-2 hover:bg-zinc-50 dark:hover:bg-indigo-400/50"
                                wire:click="selectPatient({{ $s['id'] }})">
                                {{ $s['name'] }}
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

            <flux:field>
                <flux:label>{{ __('Procedure') }}</flux:label>
                <div class="relative">
                    <flux:input type="text" wire:model.live.debounce.200ms="procedure_type_query" />
                    @if($this->procedureTypeSuggestions->isNotEmpty())
                        <div class="absolute z-20 mt-1 w-full rounded-lg border border-zinc-200 bg-white shadow-lg dark:bg-zinc-700 dark:border-indigo-400 overflow-hidden">
                            @foreach($this->procedureTypeSuggestions as $s)
                                <button type="button"
                                    class="block w-full text-left px-4 py-2.5 hover:bg-zinc-50 dark:hover:bg-indigo-400/50 text-zinc-700 dark:text-zinc-200 transition-colors border-b border-zinc-100 dark:border-indigo-400 last:border-0"
                                    wire:click="selectProcedureType({{ $s->id }})">
                                    {{ $s->name }}
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>
                @error('procedure_type_query') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
            </flux:field>
        </div>

        @if($this->showRoomSelector)
            <flux:field>
                <flux:label>{{ __('Operating Room') }}</flux:label>
                <flux:select wire:model="operating_room_id" placeholder="{{ __('Select room') }}">
                    @foreach($this->rooms as $room)
                        <flux:select.option value="{{ $room->id }}">{{ $room->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                @error('operating_room_id') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
            </flux:field>
        @endif

        @if($this->statuses->count() > 0)
            <flux:field>
                <flux:label>{{ __('Status') }}</flux:label>
                <flux:select wire:model="surgery_status_id" placeholder="{{ __('Select status') }}">
                    @foreach($this->statuses as $status)
                        <flux:select.option value="{{ $status->id }}">{{ $status->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>
        @endif

        <hr class="border-indigo-300 dark:border-zinc-600">

        <div class="space-y-3">
            <div class="flex items-center justify-between">
                <flux:heading size="lg">{{ __('Surgical Staff') }}</flux:heading>
                <flux:button type="button" wire:click="addAssignment" size="sm" variant="filled">
                    {{ __('Add participant') }}
                </flux:button>
            </div>

            @foreach($assignments as $index => $row)
                <div class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end py-2 {{ !$loop->last ? 'border-b border-zinc-100 dark:border-zinc-800' : '' }}">
                    <flux:select wire:model="assignments.{{ $index }}.role_id" label="{{ __('Role') }}"
                        placeholder="{{ __('Select role') }}">
                        @foreach($this->roles as $r)
                            <flux:select.option value="{{ $r->id }}">{{ $r->name }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <div class="relative">
                        <flux:label>{{ __('Person') }}</flux:label>
                        <input type="text" wire:model.live.debounce.200ms="assignments.{{ $index }}.user_query"
                            placeholder="{{ __('Search person...') }}"
                            class="mt-2 block w-full rounded-lg border-zinc-200 bg-indigo-50 py-2.5 px-3 text-sm dark:border-zinc-700 dark:bg-zinc-700" />
                        @php $rowUserSuggestions = ($this->userSuggestions)($row['user_query'] ?? '', $row['user_id'] ?? null); @endphp
                        @if(!empty($rowUserSuggestions))
                            <div class="absolute z-20 mt-1 w-full rounded-lg border bg-white shadow-lg dark:bg-zinc-700">
                                @foreach($rowUserSuggestions as $s)
                                    <button type="button" class="block w-full text-left px-4 py-2 hover:bg-zinc-50 dark:hover:bg-indigo-400/50"
                                        wire:click="selectAssignmentUser({{ $index }}, {{ $s['id'] }})">
                                        {{ $s['name'] }}
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <flux:input wire:model="assignments.{{ $index }}.note" label="{{ __('Note (optional)') }}" />

                    <flux:button type="button" wire:click="removeAssignment({{ $index }})" size="sm" variant="danger" icon="trash" />
                </div>
            @endforeach
        </div>

        <div class="flex flex-col sm:flex-row gap-4 justify-end">
            @if($surgery && $surgery->exists)
                @can('surgeries.delete')
                    <flux:button wire:click="delete" variant="danger">{{ __('Delete') }}</flux:button>
                @endcan
                @can('surgeries.cancel')
                    <flux:button wire:click="cancel" variant="subtle">{{ __('Cancel Surgery') }}</flux:button>
                @endcan
            @endif
            <flux:button wire:click="saveDraft" variant="subtle">{{ __('Save as Draft') }}</flux:button>
            <flux:button wire:click="schedule" variant="primary">{{ __('Confirm Schedule') }}</flux:button>
        </div>
    </div>
</div>
