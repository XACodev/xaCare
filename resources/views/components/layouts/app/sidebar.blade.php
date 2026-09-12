<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">

<head>
    @include('partials.head')
</head>

<body class="min-h-screen bg-slate-50 dark:bg-zinc-950">
    @php($me = auth()->user())
    @php($hasQxlog = $me?->hospital?->hasFeature('qxlog') || $me?->is_platform_admin)
    @php($hasQuotes = $hasQxlog && ($me?->hospital?->hasFeature('qxlog_quotes') || $me?->is_platform_admin))

    <flux:sidebar sticky stashable class="border-e border-zinc-200 bg-white dark:border-zinc-800 dark:bg-night flex flex-col w-72">
        <div class="shrink-0">
            <flux:sidebar.toggle
                class="lg:hidden ml-2 bg-accent dark:bg-accent border border-accent-content dark:border-accent-content rounded-lg"
                icon="x-mark" inset="left" />

            <flux:sidebar.brand href="{{ route('dashboard') }}" name="{{ config('app.name') }}"
                class="flex items-center rtl:space-x-reverse" wire:navigate>
                <x-slot name="logo" class="border-accent-content dark:border-accent-content">
                    <x-app-logo-icon class="size-6 fill-none" />
                </x-slot>
            </flux:sidebar.brand>
        </div>

        <div class="flex-1 overflow-y-auto min-h-0">
            <flux:navlist variant="outline" class="[&_[data-current]]:bg-mist! [&_[data-current]]:text-accent!">
                @if($me && $me->is_platform_admin)
                    <flux:navlist.group :heading="__('Plataforma')" class="grid">
                        <flux:navlist.item icon="squares-2x2" :href="route('platform.dashboard')"
                            :current="request()->routeIs('platform.*')" wire:navigate>
                            {{ __('Panel de Administrador') }}
                        </flux:navlist.item>
                    </flux:navlist.group>
                @elseif($me && $me->hasRole('admin'))
                    <flux:navlist.group :heading="__('Ingresos')" class="grid">
                        <flux:navlist.item icon="identification" :href="route('patients.index')"
                            :current="request()->routeIs('patients.*')" wire:navigate>
                            {{ __('Pacientes') }}
                        </flux:navlist.item>
                        <flux:navlist.item icon="inbox-arrow-down" :href="route('admissions.create')"
                            :current="request()->routeIs('admissions.*')" wire:navigate>
                            {{ __('Nuevo Ingreso') }}
                        </flux:navlist.item>
                    </flux:navlist.group>
                    @if($hasQxlog && $me->can('surgeries.view'))
                        <flux:navlist.group :heading="__('Surgeries')" class="grid">
                            <flux:navlist.item icon="calendar-days" :href="route('surgeries.board')"
                                :current="request()->routeIs('surgeries.board')" wire:navigate>
                                {{ __('Surgery Schedule') }}
                            </flux:navlist.item>
                        </flux:navlist.group>
                    @endif
                    @if($hasQuotes && ($me->can('surgeries.budget.view_total') || $me->can('surgeries.budget.view_own')))
                        <flux:navlist.group :heading="__('Cotizaciones')" class="grid">
                            <flux:navlist.item icon="document-text" :href="route('quotes.index')"
                                :current="request()->routeIs('quotes.*')" wire:navigate>
                                {{ __('Cotizaciones') }}
                            </flux:navlist.item>
                        </flux:navlist.group>
                    @endif
                    @if($hasQxlog)
                        <flux:navlist.group :heading="__('Payouts')" class="grid">
                            <flux:navlist.item icon="home" :href="route('payouts.create')"
                                :current="request()->routeIs('payouts.create')" wire:navigate>
                                {{ __('Make Payment') }}
                            </flux:navlist.item>
                        </flux:navlist.group>
                        <flux:navlist.group :heading="__('History')" class="grid">
                            <flux:navlist.item icon="layout-grid" :href="route('payouts.index')"
                                :current="request()->routeIs('payouts.index')" wire:navigate>
                                {{ __('Payment History') }}
                            </flux:navlist.item>
                            <flux:navlist.item icon="layout-grid" :href="route('procedures.index')"
                                :current="request()->routeIs('procedures.index')" wire:navigate>
                                {{ __('Procedure History') }}
                            </flux:navlist.item>
                        </flux:navlist.group>
                    @endif
                    <flux:navlist.group :heading="__('Reports')" class="grid">
                        <flux:navlist.item icon="chart-bar" :href="route('reports.procedures')"
                            :current="request()->routeIs('reports.*')" wire:navigate>
                            {{ __('Reportes') }}
                        </flux:navlist.item>
                    </flux:navlist.group>
                    <flux:navlist.group :heading="__('Configurations')" class="grid">
                        <flux:navlist.item icon="user" :href="route('users.index')"
                            :current="request()->routeIs('users.index')" wire:navigate>
                            {{ __('Mi Staff') }}
                        </flux:navlist.item>
                        @if($hasQxlog)
                            <flux:navlist.item icon="users" :href="route('pricing.instrumentists')"
                                :current="request()->routeIs('pricing.instrumentists')" wire:navigate>
                                {{ __('Configure Instrumentists') }}
                            </flux:navlist.item>
                            <flux:navlist.item icon="wrench" :href="route('pricing.settings')"
                                :current="request()->routeIs('pricing.settings')" wire:navigate>
                                {{ __('Instrumentist Pricing') }}
                            </flux:navlist.item>
                            <flux:navlist.item icon="calculator" :href="route('pricing.procedure-types')"
                                :current="request()->routeIs('pricing.procedure-types')" wire:navigate>
                                {{ __('Pricing by Procedure') }}
                            </flux:navlist.item>
                            <flux:navlist.item icon="tag" :href="route('settings.roles')"
                                :current="request()->routeIs('settings.roles')" wire:navigate>
                                {{ __('Surgical Roles') }}
                            </flux:navlist.item>
                            <flux:navlist.item icon="flag" :href="route('settings.statuses')"
                                :current="request()->routeIs('settings.statuses')" wire:navigate>
                                {{ __('Surgery Statuses') }}
                            </flux:navlist.item>
                            <flux:navlist.item icon="building-office-2" :href="route('settings.rooms')"
                                :current="request()->routeIs('settings.rooms')" wire:navigate>
                                {{ __('Operating Rooms') }}
                            </flux:navlist.item>
                        @endif
                        @can('settings.manage')
                            <flux:navlist.item icon="building-office" :href="route('settings.organization')"
                                :current="request()->routeIs('settings.organization')" wire:navigate>
                                {{ __('General Settings') }}
                            </flux:navlist.item>
                            <flux:navlist.item icon="rectangle-group" :href="route('settings.wards')"
                                :current="request()->routeIs('settings.wards')" wire:navigate>
                                {{ __('Salas') }}
                            </flux:navlist.item>
                            <flux:navlist.item icon="squares-2x2" :href="route('settings.hospital-rooms')"
                                :current="request()->routeIs('settings.hospital-rooms')" wire:navigate>
                                {{ __('Habitaciones') }}
                            </flux:navlist.item>
                        @endcan
                        <flux:navlist.item icon="shield-check" :href="route('settings.roles.index')"
                            :current="request()->routeIs('settings.roles.index')" wire:navigate>
                            {{ __('Roles Custom') }}
                        </flux:navlist.item>
                        @if($me?->hospital?->hasFeature('insurance'))
                            <flux:navlist.item icon="shield-check" :href="route('modules.insurance')"
                                :current="request()->routeIs('modules.insurance')" wire:navigate>
                                {{ __('Seguros') }}
                            </flux:navlist.item>
                        @endif
                    </flux:navlist.group>
                @elseif($me && $me->hasRole('instrumentist') && $hasQxlog)
                    <flux:navlist.group :heading="__('Procedures')" class="grid">
                        <flux:navlist.item icon="clipboard-document-list" :href="route('procedures.create')"
                            :current="request()->routeIs('procedures.create')" wire:navigate>
                            {{ __('Register Procedure') }}
                        </flux:navlist.item>
                    </flux:navlist.group>
                    @can('surgeries.view')
                        <flux:navlist.group :heading="__('Surgeries')" class="grid">
                            <flux:navlist.item icon="calendar-days" :href="route('surgeries.board')"
                                :current="request()->routeIs('surgeries.board')" wire:navigate>
                                {{ __('Surgery Schedule') }}
                            </flux:navlist.item>
                        </flux:navlist.group>
                    @endcan
                    <flux:navlist.group :heading="__('History')" class="grid">
                        <flux:navlist.item icon="queue-list" :href="route('instrumentist.payouts')"
                            :current="request()->routeIs('instrumentist.payouts')" wire:navigate>
                            {{ __('My Procedures') }}
                        </flux:navlist.item>
                    </flux:navlist.group>
                @endif
            </flux:navlist>
        </div>

        <div class="shrink-0 border-t border-zinc-200 dark:border-zinc-800 p-2">
            <div class="flex items-center gap-1">
                <flux:dropdown class="flex-1 min-w-0" position="bottom" align="start">
                    <button type="button"
                        class="group flex items-center gap-2 w-full min-w-0 rounded-lg p-1.5 hover:bg-zinc-100 dark:hover:bg-zinc-800 text-start"
                        data-test="sidebar-menu-button">
                        <span
                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white">
                            {{ $me?->initials() }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-sm font-semibold text-zinc-900 dark:text-zinc-100"
                                title="{{ $me?->name }}">{{ $me?->name }}</div>
                            <div class="truncate text-xs text-zinc-500 dark:text-zinc-400">
                                {{ $me?->getRoleNames()->first() ?? ($me?->is_platform_admin ? __('Platform Admin') : '') }}
                            </div>
                        </div>
                        <flux:icon.chevrons-up-down
                            class="size-4 shrink-0 text-zinc-400 group-hover:text-zinc-600 dark:group-hover:text-zinc-300" />
                    </button>

                    <flux:menu class="w-[220px]">
                        <flux:menu.radio.group>
                            <div class="p-0 text-sm font-normal">
                                <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                    <span class="relative flex h-8 w-8 shrink-0 overflow-hidden rounded-lg">
                                        <span
                                            class="flex h-full w-full items-center justify-center rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white">
                                            {{ $me?->initials() }}
                                        </span>
                                    </span>

                                    <div class="grid flex-1 text-start text-sm leading-tight">
                                        <span class="truncate font-semibold">{{ $me?->name }}</span>
                                        <span class="truncate text-xs text-zinc-500 dark:text-zinc-400">
                                            {{ $me?->getRoleNames()->first() ?? ($me?->is_platform_admin ? __('Platform Admin') : '') }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </flux:menu.radio.group>

                        <flux:menu.separator />

                        <flux:menu.radio.group>
                            <flux:menu.item :href="route('profile.edit')" icon="user" wire:navigate>{{ __('Profile') }}
                            </flux:menu.item>
                            <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>{{ __('Settings') }}
                            </flux:menu.item>
                        </flux:menu.radio.group>

                        <flux:menu.separator />

                        <form method="POST" action="{{ route('logout') }}" class="w-full">
                            @csrf
                            <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full"
                                data-test="logout-button">
                                {{ __('Log Out') }}
                            </flux:menu.item>
                        </form>
                    </flux:menu>
                </flux:dropdown>

                <form method="POST" action="{{ route('logout') }}" class="shrink-0">
                    @csrf
                    <flux:button as="button" type="submit" icon="arrow-right-start-on-rectangle" variant="ghost"
                        size="sm"
                        class="text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100"
                        data-test="sidebar-logout-button" />
                </form>
            </div>
        </div>
    </flux:sidebar>

    <!-- Mobile User Menu -->
    <flux:header class="lg:hidden">
        <flux:sidebar.toggle
            class="lg:hidden ml-2 bg-accent dark:bg-accent border border-accent-content dark:border-accent-content rounded-lg"
            icon="bars-3" inset="left" />

        <flux:spacer />

        <flux:dropdown position="top" align="end">
            <flux:profile :name="$me?->name" :initials="$me?->initials()" icon-trailing="chevron-down">
                <x-slot name="subtitle">
                    {{ $me?->getRoleNames()->first() ?? ($me?->is_platform_admin ? __('Platform Admin') : '') }}
                </x-slot>
            </flux:profile>

            <flux:menu>
                <flux:menu.radio.group>
                    <div class="p-0 text-sm font-normal">
                        <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                            <span class="relative flex h-8 w-8 shrink-0 overflow-hidden rounded-lg">
                                <span
                                    class="flex h-full w-full items-center justify-center rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white">
                                    {{ $me?->initials() }}
                                </span>
                            </span>

                            <div class="grid flex-1 text-start text-sm leading-tight">
                                <span class="truncate font-semibold">{{ $me?->name }}</span>
                                <span class="truncate text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ $me?->getRoleNames()->first() ?? ($me?->is_platform_admin ? __('Platform Admin') : '') }}
                                </span>
                            </div>
                        </div>
                    </div>
                </flux:menu.radio.group>

                <flux:menu.separator />

                <flux:menu.radio.group>
                    <flux:menu.item :href="route('profile.edit')" icon="user" wire:navigate>{{ __('Profile') }}
                    </flux:menu.item>
                    <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>{{ __('Settings') }}
                    </flux:menu.item>
                </flux:menu.radio.group>

                <flux:menu.separator />

                <form method="POST" action="{{ route('logout') }}" class="w-full">
                    @csrf
                    <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full"
                        data-test="logout-button">
                        {{ __('Log Out') }}
                    </flux:menu.item>
                </form>
            </flux:menu>
        </flux:dropdown>
    </flux:header>

    {{ $slot }}

    @fluxScripts
</body>

</html>
