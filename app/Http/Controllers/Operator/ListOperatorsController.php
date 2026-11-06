<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\Operator;
use App\Http\Resources\Operator\OperatorResource;

class ListOperatorsController extends Controller
{
    public function __invoke()
    {
        return OperatorResource::collection(Operator::query()->orderBy('name')->get(['id', 'name', 'slug', 'is_active']));
    }
}
