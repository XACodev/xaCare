<?php
// @include'd desde cada componente concreto (Task 6), que define $modelClass, $title, $form
// (con al menos $form->name) y las acciones create/toggleActive/moveUp/moveDown delegando
// al trait CatalogManagerState. $items debe ser un computed property del componente.
// $extraFieldsSlot es opcional: HTML crudo (ya escapado por el consumidor) para campos extra.
?>
<div class="max-w-3xl mx-auto p-4 space-y-6">
    <div class="mb-4 space-y-1">
        <flux:heading size="xl">{{ $title }}</flux:heading>
        @if(! empty($description))
            <flux:subheading>{{ $description }}</flux:subheading>
        @endif
        <x-back-link :fallback="route('pricing.instrumentists')" />
    </div>

    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-6 space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
            <flux:input label="{{ __('Name') }}" wire:model="form.name" class="md:col-span-2" />
            {{ $extraFieldsSlot ?? '' }}
            <flux:button wire:click="create" variant="primary">{{ __('Add') }}</flux:button>
        </div>
        @error('form.name') <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
    </div>

    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 divide-y divide-zinc-100 dark:divide-zinc-800">
        @foreach($this->items as $item)
            <div class="flex items-center justify-between gap-3 px-4 py-3 {{ ! $item->active ? 'opacity-50' : '' }}">
                <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $item->name }}</span>
                <div class="flex items-center gap-2">
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
