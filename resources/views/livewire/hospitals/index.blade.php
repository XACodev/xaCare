<?php

use App\Models\Hospital;
use Illuminate\Support\Facades\Auth;

use function Livewire\Volt\{state, mount, computed};

state(['q' => '']);

mount(function () {
    abort_unless(Auth::check() && Auth::user()->is_super_admin, 403);
});

$hospitals = computed(function () {
    return Hospital::query()
        ->when($this->q, fn ($query) => $query->where('name', 'like', "%{$this->q}%"))
        ->orderBy('name')
        ->withCount('users')
        ->get();
});

$toggleActive = function (int $id) {
    abort_unless((bool) Auth::user()->is_super_admin, 403);

    $hospital = Hospital::findOrFail($id);
    $hospital->update(['is_active' => ! $hospital->is_active]);
};

?>

<div class="max-w-5xl mx-auto p-4 space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Hospitals') }}</flux:heading>
            <flux:subheading>{{ __('Only Super Admin') }}</flux:subheading>
        </div>

        <flux:button href="{{ route('hospitals.create') }}" icon="plus" class="w-full sm:w-auto" variant="primary">
            {{ __('New Hospital') }}
        </flux:button>
    </div>

    <flux:input icon="magnifying-glass" wire:model.live="q" placeholder="{{ __('Search by name...') }}" />

    <div class="hidden sm:block overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
        <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
            <thead class="bg-zinc-50 dark:bg-zinc-800/50">
                <tr>
                    <th class="px-4 py-4 text-left text-xs font-semibold text-zinc-500 tracking-wider">{{ __('Name') }}</th>
                    <th class="px-4 py-4 text-left text-xs font-semibold text-zinc-500 tracking-wider">{{ __('Slug') }}</th>
                    <th class="px-4 py-4 text-left text-xs font-semibold text-zinc-500 tracking-wider">{{ __('Plan') }}</th>
                    <th class="px-4 py-4 text-center text-xs font-semibold text-zinc-500 tracking-wider">{{ __('Users') }}</th>
                    <th class="px-4 py-4 text-center text-xs font-semibold text-zinc-500 tracking-wider">{{ __('Status') }}</th>
                    <th class="px-4 py-4 text-center text-xs font-semibold text-zinc-500 tracking-wider">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-zinc-900 divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse($this->hospitals as $hospital)
                    <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors">
                        <td class="px-4 py-3 font-medium text-zinc-900 dark:text-zinc-100">{{ $hospital->name }}</td>
                        <td class="px-4 py-3 text-sm text-zinc-500 font-mono">{{ $hospital->slug }}</td>
                        <td class="px-4 py-3 text-sm text-zinc-500 capitalize">{{ $hospital->plan }}</td>
                        <td class="px-4 py-3 text-center text-sm text-zinc-500">{{ $hospital->users_count }}</td>
                        <td class="px-4 py-3 text-center">
                            <flux:badge size="sm" color="{{ $hospital->is_active ? 'green' : 'red' }}">
                                {{ $hospital->is_active ? __('Active') : __('Inactive') }}
                            </flux:badge>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <div class="flex items-center justify-center gap-2">
                                <flux:button href="{{ route('hospitals.edit', $hospital->id) }}" size="sm" icon="pencil" />
                                <flux:button size="sm" icon="power" variant="ghost"
                                    wire:click="toggleActive({{ $hospital->id }})"
                                    wire:confirm="{{ __('Toggle this hospital active status?') }}" />
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-sm text-zinc-500">{{ __('No hospitals.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
