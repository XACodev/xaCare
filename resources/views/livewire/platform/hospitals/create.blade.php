<?php

use App\Models\Hospital;
use App\Services\HospitalPlanService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

use function Livewire\Volt\{state, mount, rules, layout};

layout('components.layouts.platform');

state([
    'name' => '',
    'plan' => 'basic',
    'success_message' => null,
]);

mount(function () {
    abort_unless(Auth::check() && Auth::user()->is_platform_admin, 403);
});

rules([
    'name' => ['required', 'string', 'max:255'],
    'plan' => ['required', 'string', Rule::in(array_keys(config('billing.plans')))],
]);

$save = function () {
    abort_unless((bool) Auth::user()->is_platform_admin, 403);

    $data = $this->validate();

    $slug = Str::slug($data['name']);
    $baseSlug = $slug;
    $i = 1;
    while (Hospital::where('slug', $slug)->exists()) {
        $slug = $baseSlug.'-'.++$i;
    }

    $hospital = Hospital::create([
        'name' => $data['name'],
        'slug' => $slug,
        'plan' => $data['plan'],
        'features' => [],
        'is_active' => true,
    ]);

    app(HospitalPlanService::class)->startTrial($hospital, $data['plan']);

    $this->success_message = __('Hospital created.');
    $this->reset(['name', 'plan']);
};

?>

<div class="max-w-2xl mx-auto p-4 space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('New Hospital') }}</flux:heading>
            <flux:subheading>{{ __('Solo Administrador de plataforma') }}</flux:subheading>
        </div>
        <flux:link href="{{ route('platform.hospitals.index') }}" class="text-sm">{{ __('Back') }}</flux:link>
    </div>

    @if($success_message)
        <flux:callout variant="success" icon="check-circle" heading="{{ $success_message }}" />
    @endif

    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-6 space-y-6">
        <flux:input wire:model.live="name" label="{{ __('Name') }}" placeholder="{{ __('Hospital name') }}" />

        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">{{ __('Plan') }}</label>
        <select wire:model.live="plan"
            class="w-full rounded-lg border-zinc-200 dark:border-zinc-800 bg-indigo-50 dark:bg-zinc-700/60 text-zinc-900 dark:text-zinc-100 focus:ring-0 focus:border-zinc-500 p-2.5">
            @foreach(config('billing.plans') as $planKey => $plan)
                <option value="{{ $planKey }}">{{ $plan['name'] }}</option>
            @endforeach
        </select>

        <div class="pt-2 flex justify-end">
            <flux:button variant="primary" wire:click="save" class="w-full sm:w-auto">
                {{ __('Save') }}
            </flux:button>
        </div>
    </div>
</div>
