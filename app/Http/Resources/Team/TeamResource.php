<?php

namespace App\Http\Resources\Team;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeamResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
                $supervisor = $this->relationLoaded('supervisorMembership') ? $this->supervisorMembership : null;

                return [
                    'id' => $this->id,
                    'name' => $this->name,
                    'supervisor_membership_id' => $this->when(! $this->relationLoaded('supervisorMembership'), $this->supervisor_membership_id),
                    'members_count' => $this->whenHas('members_count'),
                    'supervisor' => $this->when($this->relationLoaded('supervisorMembership'), fn (): ?array => $supervisor === null ? null : [
                        'membership_id' => $supervisor->id,
                        'user_id' => $supervisor->user_id,
                        'name' => $supervisor->user?->name,
                        'status' => $supervisor->status->value,
                    ]),
                ];
    }
}
