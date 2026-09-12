<?php
// Componente Volt definitivo (Task 6) sobre el trait/vista compartidos (Task 5). Mantiene
// el mismo nombre de ruta `qxlog.settings.roles` que uso el test ancla de Task 5
// (CatalogManagerTest.php) para probar CatalogManagerState de punta a punta.

use App\Modules\QxLog\Models\SurgicalRole;
use App\Modules\QxLog\Support\Volt\CatalogManagerState;
use Illuminate\Support\Facades\Auth;

use function Livewire\Volt\{state, computed, mount, uses};

uses(CatalogManagerState::class);

state([
    'modelClass' => SurgicalRole::class,
    'title' => __('Surgical Roles'),
    'description' => __('Roles quirúrgicos que participan en una cirugía (ej. Cirujano, Instrumentista, Circulante). Marca "Pagable" para roles que reciben pago.'),
    'form' => ['name' => '', 'is_payable' => true],
    'extraFieldsSlot' => null,
]);

mount(function () {
    abort_unless(Auth::check(), 401);
    abort_unless((bool) Auth::user()->can('pricing.manage'), 403);
    abort_if((bool) Auth::user()?->is_platform_admin, 403, 'Administrador de plataforma es de solo lectura; usa una cuenta de hospital para operar.');
});

$items = computed(fn () => $this->catalogItems($this->modelClass));

$create = function () {
    $this->validate(['form.name' => ['required', 'string', 'max:255']]);

    $this->catalogCreate($this->modelClass, ['name' => $this->form['name'], 'is_payable' => (bool) $this->form['is_payable']]);

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
