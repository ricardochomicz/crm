<?php
namespace App\Http\Resources\Tenant;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
class ActiveTenantResource extends JsonResource
{
    /** @param array<string, mixed> $access */
    public function __construct(Tenant $resource, private array $access)
    {
        parent::__construct($resource);
    }
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'name' => $this->name, 'type' => $this->type->value,
            'role' => $this->membership->role->value,
            'document' => $this->document,
            'trade_name' => $this->trade_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'timezone' => $this->timezone,
            'logo_url' => $this->logo ? \Illuminate\Support\Facades\Storage::disk('public')->url($this->logo) : null,
            'access' => $this->access,
        ];
    }
}
