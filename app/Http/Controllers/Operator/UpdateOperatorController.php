<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operator\UpdateOperatorRequest;
use App\Models\Operator;
use App\Http\Resources\Operator\OperatorResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UpdateOperatorController extends Controller
{
    public function __invoke(UpdateOperatorRequest $request, Operator $operator): JsonResponse
    {
        $data = $request->validated();
        $operator->update($data);

        return response()->json(['data' => new OperatorResource($operator->fresh())]);
    }
}
