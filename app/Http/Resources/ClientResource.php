<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ClientResource extends JsonResource
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
            'name' => $this->name,
            'document' => $this->document,
            'phone' => $this->phone,
            'address' => $this->address,
            'number' => $this->number,
            'village' => $this->village,
            'zipcode' => $this->zipcode,
            'city' => $this->city,
            'state' => $this->state,
            'operator' => $this->operator,
            'cnae' => $this->cnae,
            'textcnae' => $this->textcnae,
            'active' => $this->active,
            'user' => $this->user,
            'classification' => $this->classification,
            'number_client' => $this->number_client,
            'password_client' => $this->password_client,
            'contact_name' => $this->contact_name,
            'contact_cellphone' => $this->contact_cellphone,
            'contact_email' => $this->contact_email,
            'contact_adm' => $this->contact_adm,
            'contact_doc' => $this->contact_doc,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];
    }
}
