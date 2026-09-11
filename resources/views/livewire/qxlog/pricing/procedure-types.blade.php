<?php

use App\Modules\QxLog\Models\ProcedureType;
use App\Modules\QxLog\Models\RoleRate;
use App\Modules\QxLog\Models\SurgicalRole;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

use function Livewire\Volt\{state, computed, mount, rules, updated};

state(['selected_role_id' => null])->url();
state(['new_rate_type_query' => '', 'new_rate_type_id' => null, 'new_rate_amount' => 0]);

rules(fn () => [
    'selected_role_id' => ['required', 'integer', Rule::exists('surgical_roles', 'id')->where('hospital_id', Auth::user()?->hospital_id)],
    'new_rate_type_query' => ['required', 'string', 'max:255'],
    'new_rate_amount' => ['required', 'numeric', 'min:0'],
]);

mount(function () {
    abort_unless(Auth::check(), 401);
    abort_unless((bool) Auth::user()->can('pricing.manage'), 403);
    abort_if((bool) Auth::user()?->is_platform_admin, 403, 'Administrador de plataforma es de solo lectura; usa una cuenta de hospital para operar.');
    abort_if(! Auth::user()?->hospital_id, 422, 'Tu usuario no tiene un hospital asignado.');

    if (! $this->selected_role_id || ! SurgicalRole::query()->where('is_payable', true)->whereKey($this->selected_role_id)->exists()) {
        $this->selected_role_id = SurgicalRole::query()->where('is_payable', true)->orderBy('sort_order')->value('id');
    }
});

$roles = computed(fn () => SurgicalRole::query()->where('is_payable', true)->orderBy('sort_order')->get());

$rates = computed(fn () => $this->selected_role_id
    ? RoleRate::query()->where('surgical_role_id', $this->selected_role_id)->whereNotNull('procedure_type_id')->with('procedureType')->get()
    : collect());

$typeSuggestions = computed(function () {
    if ($this->new_rate_type_id) {
        return collect();
    }
    $q = trim($this->new_rate_type_query);
    if ($q === '') {
        return collect();
    }

    return ProcedureType::query()->where('active', true)->where('name', 'like', '%'.$q.'%')->orderBy('name')->limit(8)->get();
});

$selectType = function (int $id) {
    $type = ProcedureType::findOrFail($id);
    $this->new_rate_type_id = $type->id;
    $this->new_rate_type_query = $type->name;
};

updated(['new_rate_type_query' => function () {
    $this->new_rate_type_id = null;
}]);

$addRate = function () {
    $this->validate();

    $role = SurgicalRole::withoutGlobalScopes()->findOrFail($this->selected_role_id);
    abort_if(Auth::user()->hospital_id !== $role->hospital_id, 403);

    $hospitalId = Auth::user()->hospital_id;

    $type = $this->new_rate_type_id
        ? ProcedureType::withoutGlobalScopes()->findOrFail($this->new_rate_type_id)
        : (function () use ($hospitalId) {
            $name = trim($this->new_rate_type_query);
            $normalized = Str::lower($name);

            return ProcedureType::withoutGlobalScopes()
                ->where('hospital_id', $hospitalId)
                ->whereRaw('LOWER(name) = ?', [$normalized])
                ->first()
                ?? ProcedureType::create(['hospital_id' => $hospitalId, 'name' => $name]);
        })();

    abort_if($type->hospital_id !== $hospitalId, 403);

    DB::transaction(function () use ($role, $type) {
        $rate = RoleRate::query()
            ->where('surgical_role_id', $role->id)
            ->where('procedure_type_id', $type->id)
            ->whereNull('user_id')
            ->lockForUpdate()
            ->first();

        if ($rate) {
            $rate->update(['base_rate' => $this->new_rate_amount, 'active' => true]);
        } else {
            RoleRate::create([
                'hospital_id' => $role->hospital_id,
                'surgical_role_id' => $role->id,
                'user_id' => null,
                'procedure_type_id' => $type->id,
                'base_rate' => $this->new_rate_amount,
                'active' => true,
            ]);
        }
    });

    $this->reset('new_rate_type_query', 'new_rate_type_id', 'new_rate_amount');
    unset($this->rates);
};

$removeRate = function (int $id) {
    $rate = RoleRate::withoutGlobalScopes()->findOrFail($id);
    abort_if($rate->hospital_id !== Auth::user()->hospital_id, 403);
    $rate->delete();
    unset($this->rates);
};

?>

<div class="max-w-4xl mx-auto p-4 space-y-6">
    <div class="mb-4">
        <flux:heading size="xl">{{ __('Pricing by Procedure Type') }}</flux:heading>
        <x-back-link :fallback="route('pricing.instrumentists')" />
    </div>

    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-6 space-y-6">
        <flux:select label="{{ __('Role') }}" wire:model.live="selected_role_id">
            @foreach($this->roles as $role)
                <flux:select.option value="{{ $role->id }}">{{ $role->name }}</flux:select.option>
            @endforeach
        </flux:select>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
            <div class="relative md:col-span-2">
                <flux:label>{{ __('Procedure Type') }}</flux:label>
                <input type="text" wire:model.live.debounce.200ms="new_rate_type_query"
                    class="mt-2 block w-full rounded-lg border-zinc-200 bg-indigo-50 py-2.5 px-3 text-sm dark:border-zinc-700 dark:bg-zinc-700" />
                @if($this->typeSuggestions->isNotEmpty())
                    <div class="absolute z-20 mt-1 w-full rounded-lg border bg-white shadow-lg dark:bg-zinc-700">
                        @foreach($this->typeSuggestions as $s)
                            <button type="button" class="block w-full text-left px-4 py-2 hover:bg-zinc-50 dark:hover:bg-indigo-400/50"
                                wire:click="selectType({{ $s->id }})">
                                {{ $s->name }}
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>
            <flux:input label="{{ __('Base Rate (Q)') }}" type="number" step="0.01" wire:model="new_rate_amount" />
        </div>

        <div class="flex justify-end">
            <flux:button wire:click="addRate" variant="primary">{{ __('Add rate') }}</flux:button>
        </div>
    </div>

    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 divide-y divide-zinc-100 dark:divide-zinc-800">
        @forelse($this->rates as $rate)
            <div class="flex items-center justify-between px-4 py-3">
                <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $rate->procedureType->name }}</span>
                <div class="flex items-center gap-4">
                    <span class="font-mono">Q{{ number_format((float) $rate->base_rate, 2) }}</span>
                    <flux:button size="sm" variant="danger" wire:click="removeRate({{ $rate->id }})">{{ __('Delete') }}</flux:button>
                </div>
            </div>
        @empty
            <div class="px-4 py-6 text-center text-sm text-zinc-500 dark:text-zinc-400 italic">
                {{ __('No rates configured for this role yet.') }}
            </div>
        @endforelse
    </div>
</div>
