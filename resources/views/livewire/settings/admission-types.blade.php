<?php

use App\Models\AdmissionType;
use App\Modules\QxLog\Support\Volt\CatalogManagerState;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

use function Livewire\Volt\{state, computed, mount, uses};

uses(CatalogManagerState::class);

state([
    'modelClass' => AdmissionType::class,
    'title' => __('Tipos de ingreso'),
    'description' => __('Administra los tipos de ingreso disponibles en el wizard de admisión.'),
    'form' => ['name' => ''],
    'extraFieldsSlot' => null,
]);

mount(function () {
    abort_unless(Auth::check(), 401);
    abort_unless((bool) Auth::user()->can('settings.manage'), 403);
    abort_if((bool) Auth::user()?->is_platform_admin, 403, 'Administrador de plataforma es de solo lectura; usa una cuenta de hospital para operar.');
});

$items = computed(fn () => $this->catalogItems($this->modelClass));

$create = function () {
    $this->validate(['form.name' => ['required', 'string', 'max:255']]);

    $name = trim($this->form['name']);

    $this->catalogCreate($this->modelClass, [
        'name' => $name,
        'slug' => Str::slug($name),
        'visible_sections' => [],
        'required_sections' => [],
    ]);

    $this->form['name'] = '';
};

$toggleActive = function (int $id) {
    $this->catalogToggleActive($this->modelClass, $id);
};

$moveUp = function (int $id) {
    $this->catalogMove($this->modelClass, $id, -1);
};

$moveDown = function (int $id) {
    $this->catalogMove($this->modelClass, $id, 1);
};

?>

<div class="max-w-3xl mx-auto p-4 space-y-6">
    <div class="mb-4 space-y-1">
        <flux:heading size="xl">{{ $title }}</flux:heading>
        @if(! empty($description))
            <flux:subheading>{{ $description }}</flux:subheading>
        @endif
        <x-back-link :fallback="route('settings.wards')" />
    </div>

    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-6 space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
            <flux:input label="{{ __('Name') }}" wire:model="form.name" class="md:col-span-2" />
            <flux:button wire:click="create" variant="primary">{{ __('Add') }}</flux:button>
        </div>
        @error('form.name') <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
    </div>

    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 divide-y divide-zinc-100 dark:divide-zinc-800">
        @foreach($this->items as $item)
            <div class="flex items-center justify-between gap-3 px-4 py-3 {{ ! $item->active ? 'opacity-50' : '' }}">
                <a href="{{ route('settings.admission-types.edit', $item) }}" class="font-medium text-zinc-900 dark:text-zinc-100 hover:underline" wire:navigate>
                    {{ $item->name }}
                </a>
                <div class="flex items-center gap-2">
                    <flux:button size="sm" variant="subtle" icon="pencil-square" :href="route('settings.admission-types.edit', $item)" wire:navigate />
                    <flux:button size="sm" variant="subtle" icon="chevron-up" wire:click="moveUp({{ $item->id }})" />
                    <flux:button size="sm" variant="subtle" icon="chevron-down" wire:click="moveDown({{ $item->id }})" />
                    <flux:button size="sm" :variant="$item->active ? 'danger' : 'primary'" wire:click="toggleActive({{ $item->id }})">
                        {{ $item->active ? __('Deactivate') : __('Activate') }}
                    </flux:button>
                </div>
            </div>
        @endforeach
    </div>
</div>
