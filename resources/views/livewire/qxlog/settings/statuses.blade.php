<?php
// Fork del componente compartido (catalog-manager.blade.php) para aplicar el patron de
// edicion en linea del mockup 1n (panel debajo de la tabla), igual que rooms.blade.php.
// SurgeryStatus si tiene columna "color" (hex editable), asi que el panel de edicion
// trae Nombre + Color + Estado.

use App\Modules\QxLog\Models\SurgeryStatus;
use App\Modules\QxLog\Support\Volt\CatalogManagerState;
use Illuminate\Support\Facades\Auth;

use function Livewire\Volt\{state, computed, mount, uses};

uses(CatalogManagerState::class);

state([
    'modelClass' => SurgeryStatus::class,
    'title' => __('Surgery Statuses'),
    'description' => __('Estados por los que puede pasar una cirugía (ej. Programada, Confirmada, Cancelada). Define el flujo de trabajo del quirófano.'),
    'form' => ['name' => '', 'color' => '#6366f1'],
    'editingId' => null,
    'editForm' => ['name' => '', 'color' => '#6366f1', 'active' => true],
]);

mount(function () {
    abort_unless(Auth::check(), 401);
    abort_unless((bool) Auth::user()->can('pricing.manage'), 403);
    abort_if((bool) Auth::user()?->is_platform_admin, 403, 'Administrador de plataforma es de solo lectura; usa una cuenta de hospital para operar.');
});

$items = computed(fn () => $this->catalogItems($this->modelClass));

$create = function () {
    $this->validate(['form.name' => ['required', 'string', 'max:255']]);

    $this->catalogCreate($this->modelClass, ['name' => $this->form['name'], 'color' => $this->form['color']]);

    $this->form['name'] = '';
};

$edit = function (int $id) {
    $item = $this->catalogFind($this->modelClass, $id);

    $this->editingId = $id;
    $this->editForm = ['name' => $item->name, 'color' => $item->color ?: '#8A959C', 'active' => $item->active];
    $this->resetErrorBag();
};

$cancelEdit = function () {
    $this->editingId = null;
};

$save = function () {
    $this->validate([
        'editForm.name' => ['required', 'string', 'max:255'],
        'editForm.color' => ['required', 'string', 'max:20'],
        'editForm.active' => ['required', 'boolean'],
    ]);

    $this->catalogUpdate($this->modelClass, $this->editingId, $this->editForm, 'editForm.name');

    $this->editingId = null;
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
        <x-back-link :fallback="route('pricing.instrumentists')" />
    </div>

    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-6 space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
            <flux:input label="{{ __('Name') }}" wire:model="form.name" class="md:col-span-2" />
            <flux:input type="color" label="{{ __('Color') }}" wire:model="form.color" />
            <flux:button wire:click="create" variant="primary">{{ __('Add') }}</flux:button>
        </div>
        @error('form.name') <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
    </div>

    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 overflow-hidden">
        <div class="qx-table-head grid grid-cols-[1.4fr_1fr_auto] gap-3 px-4 py-2">
            <span>{{ __('Name') }}</span>
            <span>{{ __('Status') }}</span>
            <span class="w-28"></span>
        </div>
        @foreach($this->items as $item)
            <div class="qx-table-row grid grid-cols-[1.4fr_1fr_auto] gap-3 items-center px-4 {{ ! $item->active ? 'opacity-50' : '' }}">
                <span class="font-medium text-zinc-900 dark:text-zinc-100 flex items-center gap-2">
                    <span class="inline-block size-3 rounded-full" style="background-color: {{ $item->color ?: '#8A959C' }}"></span>
                    {{ $item->name }}
                </span>
                <flux:badge :variant="$item->active ? 'success' : 'warning'">
                    {{ $item->active ? __('Active') : __('Inactive') }}
                </flux:badge>
                <div class="w-28 flex items-center justify-end gap-2">
                    <flux:button size="sm" variant="subtle" icon="chevron-up" wire:click="moveUp({{ $item->id }})" />
                    <flux:button size="sm" variant="subtle" icon="chevron-down" wire:click="moveDown({{ $item->id }})" />
                    <flux:button size="sm" variant="subtle" icon="pencil" wire:click="edit({{ $item->id }})" />
                </div>
            </div>
        @endforeach
    </div>

    @if($editingId !== null)
        <div class="rounded-xl border-2 border-accent bg-white dark:bg-zinc-900 p-6 space-y-4">
            <div class="flex items-center justify-between">
                <flux:heading size="lg">{{ __('Edit') }} · {{ $editForm['name'] }}</flux:heading>
                <flux:subheading>{{ __('Inline editing, without leaving the list') }}</flux:subheading>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <flux:input label="{{ __('Name') }}" wire:model="editForm.name" />
                <flux:input type="color" label="{{ __('Color') }}" wire:model="editForm.color" />
                <flux:select label="{{ __('Status') }}" wire:model="editForm.active">
                    <flux:select.option value="1">{{ __('Active') }}</flux:select.option>
                    <flux:select.option value="0">{{ __('Inactive') }}</flux:select.option>
                </flux:select>
            </div>
            @error('editForm.name') <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
            @error('editForm.color') <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror

            <div class="flex justify-end gap-3">
                <flux:button variant="ghost" wire:click="cancelEdit">{{ __('Cancel') }}</flux:button>
                <flux:button variant="primary" wire:click="save">{{ __('Save Changes') }}</flux:button>
            </div>
        </div>
    @endif
</div>
