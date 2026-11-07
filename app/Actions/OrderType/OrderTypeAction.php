<?php

namespace App\Actions\OrderType;

use App\Models\OrderType;
use Illuminate\Support\Facades\DB;

class OrderTypeAction
{
    public function list(bool $activeOnly = false)
    {
        return OrderType::query()
            ->when($activeOnly, fn ($query) => $query->where('is_active', true))
            ->orderBy('position')->orderBy('name')->get();
    }

    public function create(array $data): OrderType
    {
        return DB::transaction(fn () => OrderType::create($data));
    }

    public function update(string $id, array $data): OrderType
    {
        return DB::transaction(function () use ($id, $data) {
            $type = OrderType::query()->findOrFail($id);
            $type->update($data);
            return $type->refresh();
        });
    }
}
