<?php

namespace App\Http\Controllers\OrderType;

use App\Actions\OrderType\OrderTypeAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\OrderType\SaveOrderTypeRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderTypeController extends Controller
{
    public function available(OrderTypeAction $action): JsonResponse
    {
        return response()->json(['data' => $action->list(true)]);
    }

    public function index(OrderTypeAction $action): JsonResponse
    {
        return response()->json(['data' => $action->list()]);
    }

    public function store(SaveOrderTypeRequest $request, OrderTypeAction $action): JsonResponse
    {
        return response()->json(['data' => $action->create($request->validated())], 201);
    }

    public function update(SaveOrderTypeRequest $request, string $orderTypeId, OrderTypeAction $action): JsonResponse
    {
        return response()->json(['data' => $action->update($orderTypeId, $request->validated())]);
    }
}
