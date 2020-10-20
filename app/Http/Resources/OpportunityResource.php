<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class OpportunityResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'client' => $this->client,
            'funnel' => $this->funnel,
            'status' => $this->status,
            'active' => $this->active,
            'lines' => $this->lines,
            'recipe' => $this->recipe,
            'forecast' => $this->forecast,
            'archive' => $this->archive,
            'activate' => $this->activate,
            'renew' => $this->renew,
            'type' => $this->type,
            'activity_date' => $this->activity_date,
            'activity_status' => $this->activity_status,
            'order_status' => $this->order_status,
            'offer' => $this->offer,
            'renew_date' => $this->renew_date,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];
    }
}
