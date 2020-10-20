<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ContactResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'contact' => $this->contact,
            'cellphone' => $this->cellphone,
            'office' => $this->office,
            'email' => $this->email,
            'active' => $this->active,
            'client' => $this->client,
            'adm' => $this->adm,
            'doc' => $this->doc,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];
    }
}
