<?php

namespace App\Actions\Tenant;

use App\Enums\TenantPermission;
use App\Models\Tenant;
use App\Models\TenantRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
class SaveTenantRoleAction {
    public function execute(Tenant $tenant,array $data,?string $id=null):TenantRole {
        return DB::transaction(function()use($tenant,$data,$id) {
            $role=$id?TenantRole::where('tenant_id',$tenant->id)->lockForUpdate()->findOrFail($id):new TenantRole(['tenant_id'=>$tenant->id]);
            if($role->exists && $role->code==='OWNER')throw ValidationException::withMessages(['role'=>'As permissões do proprietário são protegidas.']);
            $code=$role->exists?$role->code:($data['code']??Str::upper(Str::slug($data['name'],'_')));
            if(!$role->exists && TenantRole::where('tenant_id',$tenant->id)->where('code',$code)->exists())throw ValidationException::withMessages(['code'=>'Este código já existe nesta empresa.']);
            $permissions=array_values(array_unique($data['permissions']));
            foreach(TenantPermission::catalog() as $item) {
                if(in_array($item['key'],$permissions,true) && array_diff($item['requires'],$permissions))throw ValidationException::withMessages(['permissions'=>'Inclua as permissões necessárias para '.$item['label'].'.']);
            }
            $role->fill(['name'=>$data['name'],'permissions'=>$permissions]);
            if(!$role->exists)$role->fill(['code'=>$code,'legacy_role'=>$data['legacy_role']??'ASSISTANT','is_system'=>false,'is_active'=>true]);
            if(array_key_exists('is_active',$data))$role->is_active=$data['is_active'];
            $role->save();
            return $role;
        });
    }
}
