<?php

namespace App\Modules\QxLog\Support\Volt;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

trait CatalogManagerState
{
    protected function catalogItems(string $modelClass)
    {
        return $modelClass::query()->orderBy('sort_order')->orderBy('name')->get();
    }

    protected function catalogCreate(string $modelClass, array $data): void
    {
        $hospitalId = Auth::user()->hospital_id;

        // SurgicalRole/SurgeryStatus derivan su slug del name y las 3 tablas tienen un
        // unique constraint por hospital (slug o name) — sin este chequeo, un nombre
        // duplicado (ej. "Cirujano", ya sembrado por seedDefaultsFor()) revienta con un
        // QueryException sin manejar en vez de mostrarse como un error de formulario normal.
        $duplicate = $modelClass::withoutGlobalScopes()
            ->where('hospital_id', $hospitalId)
            ->whereRaw('LOWER(name) = ?', [Str::lower(trim($data['name']))])
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages(['form.name' => __('This name already exists.')]);
        }

        $maxSort = (int) $modelClass::withoutGlobalScopes()->where('hospital_id', $hospitalId)->max('sort_order');

        $modelClass::create([...$data, 'hospital_id' => $hospitalId, 'sort_order' => $maxSort + 1, 'active' => true]);
    }

    protected function catalogFind(string $modelClass, int $id)
    {
        $item = $modelClass::withoutGlobalScopes()->findOrFail($id);
        abort_if($item->hospital_id !== Auth::user()->hospital_id, 403);

        return $item;
    }

    protected function catalogToggleActive(string $modelClass, int $id): void
    {
        $item = $this->catalogFind($modelClass, $id);
        $item->update(['active' => ! $item->active]);
    }

    protected function catalogMove(string $modelClass, int $id, int $direction): void
    {
        $item = $this->catalogFind($modelClass, $id);

        $neighbor = $modelClass::withoutGlobalScopes()
            ->where('hospital_id', $item->hospital_id)
            ->where('sort_order', $direction > 0 ? '>' : '<', $item->sort_order)
            ->orderBy('sort_order', $direction > 0 ? 'asc' : 'desc')
            ->first();

        if (! $neighbor) {
            return;
        }

        [$a, $b] = [$item->sort_order, $neighbor->sort_order];
        $item->update(['sort_order' => $b]);
        $neighbor->update(['sort_order' => $a]);
    }
}
