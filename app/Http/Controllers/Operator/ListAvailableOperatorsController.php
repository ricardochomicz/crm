<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\Operator;
use Illuminate\Http\JsonResponse;

class ListAvailableOperatorsController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'data' => Operator::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'slug', 'is_active']),
        ]);
    }
}
