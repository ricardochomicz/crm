<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
class TenantRoleResource extends JsonResource {
    public function toArray(Request $request):array {
        return ['id'=>$this->id,'code'=>$this->code,'name'=>$this->name,'legacy_role'=>$this->legacy_role,'permissions'=>$this->effectivePermissions(),'is_system'=>$this->is_system,'is_active'=>$this->is_active];
    }
}
