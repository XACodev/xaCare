<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">

<head>
    @include('partials.head')
</head>

<body class="min-h-screen bg-white dark:bg-zinc-800">
    <flux:header container class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
        <flux:sidebar.toggle
            class="lg:hidden ml-2 bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg"
            icon="bars-3" inset="left" />

        <flux:brand href="{{ route('dashboard') }}" name="xaCare" class="me-5 flex items-center rtl:space-x-reverse"
            wire:navigate>
            <x-slot name="logo" class="bg-accent text-accent-foreground">
                <x-app-logo-icon class="size-4 fill-none" />
            </x-slot>
        </flux:brand>

        <flux:navbar class="-mb-px max-lg:hidden">
            <flux:navbar.item icon="layout-grid" :href="route('dashboard')" :current="request()->routeIs('dashboard')"
                wire:navigate>
                {{ __('Dashboard') }}
            </flux:navbar.item>
            @if (auth()->user()->role === 'instrumentist' || auth()->user()->role === 'doctor' || auth()->user()->role === 'circulating')
                <flux:navbar.item icon="layout-grid" :href="route('procedures.create')"
                    :current="request()->routeIs('procedures.create')" wire:navigate>
                    {{ __('Register Procedure') }}
                </flux:navbar.item>
                <flux:navbar.item icon="layout-list" :href="route('instrumentist.payouts')"
                    :current="request()->routeIs('instrumentist.payouts')" wire:navigate>
                    {{ __('My Services') }}
                </flux:navbar.item>
            @endif
            @if(auth()->user()->role === 'admin')
                <flux:navbar.item icon="layout-grid" :href="route('payouts.create')"
                    :current="request()->routeIs('payouts.create')" wire:navigate>
                    {{ __('Realizar Pago') }}
                </flux:navbar.item>
                <flux:navbar.item icon="layout-grid" :href="route('payouts.index')"
                    :current="request()->routeIs('payouts.index')" wire:navigate>
                    {{ __('Historial de Pagos') }}
                </flux:navbar.item>
                <flux:navbar.item icon="layout-grid" :href="route('procedures.index')"
                    :current="request()->routeIs('procedures.index')" wire:navigate>
                    {{ __('Historial de Procedimientos') }}
                </flux:navbar.item>
            @endif
        </flux:navbar>

        <flux:spacer />

        <flux:navbar class="me-1.5 space-x-0.5 rtl:space-x-reverse py-0!">
            <flux:tooltip :content="__('Search')" position="bottom">
                <flux:navbar.item class="h-10 [&>div>svg]:size-5" icon="magnifying-glass" href="#"
                    :label="__('Search')" />
            </flux:tooltip>
            <flux:tooltip :content="__('Settings')" position="bottom">
                <flux:navbar.item :href="route('profile.edit')" icon="cog" wire:navigate>
                    {{ __('Settings') }}
                </flux:navbar.item>
            </flux:tooltip>
            <flux:tooltip :content="__('Log Out')" position="bottom">
                <form method="POST" action="{{ route('logout') }}" class="w-full">
                    @csrf
                    <flux:navbar.item as="button" type="submit" icon="arrow-right-start-on-rectangle" wire:navigate
                        class="w-full" data-test="logout-button">
                        {{ __('Log Out') }}
                    </flux:navbar.item>
                </form>
            </flux:tooltip>
            @if(auth()->user()->is_super_admin)
                <flux:tooltip :content="__('Users')" position="bottom">
                    <flux:navbar.item icon="user" :href="route('users.index')" :current="request()->routeIs('users.index')"
                        wire:navigate>
                        {{ __('Users') }}
                    </flux:navbar.item>
                </flux:tooltip>
                <flux:tooltip :content="__('Roles')" position="bottom">
                    <flux:navbar.item icon="user" :href="route('roles.index')" :current="request()->routeIs('roles.index')"
                        wire:navigate>
                        {{ __('Roles') }}
                    </flux:navbar.item>
                </flux:tooltip>
                <flux:tooltip :content="__('Permissions')" position="bottom">
                    <flux:navbar.item icon="user" :href="route('permissions.index')"
                        :current="request()->routeIs('permissions.index')" wire:navigate>
                        {{ __('Permissions') }}
                    </flux:navbar.item>
                </flux:tooltip>
                <flux:tooltip :content="__('Instrumentists')" position="bottom">
                    <flux:navbar.item icon="users" :href="route('pricing.instrumentists')"
                        :current="request()->routeIs('pricing.instrumentists')" wire:navigate>
                        {{ __('Instrumentists') }}
                    </flux:navbar.item>
                </flux:tooltip>
                <flux:tooltip :content="__('Pricing')" position="bottom">
                    <flux:navbar.item icon="wrench" :href="route('pricing.settings')"
                        :current="request()->routeIs('pricing.settings')" wire:navigate>
                        {{ __('Pricing') }}
                    </flux:navbar.item>
                </flux:tooltip>
                @can('settings.manage')
                    <flux:tooltip :content="__('General Settings')" position="bottom">
                        <flux:navbar.item icon="building-office" :href="route('settings.organization')"
                            :current="request()->routeIs('settings.organization')" wire:navigate>
                            {{ __('General Settings') }}
                        </flux:navbar.item>
                    </flux:tooltip>
                @endcan
            @endif
        </flux:navbar>

        <!-- Desktop User Menu -->
        <flux:dropdown position="top" align="end">
            <flux:profile class="cursor-pointer" :initials="auth()->user()->initials()" />

            <flux:menu>
                <flux:menu.radio.group>
                    <div class="p-0 text-sm font-normal">
                        <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                            <span class="relative flex h-8 w-8 shrink-0 overflow-hidden rounded-lg">
                                <span
                                    class="flex h-full w-full items-center justify-center rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white">
                                    {{ auth()->user()->initials() }}
                                </span>
                            </span>

                            <div class="grid flex-1 text-start text-sm leading-tight">
                                <span class="truncate font-semibold">{{ auth()->user()->name }}</span>
                                <span class="truncate text-xs">{{ auth()->user()->email }}</span>
                            </div>
                        </div>
                    </div>
                </flux:menu.radio.group>

                <flux:menu.separator />

                <!-- <flux:menu.radio.group>
                    <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>{{ __('Settings') }}
                    </flux:menu.item>
                </flux:menu.radio.group> -->

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

    <!-- Mobile Menu -->
    <flux:sidebar stashable sticky
        class="lg:hidden border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
        <flux:sidebar.toggle
            class="lg:hidden ml-2 bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg"
            icon="x-mark" />

        <a href="{{ route('dashboard') }}" class="ms-1 flex items-center space-x-2 rtl:space-x-reverse" wire:navigate>
            <x-app-logo />
        </a>

        <flux:navlist variant="outline">
            <flux:navlist.group :heading="__('Platform')">
                <flux:navlist.item icon="layout-grid" :href="route('dashboard')"
                    :current="request()->routeIs('dashboard')" wire:navigate>
                    {{ __('Dashboard') }}
                </flux:navlist.item>
                <flux:navlist.item icon="layout-grid" :href="route('procedures.create')"
                    :current="request()->routeIs('procedures.create')" wire:navigate>
                    {{ __('Registro de Procedimientos') }}
                </flux:navlist.item>
                <flux:navlist.item icon="layout-grid" :href="route('payouts.create')"
                    :current="request()->routeIs('payouts.create')" wire:navigate>
                    {{ __('Registro de Pago') }}
                </flux:navlist.item>
                <flux:navlist.item icon="layout-grid" :href="route('payouts.index')"
                    :current="request()->routeIs('payouts.index')" wire:navigate>
                    {{ __('Historial de Pagos') }}
                </flux:navlist.item>
            </flux:navlist.group>
        </flux:navlist>

        <flux:spacer />

        <flux:navlist variant="outline">
            <form method="POST" action="{{ route('logout') }}" class="w-full">
                @csrf
                <flux:navlist.item as="button" type="submit" icon="arrow-right-start-on-rectangle" wire:navigate
                    class="w-full" data-test="logout-button">
                    {{ __('Log Out') }}
                </flux:navlist.item>
            </form>
        </flux:navlist>
    </flux:sidebar>

    {{ $slot }}

    @fluxScripts
</body>

</html>