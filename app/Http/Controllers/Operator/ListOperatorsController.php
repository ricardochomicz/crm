<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\Operator;
use Illuminate\Http\JsonResponse;

class ListOperatorsController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json(['data' => Operator::query()->orderBy('name')->get(['id', 'name', 'slug', 'is_active'])]);
    }
}
