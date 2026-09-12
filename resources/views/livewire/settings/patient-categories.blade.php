<?php
// Catalogo de categorias de paciente precargadas por edad, editables por hospital.

use App\Models\PatientCategory;
use App\Modules\QxLog\Support\Volt\CatalogManagerState;
use Illuminate\Support\Facades\Auth;

use function Livewire\Volt\{state, computed, mount, uses};

uses(CatalogManagerState::class);

state([
    'editingId' => null,
    'editingName' => '',
]);

mount(function () {
    abort_unless(Auth::check(), 401);
    abort_unless((bool) Auth::user()->can('settings.manage'), 403);
    abort_if((bool) Auth::user()?->is_platform_admin, 403, 'Administrador de plataforma es de solo lectura; usa una cuenta de hospital para operar.');
});

$items = computed(fn () => PatientCategory::query()
    ->where('hospital_id', Auth::user()->hospital_id)
    ->orderBy('sort_order')
    ->orderBy('name')
    ->get());

$startEdit = function (int $id, string $name) {
    $this->editingId = $id;
    $this->editingName = $name;
};

$saveName = function () {
    $this->validate(['editingName' => ['required', 'string', 'max:255']]);

    $item = PatientCategory::withoutGlobalScopes()->findOrFail($this->editingId);
    abort_if($item->hospital_id !== Auth::user()->hospital_id, 403);

    $item->update(['name' => trim($this->editingName)]);

    $this->editingId = null;
    $this->editingName = '';
};

$cancelEdit = function () {
    $this->editingId = null;
    $this->editingName = '';
};

$toggleActive = function (int $id) {
    $this->catalogToggleActive(PatientCategory::class, $id);
};

$moveUp = function (int $id) {
    $this->catalogMove(PatientCategory::class, $id, -1);
};

$moveDown = function (int $id) {
    $this->catalogMove(PatientCategory::class, $id, 1);
};

?>

<div class="max-w-3xl mx-auto p-4 space-y-6">
    <div class="mb-4 space-y-1">
        <flux:heading size="xl">{{ __('Categorías de paciente') }}</flux:heading>
        <flux:subheading>{{ __('Clasificación automática por edad. Puedes editar el nombre o desactivar las que no uses; el sistema sigue calculando la categoría por edad para mostrarla en el expediente.') }}</flux:subheading>
        <x-back-link :fallback="route('settings.wards')" />
    </div>

    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 divide-y divide-zinc-100 dark:divide-zinc-800">
        @foreach($this->items as $item)
            <div class="flex items-center justify-between gap-3 px-4 py-3 {{ ! $item->active ? 'opacity-50' : '' }}">
                <div class="flex items-center gap-3 min-w-0">
                    <span class="text-xs font-mono px-2 py-0.5 rounded bg-zinc-100 dark:bg-zinc-800 text-zinc-500 dark:text-zinc-400 uppercase">{{ $item->code }}</span>

                    @if($editingId === $item->id)
                        <div class="flex items-center gap-2 flex-1">
                            <flux:input wire:model="editingName" size="sm" class="w-48" />
                            <flux:button size="sm" variant="primary" wire:click="saveName">{{ __('Save') }}</flux:button>
                            <flux:button size="sm" variant="ghost" wire:click="cancelEdit">{{ __('Cancel') }}</flux:button>
                        </div>
                    @else
                        <span class="font-medium text-zinc-900 dark:text-zinc-100 truncate">{{ $item->name }}</span>
                    @endif
                </div>

                <div class="flex items-center gap-2">
                    @if($editingId !== $item->id)
                        <flux:button size="sm" variant="subtle" icon="pencil-square" wire:click="startEdit({{ $item->id }}, '{{ addslashes($item->name) }}')" />
                    @endif
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
