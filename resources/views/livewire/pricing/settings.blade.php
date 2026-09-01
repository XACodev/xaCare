<?php

use App\Models\PricingSetting;
use Illuminate\Support\Facades\Auth;

use function Livewire\Volt\{state, mount, rules};

state([
    'default_rate' => 0,
    'video_rate' => 0,
    'night_rate' => 0,
    'long_case_rate' => 0,
    'long_case_threshold_minutes' => 0,
    'night_start' => '00:00',
    'night_end' => '00:00',
    'success' => null,
]);

mount(function () {
    abort_unless(Auth::check(), 401);
    abort_unless((bool) Auth::user()->can('pricing.manage'), 403);

    $s = PricingSetting::current();

    $this->default_rate = (float) $s->default_rate;
    $this->video_rate = (float) $s->video_rate;
    $this->night_rate = (float) $s->night_rate;
    $this->long_case_rate = (float) $s->long_case_rate;
    $this->long_case_threshold_minutes = (int) $s->long_case_threshold_minutes;
    $this->night_start = (string) $s->night_start;
    $this->night_end = (string) $s->night_end;
});

rules([
    'default_rate' => ['required', 'numeric', 'min:0'],
    'video_rate' => ['required', 'numeric', 'min:0'],
    'night_rate' => ['required', 'numeric', 'min:0'],
    'long_case_rate' => ['required', 'numeric', 'min:0'],
    'long_case_threshold_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
    'night_start' => ['required', 'date_format:H:i'],
    'night_end' => ['required', 'date_format:H:i'],
]);

$save = function () {
    abort_unless((bool) Auth::user()->can('pricing.manage'), 403);

    $data = $this->validate();

    PricingSetting::current()->update($data);

    $this->success = __('Prices saved.');
};

?>

<div class="max-w-6xl mx-auto p-4 space-y-6">
    <div class="mb-4">
        <flux:heading size="xl">{{ __('Global Pricing') }}</flux:heading>
        <flux:subheading>{{ __('Applies to special scheme instrumentists') }}</flux:subheading>
    </div>

    @if($success)
        <div
            class="rounded-lg border border-green-200 bg-green-50 dark:bg-green-900/20 dark:border-green-800 px-4 py-3 text-green-800 dark:text-green-300">
            {{ $success }}
        </div>
    @endif

    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-6 space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <flux:input label="{{ __('Base Rate (Q)') }}" type="number" step="0.01" wire:model.live="default_rate"
                clearable />

            <flux:input label="{{ __('Video Surgery (Q)') }}" type="number" step="0.01" wire:model.live="video_rate"
                clearable />

            <flux:input label="{{ __('Night Rate (Q)') }}" type="number" step="0.01" wire:model.live="night_rate"
                clearable />

            <flux:input label="{{ __('Long Procedure (Q)') }}" type="number" step="0.01"
                wire:model.live="long_case_rate" clearable />

            <flux:input label="{{ __('Long Procedure Threshold (min)') }}" type="number" step="1"
                wire:model.live="long_case_threshold_minutes" clearable />

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <flux:input label="{{ __('Night Start Time') }}" type="time" wire:model.live="night_start" clearable />
                <flux:input label="{{ __('Night End Time') }}" type="time" wire:model.live="night_end" clearable />
            </div>
        </div>

        <div class="pt-2 flex justify-end">
            <flux:button wire:click="save" variant="primary">
                {{ __('Save') }}
            </flux:button>
        </div>
    </div>
</div>