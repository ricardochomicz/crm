<?php

namespace App\Http\Resources;

use App\Models\Opportunity;
use Illuminate\Http\Resources\Json\ResourceCollection;

class OpportunityTermCollection extends ResourceCollection
{
    private $opportunity;

    public function __construct($resource, Opportunity $opportunity)
    {
        $this->opportunity = $opportunity;
        parent::__construct($resource);
    }

    /**
     * Transform the resource collection into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'opportunity' => new OpportunityResource($this->opportunity),
            'terms' => $this->collection->map(function ($term) {
                return new OpportunityTermResource($term);
            })
        ];
    }
}
