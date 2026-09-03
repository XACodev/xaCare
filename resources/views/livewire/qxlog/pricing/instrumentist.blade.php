<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;

use function Livewire\Volt\{state, computed, mount};

state([
    'searchInstrumentists' => '',
]);

mount(function () {
    abort_unless(Auth::check(), 401);
    abort_unless((bool) Auth::user()->can('pricing.manage'), 403);
    abort_if((bool) Auth::user()?->is_platform_admin, 403, 'Administrador de plataforma es de solo lectura; usa una cuenta de hospital para operar.');
});

// Generalized from User::role('instrumentist') to "any user who has ever participated in a
// case" so that hospital admins can set per-user RoleRate overrides for any payable role, not
// just instrumentists. Reuses pricing.settings (Step 1/2) as the override editor by linking to
// it with a ?user_id= query param instead of building a second tarifario UI here.
$instrumentists = computed(function () {
    return User::whereHas('assignments')
        ->when($this->searchInstrumentists, function ($q) {
            $term = trim($this->searchInstrumentists);
            $q->where(function ($s) use ($term) {
                $s->where('name', 'like', "%{$term}%")
                    ->orWhere('username', 'like', "%{$term}%");
            });
        })
        ->orderBy('name')
        ->get(['id', 'name', 'username', 'use_pay_scheme']);
});

$toggle = function (int $id) {
    abort_unless((bool) Auth::user()->can('pricing.manage'), 403);
    abort_if((bool) Auth::user()->is_platform_admin, 403, 'Administrador de plataforma es de solo lectura; usa una cuenta de hospital para operar.');

    $u = User::findOrFail($id);

    $u->use_pay_scheme = !(bool) $u->use_pay_scheme;
    $u->save();
};

?>

<div class="max-w-6xl mx-auto p-4 space-y-6">
    <div class="mb-4">
        <flux:heading size="xl">{{ __('Instrumentists') }}</flux:heading>
        <flux:subheading>{{ __('Mark who uses special payment scheme') }}</flux:subheading>
    </div>

    <div class="space-y-4">
        <flux:input icon="magnifying-glass" wire:model.live.debounce.300ms="searchInstrumentists"
            placeholder="{{ __('Search name or username...') }}" clearable />

        <!-- Mobile View (Cards) -->
        <div class="grid grid-cols-1 gap-4 sm:hidden">
            @forelse($this->instrumentists as $u)
                <div
                    class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 shadow-sm space-y-3">
                    <div class="flex justify-between items-start">
                        <div>
                            <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $u->name }}</div>
                            <div class="text-sm text-zinc-500">{{ $u->username }}</div>
                        </div>
                        <flux:badge size="sm" color="{{ $u->use_pay_scheme ? 'green' : 'zinc' }}">
                            {{ $u->use_pay_scheme ? __('Special') : __('Standard') }}
                        </flux:badge>
                    </div>
                    <div class="pt-2 border-t border-zinc-100 dark:border-zinc-800 flex flex-col gap-2">
                        <button
                            class="w-full h-8 text-sm rounded-lg cursor-pointer hover:bg-indigo-100 dark:hover:bg-indigo-800/30 transition-colors border-2 hover:border-indigo-200 dark:hover:border-indigo-700 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 border-indigo-100 dark:border-indigo-800"
                            wire:click="toggle({{ $u->id }})">
                            {{ $u->use_pay_scheme ? __('Deactivate scheme') : __('Activate scheme') }}
                        </button>
                        <a href="{{ route('pricing.settings', ['user_id' => $u->id]) }}"
                            class="w-full h-8 flex items-center justify-center text-sm rounded-lg cursor-pointer hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors border-2 border-zinc-100 dark:border-zinc-800">
                            {{ __('Configure rate') }}
                        </a>
                    </div>
                </div>
            @empty
                <div class="p-4 text-center text-zinc-500 dark:text-zinc-400 italic">
                    {{ __('No instrumentists.') }}
                </div>
            @endforelse
        </div>

        <!-- Desktop View (Table) -->
        <div class="hidden sm:block overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
            <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                <thead class="bg-indigo-100 dark:bg-indigo-500/25">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-medium tracking-wider">
                            <flux:label>{{ __('Name') }}</flux:label>
                        </th>
                        <th class="px-6 py-4 text-left text-xs font-medium tracking-wider">
                            <flux:label>{{ __('Username') }}</flux:label>
                        </th>
                        <th class="px-6 py-4 text-left text-xs font-medium tracking-wider">
                            <flux:label>{{ __('Scheme') }}</flux:label>
                        </th>
                        <th class="px-6 py-4 text-center text-xs font-medium tracking-wider">
                            <flux:label>{{ __('Actions') }}</flux:label>
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-zinc-900 divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse($this->instrumentists as $u)
                        <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                {{ $u->name }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-zinc-500 dark:text-zinc-400">
                                {{ $u->username }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <flux:badge size="sm" color="{{ $u->use_pay_scheme ? 'green' : 'zinc' }}">
                                    {{ $u->use_pay_scheme ? __('Yes') : __('No') }}
                                </flux:badge>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center text-sm space-x-2">
                                <button
                                    class="cursor-pointer transition-colors font-medium h-8 px-2 rounded-lg hover:bg-indigo-100 dark:hover:bg-indigo-900 border border-indigo-100 dark:border-indigo-800 bg-indigo-50 dark:bg-indigo-900/30 hover:border-indigo-200 dark:hover:border-indigo-700 hover:text-indigo-700 dark:hover:text-indigo-300 text-indigo-600 dark:text-indigo-400"
                                    wire:click="toggle({{ $u->id }})">
                                    {{ $u->use_pay_scheme ? __('Deactivate') : __('Activate') }}
                                </button>
                                <a href="{{ route('pricing.settings', ['user_id' => $u->id]) }}"
                                    class="inline-flex cursor-pointer transition-colors font-medium h-8 px-2 items-center rounded-lg hover:bg-zinc-100 dark:hover:bg-zinc-800 border border-zinc-100 dark:border-zinc-800">
                                    {{ __('Configure rate') }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-8 text-center text-sm text-zinc-500 dark:text-zinc-400 italic">
                                {{ __('No instrumentists.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>