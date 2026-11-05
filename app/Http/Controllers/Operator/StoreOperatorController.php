<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\Operator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StoreOperatorController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'slug' => ['required', 'alpha_dash', 'max:160', Rule::unique('operators', 'slug')],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $operator = Operator::query()->create($data);

        return response()->json(['data' => $operator], 201);
    }
}
