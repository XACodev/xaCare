<?php

use Illuminate\Support\Facades\Auth;

use function Livewire\Volt\{mount};

mount(function () {
    abort_unless(Auth::check(), 401);
    abort_unless((bool) Auth::user()->hasRole('admin'), 403);
    abort_if((bool) Auth::user()?->is_platform_admin, 403);
});

?>

@php
    $me = Auth::user();
    $hasQxlog = $me->hospital?->hasFeature('qxlog');
    $hasInsurance = $me->hospital?->hasFeature('insurance');
    $canManage = $me->can('settings.manage');

    $groups = [
        __('Hospital') => array_filter([
            $canManage ? ['label' => __('General Settings'), 'route' => 'settings.organization', 'icon' => 'building-office'] : null,
        ]),
        __('Espacios') => array_filter([
            $hasQxlog ? ['label' => __('Operating Rooms'), 'route' => 'settings.rooms', 'icon' => 'building-office-2'] : null,
            $canManage ? ['label' => __('Salas'), 'route' => 'settings.wards', 'icon' => 'rectangle-group'] : null,
            $canManage ? ['label' => __('Habitaciones'), 'route' => 'settings.hospital-rooms', 'icon' => 'squares-2x2'] : null,
        ]),
        __('Personal') => array_filter([
            ['label' => __('Mi Staff'), 'route' => 'users.index', 'icon' => 'user'],
            ['label' => __('Roles Custom'), 'route' => 'settings.roles.index', 'icon' => 'shield-check'],
            $hasQxlog ? ['label' => __('Surgical Roles'), 'route' => 'settings.roles', 'icon' => 'tag'] : null,
            $hasQxlog ? ['label' => __('Configure Instrumentists'), 'route' => 'pricing.instrumentists', 'icon' => 'users'] : null,
        ]),
        __('Catálogos') => array_filter([
            $canManage ? ['label' => __('Categorías de paciente'), 'route' => 'settings.patient-categories', 'icon' => 'tag'] : null,
            $me->hospital?->hasFeature('admissions_custom_form') ? ['label' => __('Tipos de ingreso'), 'route' => 'settings.admission-types', 'icon' => 'clipboard-document-list'] : null,
            $hasQxlog ? ['label' => __('Surgery Statuses'), 'route' => 'settings.statuses', 'icon' => 'flag'] : null,
            $hasQxlog ? ['label' => __('Instrumentist Pricing'), 'route' => 'pricing.settings', 'icon' => 'wrench'] : null,
            $hasQxlog ? ['label' => __('Pricing by Procedure'), 'route' => 'pricing.procedure-types', 'icon' => 'calculator'] : null,
        ]),
        __('Cuenta') => array_filter([
            $hasInsurance ? ['label' => __('Seguros'), 'route' => 'modules.insurance', 'icon' => 'shield-check'] : null,
            ['label' => __('Appearance'), 'route' => 'appearance.edit', 'icon' => 'swatch'],
        ]),
    ];

    $groups = array_filter($groups);
@endphp

<div class="max-w-5xl mx-auto p-4 space-y-6">
    <div>
        <div class="text-xs text-zinc-500 dark:text-zinc-400 mb-1">{{ __('Configurations') }}</div>
        <flux:heading size="xl">{{ __('Configurations') }}</flux:heading>
    </div>

    @foreach($groups as $groupName => $items)
        @if(count($items))
            <div class="space-y-3">
                <flux:subheading class="uppercase tracking-wide text-xs">{{ $groupName }}</flux:subheading>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    @foreach($items as $item)
                        <a href="{{ route($item['route']) }}" wire:navigate
                            class="flex items-center gap-3 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-4 hover:border-accent dark:hover:border-accent transition-colors">
                            <span class="size-9 rounded-lg bg-mist dark:bg-accent/10 text-accent-content dark:text-accent flex items-center justify-center flex-none">
                                <flux:icon :name="$item['icon']" size="sm" />
                            </span>
                            <span class="font-medium text-sm text-zinc-900 dark:text-zinc-100">{{ $item['label'] }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    @endforeach
</div>
