<?php

use App\Models\OrganizationSetting;
use Illuminate\Support\Facades\Auth;

use function Livewire\Volt\{state, mount, rules};

state([
    'org_name' => '',
    'voucher_legend' => '',
    'flat_default_rate' => 0,
    'success' => null,
]);

mount(function () {
    abort_unless(Auth::check(), 401);
    abort_unless((bool) Auth::user()->can('settings.manage'), 403);

    $s = OrganizationSetting::current();

    $this->org_name = $s->org_name;
    $this->voucher_legend = $s->voucher_legend;
    $this->flat_default_rate = (float) $s->flat_default_rate;
});

rules([
    'org_name' => ['required', 'string', 'max:255'],
    'voucher_legend' => ['required', 'string', 'max:1000'],
    'flat_default_rate' => ['required', 'numeric', 'min:0'],
]);

$save = function () {
    abort_unless((bool) Auth::user()->can('settings.manage'), 403);

    $data = $this->validate();

    OrganizationSetting::current()->update($data);

    $this->success = __('Settings saved.');
};

?>

<div class="max-w-6xl mx-auto p-4 space-y-6">
    <div class="mb-4">
        <flux:heading size="xl">{{ __('General Settings') }}</flux:heading>
        <flux:subheading>{{ __('Organization data used on printed documents') }}</flux:subheading>
    </div>

    @if($success)
        <div
            class="rounded-lg border border-green-200 bg-green-50 dark:bg-green-900/20 dark:border-green-800 px-4 py-3 text-green-800 dark:text-green-300">
            {{ $success }}
        </div>
    @endif

    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-6 space-y-6">
        <flux:input label="{{ __('Organization Name') }}" wire:model.live="org_name" clearable />

        <flux:textarea label="{{ __('Voucher Legend') }}" wire:model.live="voucher_legend" rows="3" />

        <flux:input label="{{ __('Flat Rate (Q)') }}" type="number" step="0.01" wire:model.live="flat_default_rate"
            clearable />

        <div class="pt-2 flex justify-end">
            <flux:button wire:click="save" variant="primary">
                {{ __('Save') }}
            </flux:button>
        </div>
    </div>
</div>
