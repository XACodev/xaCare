<flux:sidebar sticky stashable class="border-e border-zinc-200 bg-white dark:border-zinc-800 dark:bg-night flex flex-col w-72">
    <div class="shrink-0">
        <flux:sidebar.toggle
            class="lg:hidden ml-2 bg-accent dark:bg-accent border border-accent-content dark:border-accent-content rounded-lg"
            icon="x-mark" inset="left" />

        <flux:sidebar.brand href="{{ route('platform.dashboard') }}" name="{{ config('app.name') }}"
            class="flex items-center rtl:space-x-reverse" wire:navigate>
            <x-slot name="logo" class="border-accent-content dark:border-accent-content">
                <x-app-logo-icon class="size-6 fill-none" />
            </x-slot>
        </flux:sidebar.brand>
    </div>

    @php($me = auth()->user())

    <div class="flex-1 overflow-y-auto min-h-0">
        <flux:navlist variant="outline" class="[&_[data-current]]:bg-mist! [&_[data-current]]:text-accent!">
            <flux:navlist.group :heading="__('Plataforma')" class="grid">
                <flux:navlist.item icon="chart-bar" :href="route('platform.dashboard')"
                    :current="request()->routeIs('platform.dashboard')" wire:navigate>
                    {{ __('Dashboard') }}
                </flux:navlist.item>
                <flux:navlist.item icon="building-office-2" :href="route('platform.hospitals.index')"
                    :current="request()->routeIs('platform.hospitals.*')" wire:navigate>
                    {{ __('Hospitales') }}
                </flux:navlist.item>
                <flux:navlist.item icon="clock" :href="route('platform.activity.index')"
                    :current="request()->routeIs('platform.activity.*')" wire:navigate>
                    {{ __('Actividad') }}
                </flux:navlist.item>
                <flux:navlist.item icon="banknotes" :href="route('platform.billing.reports')"
                    :current="request()->routeIs('platform.billing.*')" wire:navigate>
                    {{ __('Reportes de facturación') }}
                </flux:navlist.item>
                <flux:navlist.item icon="chart-bar-square" :href="route('platform.reports.hospitals')"
                    :current="request()->routeIs('platform.reports.*')" wire:navigate>
                    {{ __('Reportes operativos') }}
                </flux:navlist.item>
                <flux:navlist.item icon="users" :href="route('platform.admins.index')"
                    :current="request()->routeIs('platform.admins.*')" wire:navigate>
                    {{ __('Administradores') }}
                </flux:navlist.item>
            </flux:navlist.group>

            <flux:navlist.group :heading="__('Control de acceso')" class="grid">
                <flux:navlist.item icon="user" :href="route('platform.roles.index')"
                    :current="request()->routeIs('platform.roles.index')" wire:navigate>
                    {{ __('Roles') }}
                </flux:navlist.item>
                <flux:navlist.item icon="key" :href="route('platform.permissions.index')"
                    :current="request()->routeIs('platform.permissions.index')" wire:navigate>
                    {{ __('Permisos') }}
                </flux:navlist.item>
            </flux:navlist.group>
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
                            {{ $me?->is_platform_admin ? __('Platform Admin') : '' }}
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
                                        {{ $me?->is_platform_admin ? __('Platform Admin') : '' }}
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

{{ $slot }}
