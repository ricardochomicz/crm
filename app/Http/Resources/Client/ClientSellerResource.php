<?php
namespace App\Http\Resources\Client;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
class ClientSellerResource extends JsonResource
{
 public function toArray(Request $request): array
 {
  return [
'id' => $this->id,
'tenant_id' => $this->tenant_id,
'client_id' => $this->client_id,
'tenant_user_id' => $this->tenant_user_id,
'assigned_by' => $this->assigned_by,
'started_at' => $this->started_at,
'ended_at' => $this->ended_at,
'reason' => $this->reason,
'created_at' => $this->created_at,
'updated_at' => $this->updated_at,
'membership' => $this->whenLoaded('membership')
  ];
 }
}
