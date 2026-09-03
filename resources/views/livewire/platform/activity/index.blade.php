<?php

use App\Models\Activity;
use Illuminate\Support\Facades\Auth;
use Livewire\WithPagination;
use Livewire\Volt\Component;

new class extends Component {
    use WithPagination;

    public function mount(): void
    {
        abort_unless(Auth::check() && Auth::user()->is_platform_admin, 403);
    }

    public function with(): array
    {
        return [
            'activity' => Activity::with(['causer', 'subject'])->latest()->paginate(25),
        ];
    }

    public function layout(): mixed
    {
        return view('components.layouts.platform', ['title' => __('Actividad')]);
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6 rounded-xl">
    <flux:heading size="xl">{{ __('Actividad reciente') }}</flux:heading>

    <div class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900 space-y-4">
        @forelse($activity as $entry)
            <div class="text-sm text-zinc-700 dark:text-zinc-300 border-b border-zinc-100 dark:border-zinc-800 pb-3 last:border-0 last:pb-0">
                <span class="font-medium">{{ $entry->causer?->name ?? __('Sistema') }}</span>
                {{ $entry->description }}
                @if($entry->subject)
                    <span class="text-zinc-500">({{ class_basename($entry->subject_type) }} #{{ $entry->subject_id }})</span>
                @endif
                <span class="text-zinc-400">— {{ $entry->created_at->format('Y-m-d H:i') }}</span>
            </div>
        @empty
            <p class="text-sm text-zinc-500 italic">{{ __('Sin actividad todavía.') }}</p>
        @endforelse
    </div>

    {{ $activity->links() }}
</div>
