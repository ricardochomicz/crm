<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\Operator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UpdateOperatorController extends Controller
{
    public function __invoke(Request $request, Operator $operator): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:160'],
            'slug' => ['sometimes', 'required', 'alpha_dash', 'max:160', Rule::unique('operators', 'slug')->ignore($operator->id)],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $operator->update($data);

        return response()->json(['data' => $operator->fresh()]);
    }
}
