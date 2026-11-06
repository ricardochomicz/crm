<?php
namespace App\Http\Resources\Client;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
class ClientOperatorHistoryResource extends JsonResource
{
 public function toArray(Request $request): array
 {
  return [
'id' => $this->id,
'tenant_id' => $this->tenant_id,
'client_id' => $this->client_id,
'operator_id' => $this->operator_id,
'classification_id' => $this->classification_id,
'changed_at' => $this->changed_at,
'changed_by' => $this->changed_by,
'created_at' => $this->created_at,
'updated_at' => $this->updated_at,
'operator' => $this->whenLoaded('operator'),
'classification' => $this->whenLoaded('classification')
  ];
 }
}
