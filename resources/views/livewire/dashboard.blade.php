<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Modules\QxLog\Models\SurgicalCase;
use App\Modules\QxLog\Models\PayoutBatch;
use Illuminate\Support\Facades\Auth;

new #[Layout('components.layouts.app')] #[Title('Dashboard')] class extends Component {
    public bool $showEarnings = false;

    public function toggleEarnings(): void
    {
        $this->showEarnings = !$this->showEarnings;
    }

    public function goToRapidAdmission(): mixed
    {
        return redirect()->route('admissions.create')->with('rapid', true);
    }

    public function mount(): void
    {
        if (Auth::user()?->is_platform_admin) {
            // Sin `navigate: true` a propósito: `app` y `platform` son layouts con
            // `<body>` completamente distintos (otro sidebar), y el morphing SPA de
            // wire:navigate no puede reconciliarlos -- deja la página en blanco.
            $this->redirect(route('platform.dashboard'));
        }
    }

    public function with(): array
    {
        $user = Auth::user();
        $stats = [];

        if (!$user) {
            return [
                'stats' => [],
                'user' => null,
                'hasQxlog' => false,
                'greeting' => '',
            ];
        }

        $firstName = \Illuminate\Support\Str::of($user->name)->before(' ')->toString();
        $hour = now()->hour;
        $greeting = match (true) {
            $hour < 12 => __('Buenos días, :name', ['name' => $firstName]),
            $hour < 19 => __('Buenas tardes, :name', ['name' => $firstName]),
            default => __('Buenas noches, :name', ['name' => $firstName]),
        };

        $hasQxlog = $user->hospital?->hasFeature('qxlog') || $user->is_platform_admin;

        if ($hasQxlog && $user->hasRole('admin')) {
            $stats = [
                'total_procedures' => \App\Modules\QxLog\Models\SurgicalCase::count(),
                'pending_procedures' => \App\Modules\QxLog\Models\SurgicalAssignment::where('status', 'pending')->count(),
                'total_paid' => \App\Modules\QxLog\Models\PayoutBatch::where('status', '!=', 'void')->sum('total_amount'),
            ];
        } elseif ($hasQxlog) {
            $stats = [
                'total_earnings' => \App\Modules\QxLog\Models\SurgicalAssignment::where('user_id', $user->id)->where('status', 'paid')->sum('calculated_amount'),
                'pending_earnings' => \App\Modules\QxLog\Models\SurgicalAssignment::where('user_id', $user->id)->where('status', 'pending')->sum('calculated_amount'),
                'procedures_count' => \App\Modules\QxLog\Models\SurgicalAssignment::where('user_id', $user->id)->count(),
            ];
        }

        return [
            'stats' => $stats,
            'user' => $user,
            'hasQxlog' => $hasQxlog,
            'greeting' => $greeting,
        ];
    }

}; ?>

    <div class="flex h-full w-full flex-1 flex-col gap-6 rounded-xl">

        <!-- Header -->
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <div class="text-xs text-zinc-500 dark:text-zinc-400 mb-1.5">
                    {{ ucfirst(now()->translatedFormat('D d M Y')) }}
                    @if ($user->shift_label)
                        · {{ $user->shift_label }}
                    @endif
                </div>
                <h1 class="text-2xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-50">
                    {{ $greeting }}
                </h1>
            </div>
            @if($hasQxlog && $user->hasRole('admin'))
                <div class="flex gap-2.5">
                    <a href="{{ route('admissions.create') }}" wire:navigate
                        class="h-10 px-4 rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-900 flex items-center gap-2 text-sm font-medium text-zinc-700 dark:text-zinc-200 hover:border-zinc-400 dark:hover:border-zinc-600 transition-colors">
                        <flux:icon.plus class="size-4" />
                        {{ __('Nuevo ingreso') }}
                    </a>
                    <button type="button" wire:click="goToRapidAdmission"
                        class="h-10 px-4 rounded-lg bg-urgent-soft border border-urgent/25 text-urgent flex items-center gap-2 text-sm font-semibold hover:bg-urgent/10 transition-colors">
                        <span class="size-2 rounded-full bg-urgent"></span>
                        {{ __('Ingreso rápido') }}
                    </button>
                </div>
            @endif
        </div>

        <!-- Stats Section -->
        @if($user && $user->hasRole('admin') && $hasQxlog)
            <div class="grid gap-3.5 md:grid-cols-3">
                <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                    <dt class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Total Procedimientos') }}</dt>
                    <dd class="mt-1.5 text-3xl font-semibold tracking-tight tabular-nums text-zinc-900 dark:text-zinc-50">
                        {{ $stats['total_procedures'] }}</dd>
                </div>
                <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                    <dt class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Pendientes de Pago') }}</dt>
                    <dd class="mt-1.5 text-3xl font-semibold tracking-tight tabular-nums text-zinc-900 dark:text-zinc-50">
                        {{ $stats['pending_procedures'] }}</dd>
                </div>
                <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                    <dt class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Total Pagado') }}</dt>
                    <dd class="mt-1.5 text-3xl font-semibold tracking-tight tabular-nums text-zinc-900 dark:text-zinc-50">
                        Q{{ number_format($stats['total_paid'], 2) }}</dd>
                </div>
            </div>
        @elseif($user && $user->hasRole('instrumentist') && $hasQxlog)
            <div class="grid gap-3.5 md:grid-cols-3">
                <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="flex items-center justify-between">
                        <dt class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Ganancias Totales') }}</dt>
                        <button wire:click="toggleEarnings" class="text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300">
                            @if($showEarnings)
                                <flux:icon.eye class="size-4" />
                            @else
                                <flux:icon.eye-slash class="size-4" />
                            @endif
                        </button>
                    </div>
                    <dd class="mt-1.5 text-3xl font-semibold tracking-tight tabular-nums text-zinc-900 dark:text-zinc-50 {{ $showEarnings ? '' : 'blur-md select-none' }} transition-all duration-300">
                        Q{{ number_format($stats['total_earnings'], 2) }}
                    </dd>
                </div>
                <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="flex items-center justify-between">
                        <dt class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Pendiente de Cobro') }}</dt>
                        <button wire:click="toggleEarnings" class="text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300">
                            @if($showEarnings)
                                <flux:icon.eye class="size-4" />
                            @else
                                <flux:icon.eye-slash class="size-4" />
                            @endif
                        </button>
                    </div>
                    <dd class="mt-1.5 text-3xl font-semibold tracking-tight tabular-nums text-zinc-900 dark:text-zinc-50 {{ $showEarnings ? '' : 'blur-md select-none' }} transition-all duration-300">
                        Q{{ number_format($stats['pending_earnings'], 2) }}
                    </dd>
                </div>
                <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                    <dt class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Procedimientos') }}</dt>
                    <dd class="mt-1.5 text-3xl font-semibold tracking-tight tabular-nums text-zinc-900 dark:text-zinc-50">
                        {{ $stats['procedures_count'] }}</dd>
                </div>
            </div>
        @endif

        <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Accesos Directos') }}</h2>
        </div>

        @if($user && $user->hasRole('admin'))
            <div class="grid auto-rows-min gap-4 md:grid-cols-2 lg:grid-cols-3">
                <!-- Admin Shortcuts -->
                @if($hasQxlog)
                    <a href="{{ route('procedures.index') }}" wire:navigate
                        class="group relative flex flex-col justify-between overflow-hidden rounded-xl border border-zinc-200 p-6 hover:border-zinc-300 dark:border-zinc-700 dark:hover:border-zinc-600 bg-white dark:bg-zinc-900 transition-colors">
                        <div>
                            <div
                                class="mb-4 inline-flex items-center justify-center rounded-lg bg-mist p-3 text-accent dark:bg-accent/20 dark:text-accent">
                                <flux:icon.layout-grid class="size-6" />
                            </div>
                            <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Procedimientos') }}</h3>
                            <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                                {{ __('Ver todos los procedimientos registrados.') }}</p>
                        </div>
                    </a>

                    <a href="{{ route('payouts.index') }}" wire:navigate
                        class="group relative flex flex-col justify-between overflow-hidden rounded-xl border border-zinc-200 p-6 hover:border-zinc-300 dark:border-zinc-700 dark:hover:border-zinc-600 bg-white dark:bg-zinc-900 transition-colors">
                        <div>
                            <div
                                class="mb-4 inline-flex items-center justify-center rounded-lg bg-emulator-100 p-3 text-emulator-600 dark:bg-emulator-900/30 dark:text-emulator-400">
                                <flux:icon.banknotes class="size-6" />
                            </div>
                            <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Pagos') }}</h3>
                            <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                                {{ __('Gestionar y ver historial de pagos.') }}</p>
                        </div>
                    </a>

                    <a href="{{ route('pricing.settings') }}" wire:navigate
                        class="group relative flex flex-col justify-between overflow-hidden rounded-xl border border-zinc-200 p-6 hover:border-zinc-300 dark:border-zinc-700 dark:hover:border-zinc-600 bg-white dark:bg-zinc-900 transition-colors">
                        <div>
                            <div
                                class="mb-4 inline-flex items-center justify-center rounded-lg bg-zinc-100 p-3 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400">
                                <flux:icon.wrench class="size-6" />
                            </div>
                            <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Configuración') }}</h3>
                            <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                                {{ __('Ajustar precios y configuraciones.') }}</p>
                        </div>
                    </a>
                @else
                    <a href="{{ route('profile.edit') }}" wire:navigate
                        class="group relative flex flex-col justify-between overflow-hidden rounded-xl border border-zinc-200 p-6 hover:border-zinc-300 dark:border-zinc-700 dark:hover:border-zinc-600 bg-white dark:bg-zinc-900 transition-colors">
                        <div>
                            <div
                                class="mb-4 inline-flex items-center justify-center rounded-lg bg-zinc-100 p-3 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400">
                                <flux:icon.cog class="size-6" />
                            </div>
                            <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Mi Perfil') }}</h3>
                            <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                                {{ __('Actualizar mis datos y contraseña.') }}</p>
                        </div>
                    </a>
                @endif
            </div>
        @elseif($user && $user->hasRole('instrumentist'))
            <div class="grid auto-rows-min gap-4 md:grid-cols-2 lg:grid-cols-3">
                <!-- Instrumentist Shortcuts -->
                @if($hasQxlog)
                    <a href="{{ route('instrumentist.payouts') }}" wire:navigate
                        class="group relative flex flex-col justify-between overflow-hidden rounded-xl border border-zinc-200 p-6 hover:border-zinc-300 dark:border-zinc-700 dark:hover:border-zinc-600 bg-white dark:bg-zinc-900 transition-colors">
                        <div>
                            <div
                                class="mb-4 inline-flex items-center justify-center rounded-lg bg-emulator-100 p-3 text-emulator-600 dark:bg-emulator-900/30 dark:text-emulator-400">
                                <flux:icon.banknotes class="size-6" />
                            </div>
                            <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Mis Pagos') }}</h3>
                            <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                                {{ __('Ver mi historial de pagos recibidos.') }}</p>
                        </div>
                    </a>
                @endif

                <a href="{{ route('profile.edit') }}" wire:navigate
                    class="group relative flex flex-col justify-between overflow-hidden rounded-xl border border-zinc-200 p-6 hover:border-zinc-300 dark:border-zinc-700 dark:hover:border-zinc-600 bg-white dark:bg-zinc-900 transition-colors">
                    <div>
                        <div
                            class="mb-4 inline-flex items-center justify-center rounded-lg bg-zinc-100 p-3 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400">
                            <flux:icon.cog class="size-6" />
                        </div>
                        <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Mi Perfil') }}</h3>
                        <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                            {{ __('Actualizar mis datos y contraseña.') }}</p>
                    </div>
                </a>
            </div>
        @else
            <!-- Default / Other roles -->
            <div class="grid auto-rows-min gap-4 md:grid-cols-2 lg:grid-cols-3">
                <a href="{{ route('profile.edit') }}" wire:navigate
                    class="group relative flex flex-col justify-between overflow-hidden rounded-xl border border-zinc-200 p-6 hover:border-zinc-300 dark:border-zinc-700 dark:hover:border-zinc-600 bg-white dark:bg-zinc-900 transition-colors">
                    <div>
                        <div
                            class="mb-4 inline-flex items-center justify-center rounded-lg bg-zinc-100 p-3 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400">
                            <flux:icon.cog class="size-6" />
                        </div>
                        <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Mi Perfil') }}</h3>
                        <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                            {{ __('Actualizar mis datos y contraseña.') }}</p>
                    </div>
                </a>
            </div>
        @endif

    </div>