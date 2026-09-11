<?php
// Componente Volt minimo temporal sobre el trait/vista compartidos (Task 5), para probar
// CatalogManagerState de punta a punta. El Task 6 lo reemplaza por la version definitiva
// (con validacion de permisos, campos extra, etc.) manteniendo este mismo nombre de ruta
// `qxlog.settings.roles`.

use App\Modules\QxLog\Models\SurgicalRole;
use App\Modules\QxLog\Support\Volt\CatalogManagerState;
use Illuminate\Support\Facades\Auth;

use function Livewire\Volt\{state, computed, mount, uses};

uses(CatalogManagerState::class);

state([
    'modelClass' => SurgicalRole::class,
    'title' => __('Surgical Roles'),
    'form' => ['name' => ''],
    'extraFieldsSlot' => null,
]);

mount(function () {
    abort_unless(Auth::check(), 401);
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
