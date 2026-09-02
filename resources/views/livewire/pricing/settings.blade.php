<?php

use App\Models\RateModifier;
use App\Models\RoleRate;
use App\Models\SurgicalRole;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

use function Livewire\Volt\{state, computed, mount, rules};

state([
    'selected_role_id' => null,
    'base_rate' => 0,
    // user_id: optional query param (?user_id=) that turns this same component into the
    // per-medico override editor instead of the hospital-wide default editor. See Step 3
    // in pricing/instrumentist.blade.php for the "Configurar tarifario" link that sets it.
    'user_id' => null,
    'modifier_name' => '',
    'modifier_trigger_type' => RateModifier::TRIGGER_MANUAL_TOGGLE,
    'modifier_trigger_hour_start' => '',
    'modifier_trigger_hour_end' => '',
    'modifier_trigger_minutes' => 60,
    'modifier_amount' => 0,
]);

rules([
    'selected_role_id' => ['required', 'integer', 'exists:surgical_roles,id'],
    'base_rate' => ['required', 'numeric', 'min:0'],
]);

mount(function () {
    abort_unless(Auth::check(), 401);
    abort_unless((bool) Auth::user()->can('pricing.manage'), 403);

    // Follows the same `request()->integer(...)` query-param convention used by
    // payouts/create.blade.php's `payee_id` preselection, rather than a typed mount()
    // parameter, for consistency across Volt components in this codebase.
    $requestedUserId = request()->integer('user_id');
    if ($requestedUserId) {
        $this->user_id = User::query()->findOrFail($requestedUserId)->id;
    }

    $this->selected_role_id = SurgicalRole::query()->where('active', true)->orderBy('sort_order')->value('id');
    $this->loadDefaultRate();
});

$roles = computed(fn () => SurgicalRole::query()->where('active', true)->orderBy('sort_order')->get());

$targetUser = computed(fn () => $this->user_id ? User::query()->find($this->user_id) : null);

$default_rate = computed(function () {
    $query = RoleRate::query()
        ->where('surgical_role_id', $this->selected_role_id)
        ->whereNull('procedure_type');

    $query = $this->user_id ? $query->where('user_id', $this->user_id) : $query->whereNull('user_id');

    // Defensive check: the (surgical_role_id, user_id, procedure_type) unique index does not
    // actually prevent duplicate rows here because MySQL/SQLite treat NULL as distinct in
    // unique indexes. If duplicates already exist (e.g. a prior data issue), surface it instead
    // of silently picking one arbitrarily.
    if ((clone $query)->count() > 1) {
        logger()->warning('Duplicate RoleRate rows found for the same role/user/procedure_type scope.', [
            'surgical_role_id' => $this->selected_role_id,
            'user_id' => $this->user_id,
        ]);
    }

    return $query->first();
});

$modifiers = computed(function () {
    return $this->default_rate
        ? RateModifier::query()->where('role_rate_id', $this->default_rate->id)->orderBy('sort_order')->get()
        : collect();
});

$loadDefaultRate = function () {
    $this->base_rate = (float) ($this->default_rate?->base_rate ?? 0);
};

$selectRole = function (int $roleId) {
    $this->selected_role_id = $roleId;
    $this->loadDefaultRate();
};

$saveBaseRate = function () {
    abort_unless((bool) Auth::user()->can('pricing.manage'), 403);
    $this->validate();

    RoleRate::updateOrCreate(
        ['surgical_role_id' => $this->selected_role_id, 'user_id' => $this->user_id, 'procedure_type' => null],
        ['base_rate' => $this->base_rate, 'active' => true],
    );

    unset($this->default_rate);
};

$addModifier = function () {
    abort_unless((bool) Auth::user()->can('pricing.manage'), 403);

    $this->validate([
        'modifier_name' => ['required', 'string', 'max:255'],
        'modifier_trigger_type' => ['required', 'in:'.implode(',', [
            RateModifier::TRIGGER_TIME_WINDOW,
            RateModifier::TRIGGER_DURATION_GTE,
            RateModifier::TRIGGER_MANUAL_TOGGLE,
        ])],
        'modifier_amount' => ['required', 'numeric'],
        'modifier_trigger_hour_start' => ['required_if:modifier_trigger_type,'.RateModifier::TRIGGER_TIME_WINDOW, 'nullable', 'date_format:H:i'],
        'modifier_trigger_hour_end' => ['required_if:modifier_trigger_type,'.RateModifier::TRIGGER_TIME_WINDOW, 'nullable', 'date_format:H:i'],
        'modifier_trigger_minutes' => ['required_if:modifier_trigger_type,'.RateModifier::TRIGGER_DURATION_GTE, 'nullable', 'integer', 'min:1'],
    ]);

    if (! $this->default_rate) {
        $this->saveBaseRate();
    }

    $triggerConfig = match ($this->modifier_trigger_type) {
        RateModifier::TRIGGER_TIME_WINDOW => ['start' => $this->modifier_trigger_hour_start, 'end' => $this->modifier_trigger_hour_end],
        RateModifier::TRIGGER_DURATION_GTE => ['minutes' => (int) $this->modifier_trigger_minutes],
        default => [],
    };

    RateModifier::create([
        'role_rate_id' => $this->default_rate->id,
        'name' => $this->modifier_name,
        'trigger_type' => $this->modifier_trigger_type,
        'trigger_config' => $triggerConfig,
        'rate_type' => RateModifier::RATE_FIXED_AMOUNT,
        'amount' => $this->modifier_amount,
        'active' => true,
    ]);

    $this->reset(['modifier_name', 'modifier_trigger_hour_start', 'modifier_trigger_hour_end', 'modifier_amount']);
    $this->modifier_trigger_type = RateModifier::TRIGGER_MANUAL_TOGGLE;
    $this->modifier_trigger_minutes = 60;

    unset($this->modifiers);
};

$removeModifier = function (int $id) {
    abort_unless((bool) Auth::user()->can('pricing.manage'), 403);
    RateModifier::where('id', $id)->delete();

    unset($this->modifiers);
};

?>

<div class="max-w-6xl mx-auto p-4 space-y-6">
    <div class="mb-4">
        <flux:heading size="xl">
            {{ $this->targetUser ? __('Rate for :name', ['name' => $this->targetUser->name]) : __('Hospital Pricing Defaults') }}
        </flux:heading>
        <flux:subheading>
            {{ $this->targetUser ? __('Overrides the hospital default for this user') : __('Configure the default rate per role') }}
        </flux:subheading>
    </div>

    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-6 space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <flux:select label="{{ __('Role') }}" wire:model.live="selected_role_id"
                wire:change="selectRole($event.target.value)">
                @foreach($this->roles as $role)
                    <flux:select.option value="{{ $role->id }}">{{ $role->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:input label="{{ __('Base Rate (Q)') }}" type="number" step="0.01" wire:model="base_rate" clearable />
        </div>

        <div class="pt-2 flex justify-end">
            <flux:button wire:click="saveBaseRate" variant="primary">
                {{ __('Save') }}
            </flux:button>
        </div>
    </div>

    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-6 space-y-4">
        <flux:heading size="lg">{{ __('Modifiers') }}</flux:heading>

        <div class="space-y-2">
            @forelse($this->modifiers as $m)
                <div
                    class="flex items-center justify-between p-3 rounded-lg border border-zinc-100 dark:border-zinc-800">
                    <div>
                        <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $m->name }}</div>
                        <div class="text-sm text-zinc-500">
                            {{ $m->trigger_type }} &middot; {{ __('Q') }}{{ number_format((float) $m->amount, 2) }}
                        </div>
                    </div>
                    <flux:button size="sm" variant="danger" wire:click="removeModifier({{ $m->id }})">
                        {{ __('Delete') }}
                    </flux:button>
                </div>
            @empty
                <div class="text-sm text-zinc-500 dark:text-zinc-400 italic">
                    {{ __('No modifiers yet.') }}
                </div>
            @endforelse
        </div>

        <div class="pt-4 border-t border-zinc-100 dark:border-zinc-800 grid grid-cols-1 md:grid-cols-2 gap-4">
            <flux:input label="{{ __('Name') }}" wire:model="modifier_name" />

            <flux:select label="{{ __('Trigger') }}" wire:model.live="modifier_trigger_type">
                <flux:select.option value="{{ \App\Models\RateModifier::TRIGGER_MANUAL_TOGGLE }}">
                    {{ __('Manual toggle') }}
                </flux:select.option>
                <flux:select.option value="{{ \App\Models\RateModifier::TRIGGER_TIME_WINDOW }}">
                    {{ __('Time window') }}
                </flux:select.option>
                <flux:select.option value="{{ \App\Models\RateModifier::TRIGGER_DURATION_GTE }}">
                    {{ __('Duration threshold') }}
                </flux:select.option>
            </flux:select>

            <flux:input label="{{ __('Amount (Q)') }}" type="number" step="0.01" wire:model="modifier_amount" />

            @if($modifier_trigger_type === \App\Models\RateModifier::TRIGGER_TIME_WINDOW)
                <div class="grid grid-cols-2 gap-4">
                    <flux:input label="{{ __('Start') }}" type="time" wire:model="modifier_trigger_hour_start" />
                    <flux:input label="{{ __('End') }}" type="time" wire:model="modifier_trigger_hour_end" />
                </div>
            @elseif($modifier_trigger_type === \App\Models\RateModifier::TRIGGER_DURATION_GTE)
                <flux:input label="{{ __('Minutes') }}" type="number" step="1" wire:model="modifier_trigger_minutes" />
            @endif
        </div>

        <div class="pt-2 flex justify-end">
            <flux:button wire:click="addModifier" variant="primary">
                {{ __('Add modifier') }}
            </flux:button>
        </div>
    </div>
</div>
