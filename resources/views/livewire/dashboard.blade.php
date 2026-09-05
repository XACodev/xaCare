<?php

use Livewire\Volt\Component;
use App\Modules\QxLog\Models\SurgicalCase;
use App\Modules\QxLog\Models\PayoutBatch;
use Illuminate\Support\Facades\Auth;

new class extends Component {
    public bool $showEarnings = false;

    public function toggleEarnings(): void
    {
        $this->showEarnings = !$this->showEarnings;
    }

    public function mount(): void
    {
        if (Auth::user()?->is_platform_admin) {
            $this->redirect(route('platform.dashboard'), navigate: true);
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
            ];
        }

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
        ];
    }

    public function layout(): mixed
    {
        return view('components.layouts.app', ['title' => __('Dashboard')]);
    }
}; ?>

    <div class="flex h-full w-full flex-1 flex-col gap-6 rounded-xl">

        <!-- Stats Section -->
        @if($user && $user->hasRole('admin') && $hasQxlog)
            <div class="grid gap-4 md:grid-cols-3">
                <div
                    class="relative overflow-hidden rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
                    <dt class="truncate text-sm font-medium text-zinc-500 dark:text-zinc-400">Total Procedimientos</dt>
                    <dd class="mt-2 text-3xl font-semibold text-zinc-900 dark:text-zinc-100">
                        {{ $stats['total_procedures'] }}</dd>
                    <div
                        class="absolute right-4 top-6 p-2 bg-indigo-50 dark:bg-indigo-900/20 rounded-lg text-indigo-600 dark:text-indigo-400">
                        <flux:icon.layout-grid class="size-5" />
                    </div>
                </div>
                <div
                    class="relative overflow-hidden rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
                    <dt class="truncate text-sm font-medium text-zinc-500 dark:text-zinc-400">Pendientes de Pago</dt>
                    <dd class="mt-2 text-3xl font-semibold text-zinc-900 dark:text-zinc-100">
                        {{ $stats['pending_procedures'] }}</dd>
                    <div
                        class="absolute right-4 top-6 p-2 bg-amber-50 dark:bg-amber-900/20 rounded-lg text-amber-600 dark:text-amber-400">
                        <flux:icon.clock class="size-5" />
                    </div>
                </div>
                <div
                    class="relative overflow-hidden rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
                    <dt class="truncate text-sm font-medium text-zinc-500 dark:text-zinc-400">Total Pagado</dt>
                    <dd class="mt-2 text-3xl font-semibold text-zinc-900 dark:text-zinc-100">
                        Q{{ number_format($stats['total_paid'], 2) }}</dd>
                    <div
                        class="absolute right-4 top-6 p-2 bg-emerald-50 dark:bg-emerald-900/20 rounded-lg text-emerald-600 dark:text-emerald-400">
                        <flux:icon.banknotes class="size-5" />
                    </div>
                </div>
            </div>
        @elseif($user && $user->hasRole('instrumentist') && $hasQxlog)
            <div class="grid gap-4 md:grid-cols-3">
                 <div class="relative overflow-hidden rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="flex items-center justify-between">
                        <dt class="truncate text-sm font-medium text-zinc-500 dark:text-zinc-400">Ganancias Totales</dt>
                        <button wire:click="toggleEarnings" class="text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300">
                            @if($showEarnings)
                                <flux:icon.eye class="size-4" />
                            @else
                                <flux:icon.eye-slash class="size-4" />
                            @endif
                        </button>
                    </div>
                    <dd class="mt-2 text-3xl font-semibold text-zinc-900 dark:text-zinc-100 {{ $showEarnings ? '' : 'blur-md select-none' }} transition-all duration-300">
                        Q{{ number_format($stats['total_earnings'], 2) }}
                    </dd>
                     <div class="absolute right-4 top-6 p-2 bg-emerald-50 dark:bg-emerald-900/20 rounded-lg text-emerald-600 dark:text-emerald-400">
                        <flux:icon.banknotes class="size-5" />
                    </div>
                </div>
                <div class="relative overflow-hidden rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
                     <div class="flex items-center justify-between">
                        <dt class="truncate text-sm font-medium text-zinc-500 dark:text-zinc-400">Pendiente de Cobro</dt>
                        <button wire:click="toggleEarnings" class="text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300">
                            @if($showEarnings)
                                <flux:icon.eye class="size-4" />
                            @else
                                <flux:icon.eye-slash class="size-4" />
                            @endif
                        </button>
                    </div>
                    <dd class="mt-2 text-3xl font-semibold text-zinc-900 dark:text-zinc-100 {{ $showEarnings ? '' : 'blur-md select-none' }} transition-all duration-300">
                        Q{{ number_format($stats['pending_earnings'], 2) }}
                    </dd>
                     <div class="absolute right-4 top-6 p-2 bg-amber-50 dark:bg-emerald-900/20 rounded-lg text-amber-600 dark:text-amber-400">
                        <flux:icon.clock class="size-5" />
                    </div>
                </div>
                <div
                    class="relative overflow-hidden rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
                    <dt class="truncate text-sm font-medium text-zinc-500 dark:text-zinc-400">Procedimientos</dt>
                    <dd class="mt-2 text-3xl font-semibold text-zinc-900 dark:text-zinc-100">
                        {{ $stats['procedures_count'] }}</dd>
                    <div
                        class="absolute right-4 top-6 p-2 bg-indigo-50 dark:bg-indigo-900/20 rounded-lg text-indigo-600 dark:text-indigo-400">
                        <flux:icon.layout-grid class="size-5" />
                    </div>
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
                                class="mb-4 inline-flex items-center justify-center rounded-lg bg-indigo-100 p-3 text-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-400">
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