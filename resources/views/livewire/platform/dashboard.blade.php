<?php

use App\Enums\SubscriptionStatus;
use App\Models\Activity;
use App\Models\Hospital;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Component;

new class extends Component {
    public array $hospitalStats = [];
    public $trialsEndingSoon;
    public int $totalPlatformUsers = 0;
    public $recentActivity;

    public function mount(): void
    {
        $this->hospitalStats = [
            'total' => Hospital::count(),
            'active' => Hospital::where('subscription_status', SubscriptionStatus::Active)->count(),
            'trialing' => Hospital::where('subscription_status', SubscriptionStatus::Trialing)->count(),
            'past_due_or_canceled' => Hospital::whereIn('subscription_status', [
                SubscriptionStatus::PastDue,
                SubscriptionStatus::Canceled,
            ])->count(),
            'by_plan' => Hospital::select('plan', DB::raw('count(*) as total'))
                ->groupBy('plan')
                ->pluck('total', 'plan'),
        ];

        $this->trialsEndingSoon = Hospital::where('subscription_status', SubscriptionStatus::Trialing)
            ->where('trial_ends_at', '<=', now()->addDays(7))
            ->orderBy('trial_ends_at')
            ->get();

        $this->totalPlatformUsers = User::where('is_platform_admin', false)->count();

        $this->recentActivity = Activity::with(['causer', 'subject'])->latest()->limit(10)->get();
    }

    public function layout(): mixed
    {
        return view('components.layouts.platform', ['title' => __('Dashboard')]);
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6 rounded-xl">
    <flux:heading size="xl">{{ __('Dashboard') }}</flux:heading>

    <div class="grid gap-4 md:grid-cols-4">
        <div class="relative overflow-hidden rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
            <dt class="truncate text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Hospitales totales') }}</dt>
            <dd class="mt-2 text-3xl font-semibold text-zinc-900 dark:text-zinc-100">{{ $hospitalStats['total'] }}</dd>
        </div>
        <div class="relative overflow-hidden rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
            <dt class="truncate text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Activos') }}</dt>
            <dd class="mt-2 text-3xl font-semibold text-emerald-600 dark:text-emerald-400">{{ $hospitalStats['active'] }}</dd>
        </div>
        <div class="relative overflow-hidden rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
            <dt class="truncate text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('En trial') }}</dt>
            <dd class="mt-2 text-3xl font-semibold text-amber-600 dark:text-amber-400">{{ $hospitalStats['trialing'] }}</dd>
        </div>
        <div class="relative overflow-hidden rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
            <dt class="truncate text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Vencidos/cancelados') }}</dt>
            <dd class="mt-2 text-3xl font-semibold text-red-600 dark:text-red-400">{{ $hospitalStats['past_due_or_canceled'] }}</dd>
        </div>
    </div>

    <div class="grid gap-6 md:grid-cols-2">
        <div class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:heading size="lg" class="mb-4">{{ __('Por plan') }}</flux:heading>
            <div class="space-y-2">
                @forelse($hospitalStats['by_plan'] as $plan => $count)
                    <div class="flex items-center justify-between text-sm">
                        <span class="capitalize text-zinc-700 dark:text-zinc-300">{{ $plan }}</span>
                        <span class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $count }}</span>
                    </div>
                @empty
                    <p class="text-sm text-zinc-500 italic">{{ __('No hay hospitales todavía.') }}</p>
                @endforelse
            </div>
            <flux:separator class="my-4" />
            <div class="flex items-center justify-between text-sm">
                <span class="text-zinc-700 dark:text-zinc-300">{{ __('Total de usuarios en la plataforma') }}</span>
                <span class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $totalPlatformUsers }}</span>
            </div>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:heading size="lg" class="mb-4">{{ __('Trials por expirar (7 días)') }}</flux:heading>
            <div class="space-y-2">
                @forelse($trialsEndingSoon as $hospital)
                    <a href="{{ route('platform.hospitals.edit', $hospital->id) }}" wire:navigate
                        class="flex items-center justify-between text-sm hover:underline">
                        <span class="text-zinc-700 dark:text-zinc-300">{{ $hospital->name }}</span>
                        <span class="text-zinc-500">{{ $hospital->trial_ends_at->format('Y-m-d') }}</span>
                    </a>
                @empty
                    <p class="text-sm text-zinc-500 italic">{{ __('Ningún trial vence pronto.') }}</p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex items-center justify-between mb-4">
            <flux:heading size="lg">{{ __('Actividad reciente') }}</flux:heading>
            <flux:link :href="route('platform.activity.index')" wire:navigate class="text-sm">{{ __('Ver todo') }}</flux:link>
        </div>
        <div class="space-y-3">
            @forelse($recentActivity as $entry)
                <div class="text-sm text-zinc-700 dark:text-zinc-300">
                    <span class="font-medium">{{ $entry->causer?->name ?? __('Sistema') }}</span>
                    {{ $entry->description }}
                    @if($entry->subject)
                        <span class="text-zinc-500">({{ class_basename($entry->subject_type) }} #{{ $entry->subject_id }})</span>
                    @endif
                    <span class="text-zinc-400">— {{ $entry->created_at->diffForHumans() }}</span>
                </div>
            @empty
                <p class="text-sm text-zinc-500 italic">{{ __('Sin actividad todavía.') }}</p>
            @endforelse
        </div>
    </div>
</div>
