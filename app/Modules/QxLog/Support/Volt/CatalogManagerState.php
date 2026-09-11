<?php

namespace App\Modules\QxLog\Support\Volt;

use Illuminate\Support\Facades\Auth;

trait CatalogManagerState
{
    protected function catalogItems(string $modelClass)
    {
        return $modelClass::query()->orderBy('sort_order')->orderBy('name')->get();
    }

    protected function catalogCreate(string $modelClass, array $data): void
    {
        $hospitalId = Auth::user()->hospital_id;
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
