<?php

use App\Models\Hospital;
use Illuminate\Support\Facades\Auth;

use function Livewire\Volt\{state, mount, rules};

state([
    'hospital' => null,
    'name' => '',
    'plan' => 'basic',
    'is_active' => true,
    'success_message' => null,
]);

mount(function (string|int $hospital) {
    abort_unless(Auth::check() && Auth::user()->is_super_admin, 403);

    $h = Hospital::findOrFail($hospital);

    $this->hospital = $h;
    $this->name = $h->name;
    $this->plan = $h->plan;
    $this->is_active = $h->is_active;
});

rules([
    'name' => ['required', 'string', 'max:255'],
    'plan' => ['required', 'string', 'max:50'],
    'is_active' => ['boolean'],
]);

$save = function () {
    abort_unless((bool) Auth::user()->is_super_admin, 403);

    $data = $this->validate();

    $this->hospital->update($data);

    $this->success_message = __('Hospital updated.');
};

?>

<div class="max-w-2xl mx-auto p-4 space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Edit Hospital') }}</flux:heading>
            <flux:subheading>{{ __('Only Super Admin') }}</flux:subheading>
        </div>
        <flux:link href="{{ route('hospitals.index') }}" class="text-sm">{{ __('Back') }}</flux:link>
    </div>

    @if($success_message)
        <flux:callout variant="success" icon="check-circle" heading="{{ $success_message }}" />
    @endif

    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-6 space-y-6">
        <flux:input wire:model.live="name" label="{{ __('Name') }}" />

        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">{{ __('Plan') }}</label>
        <select wire:model.live="plan"
            class="w-full rounded-lg border-zinc-200 dark:border-zinc-800 bg-indigo-50 dark:bg-zinc-700/60 text-zinc-900 dark:text-zinc-100 focus:ring-0 focus:border-zinc-500 p-2.5">
            <option value="basic">{{ __('Basic') }}</option>
            <option value="pro">{{ __('Pro') }}</option>
        </select>

        <flux:checkbox wire:model.live="is_active" label="{{ __('Active') }}" />

        <div class="pt-2 flex justify-end">
            <flux:button variant="primary" wire:click="save" class="w-full sm:w-auto">
                {{ __('Save') }}
            </flux:button>
        </div>
    </div>
</div>
