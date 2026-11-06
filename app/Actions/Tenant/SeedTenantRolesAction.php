<?php

namespace App\Actions\Tenant;

use App\Enums\TenantPermission;
use App\Models\Tenant;
use App\Models\TenantRole;
use Illuminate\Support\Facades\DB;

class SeedTenantRolesAction {
    public const DEFAULTS = [
        ['OWNER','Proprietário','OWNER'],
        ['MANAGER','Gerente','MANAGER'],
        ['SUPERVISOR','Supervisor de Equipe','CONSULTANT'],
        ['TELEMARKETING','Telemarketing','CONSULTANT'],
        ['BACKOFFICE','Backoffice','ASSISTANT'],
        ['FINANCE','Financeiro','ASSISTANT'],
        ['ADMINISTRATIVE','Administrativo','ASSISTANT'],
    ];
    public function execute(Tenant $tenant):void {
        foreach(self::DEFAULTS as [$code,$name,$legacy]) {
            $permissions=$legacy==='OWNER'?TenantPermission::keys():(json_decode(DB::table('tenant_role_permissions')->where('tenant_id',$tenant->id)->where('role',$legacy)->value('permissions')??'[]',true)?:[]);
            TenantRole::firstOrCreate(['tenant_id'=>$tenant->id,'code'=>$code],['name'=>$name,'legacy_role'=>$legacy,'permissions'=>$permissions,'is_system'=>true,'is_active'=>true]);
        }
    }
}
