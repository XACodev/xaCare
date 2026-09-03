<?php

use App\Models\RateModifier;
use App\Models\SurgicalAssignment;
use App\Models\SurgicalCase;
use App\Models\SurgicalRole;
use App\Models\User;
use App\Services\RateResolutionService;
use App\Support\TimeHelper;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

use function Livewire\Volt\{state, mount, computed};

state([
    'case' => null,
    'procedure_date' => null,
    'start_time' => '',
    'end_time' => '',
    'patient_name' => '',
    'procedure_type' => '',
    'is_videosurgery' => false,
    'assignments' => [],
    'success_message' => null,
]);

mount(function (SurgicalCase $procedure) {
    $user = Auth::user();
    abort_unless($user && $user->can('procedures.edit'), 403);
    abort_unless($procedure->status === 'pending', 403);

    $this->case = $procedure;
    $this->procedure_date = Carbon\Carbon::parse($procedure->procedure_date)->format('Y-m-d');
    $this->start_time = substr($procedure->start_time, 0, 5);
    $this->end_time = substr($procedure->end_time, 0, 5);
    $this->patient_name = $procedure->patient_name ?? '';
    $this->procedure_type = $procedure->procedure_type ?? '';
    $this->is_videosurgery = (bool) $procedure->is_videosurgery;

    $this->assignments = $procedure->assignments()->with(['surgicalRole', 'user'])->get()
        ->map(function (SurgicalAssignment $a) {
            $evaluated = data_get($a->pricing_snapshot, 'modifiers_evaluated', []);
            $manualToggles = collect($evaluated)
                ->filter(fn ($m) => ($m['trigger_type'] ?? null) === RateModifier::TRIGGER_MANUAL_TOGGLE && ($m['applies'] ?? false) === true)
                ->pluck('id')
                ->values()
                ->all();

            return [
                'id' => $a->id,
                'role_id' => $a->surgical_role_id,
                'role_name' => $a->surgicalRole->name,
                'user_id' => $a->user_id,
                'user_query' => $a->user?->name ?? '',
                'is_courtesy' => (bool) $a->is_courtesy,
                'note' => $a->note ?? '',
                'amount' => (float) $a->calculated_amount,
                'manual_toggles' => $manualToggles,
            ];
        })->all();
});

$historyFor = computed(function () {
    return fn (int $assignmentId) => SurgicalAssignment::find($assignmentId)
        ?->activities()
        ->with('causer:id,name')
        ->latest('id')
        ->get() ?? collect();
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

$recalculate = function (int $index) {
    $row = $this->assignments[$index];
    $role = SurgicalRole::find($row['role_id']);
    $user = $row['user_id'] ? User::find($row['user_id']) : null;
    $mins = TimeHelper::durationMinutes($this->procedure_date, $this->start_time, $this->end_time);

    $pricing = app(RateResolutionService::class)->resolve(
        role: $role,
        user: $user,
        procedureType: $this->procedure_type ?: null,
        procedureDate: $this->procedure_date,
        startTimeHHMM: $this->start_time,
        durationMinutes: max($mins, 0),
        isCourtesy: (bool) $row['is_courtesy'],
        manualToggleIds: array_map('intval', $row['manual_toggles'] ?? []),
    );

    $this->assignments[$index]['amount'] = (float) $pricing['amount'];
};

$save = function () {
    $this->success_message = null;
    $user = Auth::user();
    abort_unless($user && $user->can('procedures.edit'), 403);
    abort_if((bool) $user?->is_platform_admin, 403, 'Administrador de plataforma es de solo lectura; usa una cuenta de hospital para operar.');
    abort_unless($this->case->status === 'pending', 403);

    $durationMinutes = TimeHelper::durationMinutes($this->procedure_date, $this->start_time, $this->end_time);
    if ($durationMinutes <= 0) {
        throw ValidationException::withMessages(['end_time' => 'La hora de finalización debe ser posterior a la hora de inicio.']);
    }

    DB::transaction(function () use ($durationMinutes) {
        $this->case->update([
            'procedure_date' => $this->procedure_date,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'duration_minutes' => $durationMinutes,
            'patient_name' => $this->patient_name,
            'procedure_type' => $this->procedure_type,
            'is_videosurgery' => (bool) $this->is_videosurgery,
        ]);

        foreach ($this->assignments as $row) {
            $role = SurgicalRole::find($row['role_id']);
            $assignedUser = $row['user_id'] ? User::find($row['user_id']) : null;

            $pricing = app(RateResolutionService::class)->resolve(
                role: $role,
                user: $assignedUser,
                procedureType: $this->procedure_type,
                procedureDate: $this->procedure_date,
                startTimeHHMM: $this->start_time,
                durationMinutes: $durationMinutes,
                isCourtesy: (bool) $row['is_courtesy'],
                manualToggleIds: array_map('intval', $row['manual_toggles'] ?? []),
            );

            // Se busca y actualiza la instancia del modelo (en vez de un update()
            // masivo vía query builder) para que se disparen los eventos de
            // Eloquent que spatie/laravel-activitylog necesita para registrar el
            // historial de auditoría de honorarios.
            SurgicalAssignment::find($row['id'])?->update([
                'user_id' => $assignedUser?->id,
                'is_courtesy' => (bool) $row['is_courtesy'],
                'note' => $row['note'] ?: null,
                'calculated_amount' => (float) $pricing['amount'],
                'pricing_snapshot' => $pricing['snapshot'],
            ]);
        }
    });

    $this->success_message = 'Procedimiento actualizado.';
};

?>

<div class="max-w-6xl mx-auto p-4 space-y-6">
    <flux:button href="{{ route('procedures.index') }}" icon="arrow-left" variant="subtle">{{ __('Return') }}
    </flux:button>
    <div>
        <flux:heading size="xl">{{ __('Edit Procedure') }}</flux:heading>
        <flux:subheading>{{ __('Modify the details of the surgical procedure.') }}</flux:subheading>
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
            <flux:field>
                <flux:label>
                    {{ __('Date') }}
                </flux:label>
                <flux:input type="date" wire:model.live="procedure_date" clearable />

                @error('procedure_date') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p>
                @enderror
            </flux:field>

            <flux:field>
                <flux:label>
                    {{ __('Start Time') }}
                </flux:label>
                <flux:input type="time" wire:model.live="start_time" clearable />
                @error('start_time') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
            </flux:field>

            <flux:field>
                <flux:label>
                    {{ __('End Time') }}
                </flux:label>
                <flux:input type="time" wire:model.live="end_time" clearable />
                @error('end_time') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
            </flux:field>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <flux:field>
                <flux:label>
                    {{ __('Patient') }}
                </flux:label>
                <flux:input type="text" wire:model="patient_name" placeholder="{{ __('Patient Name') }}" clearable />
                @error('patient_name') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p>
                @enderror
            </flux:field>

            <flux:field>
                <flux:label>
                    {{ __('Procedure') }}
                </flux:label>
                <flux:input type="text" wire:model="procedure_type" clearable
                    placeholder="{{ __('Procedure Name') }}" />
                @error('procedure_type') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p>
                @enderror
            </flux:field>
        </div>

        <hr class="border-indigo-300 dark:border-zinc-600">

        <div class="w-full flex flex-col md:flex-row justify-center md:justify-between gap-10 md:px-6 md:w-auto">
            <flux:checkbox wire:model.change="is_videosurgery" label="{{ __('Videosurgery') }}" class="cursor-pointer"
                description="{{ __('Check if the procedure was by video.') }}" />
        </div>

        <hr class="border-indigo-300 dark:border-zinc-600">

        <div class="space-y-4">
            <flux:heading size="lg">{{ __('Assignments') }}</flux:heading>

            {{-- La persona asignada se selecciona de la lista de usuarios; el rol no se reasigna al editar. --}}
            @php($allUsers = \App\Models\User::query()
                ->when(Auth::user()?->hospital_id, fn ($q) => $q->where('hospital_id', Auth::user()->hospital_id))
                ->orderBy('name')
                ->get(['id', 'name']))

            @foreach($assignments as $index => $row)
                <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-4 space-y-3">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <flux:label>{{ __('Role') }}</flux:label>
                            <div class="mt-2 text-sm font-medium text-zinc-900 dark:text-zinc-100 py-2.5">
                                {{ $row['role_name'] }}
                            </div>
                        </div>

                        <flux:select wire:model="assignments.{{ $index }}.user_id"
                            wire:change="recalculate({{ $index }})" label="{{ __('Person') }}"
                            placeholder="{{ __('Select person') }}">
                            <flux:select.option value="">{{ __('Unassigned') }}</flux:select.option>
                            @foreach($allUsers as $u)
                                <flux:select.option value="{{ $u->id }}">{{ $u->name }}</flux:select.option>
                            @endforeach
                        </flux:select>

                        <div class="text-right">
                            <span class="text-xs text-zinc-500 uppercase">{{ __('Amount') }}</span>
                            <div class="text-lg font-bold text-indigo-600">
                                Q{{ number_format((float) $row['amount'], 2) }}
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-6">
                        <flux:checkbox wire:model="assignments.{{ $index }}.is_courtesy"
                            wire:change="recalculate({{ $index }})" label="{{ __('Courtesy') }}" />

                        @if($row['role_id'])
                            @foreach(($this->manualModifiersFor)($row['role_id'], $row['user_id'], $procedure_type) as $modifier)
                                <flux:checkbox wire:model.live="assignments.{{ $index }}.manual_toggles" value="{{ $modifier->id }}"
                                    wire:change="recalculate({{ $index }})" label="{{ $modifier->name }}" />
                            @endforeach
                        @endif
                    </div>

                    <flux:input wire:model="assignments.{{ $index }}.note" label="{{ __('Note (optional)') }}"
                        placeholder="{{ __('e.g. +Q200 due to complication') }}" />

                    {{-- Historial de solo lectura: quien cambio esta asignacion antes de este edit --}}
                    @php($history = ($this->historyFor)($row['id']))
                    @if($history->isNotEmpty())
                        <details class="rounded-md bg-zinc-50 dark:bg-zinc-800/50 px-3 py-2">
                            <summary class="cursor-pointer text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wide">
                                {{ __('Change History') }} ({{ $history->count() }})
                            </summary>

                            <ul class="mt-2 space-y-1 text-xs text-zinc-500 dark:text-zinc-400">
                                @foreach($history as $activity)
                                    <li>
                                        <span class="font-medium text-zinc-700 dark:text-zinc-300">
                                            {{ $activity->causer?->name ?? __('System') }}
                                        </span>
                                        &mdash;
                                        @foreach(($activity->properties['attributes'] ?? []) as $field => $newValue)
                                            <span class="font-mono">
                                                {{ $field }}: {{ $activity->properties['old'][$field] ?? '—' }} &rarr; {{ $newValue }}
                                            </span>@if(!$loop->last), @endif
                                        @endforeach
                                        <span class="italic">
                                            ({{ $activity->created_at->format('d/m/Y H:i') }})
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                        </details>
                    @endif
                </div>
            @endforeach
        </div>

        <div
            class="flex flex-col sm:flex-row items-center justify-between gap-6 bg-indigo-100 dark:bg-indigo-900/40 p-4 rounded-lg border border-indigo-100 dark:border-indigo-700/50">
            <div class="flex flex-col sm:flex-row items-center justify-center gap-4 sm:gap-8 w-full sm:w-auto">
                <div class="flex flex-col items-center sm:items-start">
                    <span class="text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">
                        {{ __('Amount') }}
                    </span>
                    <span class="text-xl font-bold text-indigo-600 dark:text-zinc-100">
                        @php($totalAmount = collect($assignments)->sum('amount'))
                        Q{{ number_format((float) $totalAmount, 2) }}
                    </span>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row gap-4 w-full sm:w-1/3">
                <flux:button wire:click="save" wire:target="save" wire:loading.class="opacity-50 cursor-not-allowed"
                    wire:loading.class.remove="opacity-50 cursor-not-allowed" color="indigo" loading="save"
                    variant="primary" class="w-full sm:w-3/4 font-bold cursor-pointer uppercase">
                    {{ __('Update') }}
                </flux:button>
                <flux:button href="{{ route('procedures.index') }}" variant="subtle" class="w-full sm:w-1/4">
                    {{ __('Cancel') }}
                </flux:button>
            </div>
        </div>
    </div>
</div>