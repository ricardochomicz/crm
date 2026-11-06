<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operator\StoreOperatorRequest;
use App\Models\Operator;
use App\Http\Resources\Operator\OperatorResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StoreOperatorController extends Controller
{
    public function __invoke(StoreOperatorRequest $request): JsonResponse
    {
        $data = $request->validated();

        $operator = Operator::query()->create($data);

        return response()->json(['data' => new OperatorResource($operator)], 201);
    }
}
