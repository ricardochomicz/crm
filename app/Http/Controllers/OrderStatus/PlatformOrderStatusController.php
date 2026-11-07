<?php

namespace App\Http\Controllers\OrderStatus;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderStatus\OrderStatusResource;
use App\Models\CommercialOrder;
use App\Models\OrderStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PlatformOrderStatusController extends Controller
{
    public function index()
    {
        return OrderStatusResource::collection(OrderStatus::query()->orderBy('name')->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'min:2', 'max:100']]);
        $name = mb_strtoupper(trim($data['name']));
        if (OrderStatus::query()->where('name', $name)->exists()) {
            throw ValidationException::withMessages(['name' => 'Este status já existe.']);
        }
        return new OrderStatusResource(OrderStatus::create(['name' => $name, 'is_initial' => false]));
    }

    public function update(Request $request, string $statusId)
    {
        $data = $request->validate(['name' => ['required', 'string', 'min:2', 'max:100']]);
        $status = OrderStatus::findOrFail($statusId);
        if ($status->is_initial || in_array($status->name, ['ATIVO', 'CONCLUIDO', 'PARCIALMENTE ATIVADO'], true)) {
            throw ValidationException::withMessages(['name' => 'Este status é reservado ao fluxo do sistema.']);
        }
        $name = mb_strtoupper(trim($data['name']));
        if (OrderStatus::query()->where('name', $name)->where('id', '!=', $statusId)->exists()) {
            throw ValidationException::withMessages(['name' => 'Este status já existe.']);
        }
        $status->update(['name' => $name]);
        return new OrderStatusResource($status);
    }

    public function destroy(string $statusId)
    {
        $status = OrderStatus::findOrFail($statusId);
        if ($status->is_initial || in_array($status->name, ['ATIVO', 'CONCLUIDO', 'PARCIALMENTE ATIVADO'], true)
            || CommercialOrder::query()->where('order_status_id', $status->id)->exists()) {
            throw ValidationException::withMessages(['status' => 'Status obrigatório ou em uso; não pode ser excluído.']);
        }
        $status->delete();
        return response()->json(['message' => 'Status excluído.']);
    }
}
