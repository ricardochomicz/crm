<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TimelineResource extends JsonResource
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
            'opportunity' => $this->opportunity,
            'client' => $this->client,
            'comment' => $this->comment,
            'event' => $this->event,
            'created_at' => $this->created_at,
        ];
    }
}
