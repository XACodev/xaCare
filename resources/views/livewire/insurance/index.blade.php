<?php

use App\Modules\Insurance\Models\Insurer;
use App\Modules\Insurance\Models\InsurancePolicy;
use App\Models\Patient;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

use function Livewire\Volt\{state, mount, computed, rules};

state([
    'insurer_id' => null,
    'insurer_name' => '',
    'insurer_contact_name' => '',
    'insurer_contact_email' => '',
    'insurer_contact_phone' => '',

    'selected_patient_id' => null,

    'policy_insurer_id' => '',
    'policy_number' => '',
    'policy_start_date' => '',
    'policy_end_date' => '',
]);

rules(fn () => [
    'insurer_name' => [
        'required', 'string', 'max:255',
        Rule::unique('insurers', 'name')
            ->where('hospital_id', Auth::user()?->hospital_id)
            ->ignore($this->insurer_id),
    ],
    'insurer_contact_name' => ['nullable', 'string', 'max:255'],
    'insurer_contact_email' => ['nullable', 'email', 'max:255'],
    'insurer_contact_phone' => ['nullable', 'string', 'max:50'],
]);

mount(function () {
    abort_unless(Auth::check(), 401);
    abort_unless((bool) (Auth::user()->hasRole('admin') || Auth::user()->is_platform_admin), 403);
});

// El global scope de BelongsToTenant ya filtra por hospital_id del usuario
// autenticado; nunca usar withoutGlobalScopes() aquí para no filtrar datos
// de otro hospital en la UI.
$insurers = computed(function () {
    return Insurer::query()->orderBy('name')->get();
});

$patients = computed(function () {
    return Patient::query()->orderBy('primer_apellido')->limit(100)->get();
});

$selectedPatient = computed(function () {
    return $this->selected_patient_id
        ? Patient::query()->find($this->selected_patient_id)
        : null;
});

$policies = computed(function () {
    if (! $this->selected_patient_id) {
        return collect();
    }

    return InsurancePolicy::query()
        ->where('patient_id', $this->selected_patient_id)
        ->with('insurer')
        ->orderByDesc('id')
        ->get();
});

$editInsurer = function (int $id) {
    // Scoped por el global scope de BelongsToTenant: si la aseguradora pertenece a
    // otro hospital, find() devuelve null y respondemos 404 explícitamente (abort_if
    // usa abort(), que sí se respeta en Livewire::test — a diferencia de dejar que
    // findOrFail lance ModelNotFoundException sin capturar).
    $insurer = Insurer::query()->find($id);
    abort_if(! $insurer, 404);

    $this->insurer_id = $insurer->id;
    $this->insurer_name = $insurer->name;
    $this->insurer_contact_name = $insurer->contact_name;
    $this->insurer_contact_email = $insurer->contact_email;
    $this->insurer_contact_phone = $insurer->contact_phone;
};

$resetInsurerForm = function () {
    $this->reset(['insurer_id', 'insurer_name', 'insurer_contact_name', 'insurer_contact_email', 'insurer_contact_phone']);
};

$saveInsurer = function () {
    $this->validate();

    Insurer::updateOrCreate(
        ['id' => $this->insurer_id],
        [
            'name' => $this->insurer_name,
            'contact_name' => $this->insurer_contact_name ?: null,
            'contact_email' => $this->insurer_contact_email ?: null,
            'contact_phone' => $this->insurer_contact_phone ?: null,
        ],
    );

    $this->resetInsurerForm();
    unset($this->insurers);
};

$deleteInsurer = function (int $id) {
    // Scoped por el global scope de BelongsToTenant: si la aseguradora pertenece a
    // otro hospital, find() devuelve null y respondemos 404 explícitamente.
    $insurer = Insurer::query()->find($id);
    abort_if(! $insurer, 404);

    $insurer->delete();

    unset($this->insurers);
};

$savePolicy = function () {
    $this->validate([
        'selected_patient_id' => ['required', 'integer', Rule::exists('patients', 'id')],
        'policy_insurer_id' => ['required', 'integer', Rule::exists('insurers', 'id')],
        'policy_number' => ['required', 'string', 'max:255'],
        'policy_start_date' => ['nullable', 'date'],
        'policy_end_date' => ['nullable', 'date', 'after_or_equal:policy_start_date'],
    ]);

    // find() scoped por BelongsToTenant garantiza que tanto el paciente como la
    // aseguradora pertenecen al hospital del usuario actual; si no, 404 explícito.
    $patient = Patient::query()->find($this->selected_patient_id);
    $insurer = Insurer::query()->find($this->policy_insurer_id);
    abort_if(! $patient || ! $insurer, 404);

    InsurancePolicy::create([
        'insurer_id' => $insurer->id,
        'patient_id' => $patient->id,
        'policy_number' => $this->policy_number,
        'start_date' => $this->policy_start_date ?: null,
        'end_date' => $this->policy_end_date ?: null,
        'status' => 'active',
    ]);

    $this->reset(['policy_insurer_id', 'policy_number', 'policy_start_date', 'policy_end_date']);
    unset($this->policies);
};

?>

<div class="max-w-5xl mx-auto p-4 space-y-8">
    <div>
        <flux:heading size="xl">{{ __('Aseguradoras') }}</flux:heading>
        <flux:subheading>{{ __('Gestiona las aseguradoras y las pólizas de tus pacientes.') }}</flux:subheading>
    </div>

    {{-- Aseguradoras --}}
    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-6 space-y-4">
        <flux:heading size="lg">{{ __('Aseguradoras del hospital') }}</flux:heading>

        <div class="divide-y dark:divide-zinc-700 rounded-lg border border-zinc-100 dark:border-zinc-800">
            @forelse($this->insurers as $insurer)
                <div class="px-4 py-3 flex items-center justify-between gap-4">
                    <div>
                        <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $insurer->name }}</div>
                        <div class="text-sm text-zinc-500 dark:text-zinc-400">
                            {{ collect([$insurer->contact_name, $insurer->contact_email, $insurer->contact_phone])->filter()->implode(' · ') ?: __('Sin datos de contacto') }}
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <flux:button size="sm" wire:click="editInsurer({{ $insurer->id }})">{{ __('Editar') }}</flux:button>
                        <flux:button size="sm" variant="danger" wire:click="deleteInsurer({{ $insurer->id }})" wire:confirm="{{ __('¿Eliminar esta aseguradora?') }}">
                            {{ __('Eliminar') }}
                        </flux:button>
                    </div>
                </div>
            @empty
                <div class="px-4 py-6 text-center text-sm text-zinc-500 dark:text-zinc-400 italic">
                    {{ __('Sin aseguradoras registradas todavía.') }}
                </div>
            @endforelse
        </div>

        <form wire:submit="saveInsurer" class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-4 border-t border-zinc-100 dark:border-zinc-800">
            <flux:input label="{{ __('Nombre') }}" wire:model="insurer_name" />
            <flux:input label="{{ __('Contacto') }}" wire:model="insurer_contact_name" />
            <flux:input label="{{ __('Correo') }}" type="email" wire:model="insurer_contact_email" />
            <flux:input label="{{ __('Teléfono') }}" wire:model="insurer_contact_phone" />

            <div class="md:col-span-2 flex justify-end gap-2">
                @if ($insurer_id)
                    <flux:button type="button" wire:click="resetInsurerForm">{{ __('Cancelar') }}</flux:button>
                @endif
                <flux:button type="submit" variant="primary">
                    {{ $insurer_id ? __('Guardar cambios') : __('Crear aseguradora') }}
                </flux:button>
            </div>
        </form>
    </div>

    {{-- Pólizas por paciente --}}
    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-6 space-y-4">
        <flux:heading size="lg">{{ __('Pólizas de un paciente') }}</flux:heading>

        <flux:select label="{{ __('Paciente') }}" wire:model.live="selected_patient_id">
            <flux:select.option value="">-- {{ __('Selecciona un paciente') }} --</flux:select.option>
            @foreach($this->patients as $patient)
                <flux:select.option value="{{ $patient->id }}">{{ $patient->nombreCompleto() }}</flux:select.option>
            @endforeach
        </flux:select>

        @if ($selected_patient_id)
            <div class="divide-y dark:divide-zinc-700 rounded-lg border border-zinc-100 dark:border-zinc-800">
                @forelse($this->policies as $policy)
                    <div class="px-4 py-3 flex items-center justify-between gap-4">
                        <div>
                            <div class="font-medium text-zinc-900 dark:text-zinc-100">
                                {{ $policy->insurer->name }} &middot; {{ $policy->policy_number }}
                            </div>
                            <div class="text-sm text-zinc-500 dark:text-zinc-400">
                                {{ $policy->start_date?->format('d/m/Y') ?? '—' }}
                                -
                                {{ $policy->end_date?->format('d/m/Y') ?? __('Sin vencimiento') }}
                            </div>
                        </div>
                        <flux:badge size="sm" color="{{ $policy->status === 'active' ? 'green' : 'zinc' }}">
                            {{ __($policy->status) }}
                        </flux:badge>
                    </div>
                @empty
                    <div class="px-4 py-6 text-center text-sm text-zinc-500 dark:text-zinc-400 italic">
                        {{ __('Este paciente no tiene pólizas registradas.') }}
                    </div>
                @endforelse
            </div>

            <form wire:submit="savePolicy" class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-4 border-t border-zinc-100 dark:border-zinc-800">
                <flux:select label="{{ __('Aseguradora') }}" wire:model="policy_insurer_id">
                    <flux:select.option value="">-- {{ __('Selecciona') }} --</flux:select.option>
                    @foreach($this->insurers as $insurer)
                        <flux:select.option value="{{ $insurer->id }}">{{ $insurer->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input label="{{ __('Número de póliza') }}" wire:model="policy_number" />
                <flux:input label="{{ __('Inicio de vigencia') }}" type="date" wire:model="policy_start_date" />
                <flux:input label="{{ __('Fin de vigencia') }}" type="date" wire:model="policy_end_date" />

                <div class="md:col-span-2 flex justify-end">
                    <flux:button type="submit" variant="primary">{{ __('Agregar póliza') }}</flux:button>
                </div>
            </form>
        @endif
    </div>
</div>
