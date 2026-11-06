<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operator\ListOperatorsRequest;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use App\Actions\Operator\FilterOperatorAction;
use App\Http\Resources\Operator\OperatorResource;

class ListAvailableOperatorsController extends Controller
{
    public function __invoke(ListOperatorsRequest $request): AnonymousResourceCollection
    {
        return OperatorResource::collection((new FilterOperatorAction)->execute(array_merge($request->validated(), ['is_active' => true])));
    }
}
