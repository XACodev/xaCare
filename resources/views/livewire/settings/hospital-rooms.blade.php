<?php
// Catalogo de habitaciones de hospitalizacion. Mismo patron que qxlog/settings/rooms.blade.php.

use App\Models\HospitalRoom;
use App\Modules\QxLog\Support\Volt\CatalogManagerState;
use Illuminate\Support\Facades\Auth;

use function Livewire\Volt\{state, computed, mount, uses};

uses(CatalogManagerState::class);

state([
    'modelClass' => HospitalRoom::class,
    'title' => __('Habitaciones'),
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

    $this->catalogCreate($this->modelClass, ['name' => $this->form['name']]);

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

@include('livewire.qxlog.settings.catalog-manager')
