<?php

use App\Enums\TenantUserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void {
        Schema::create('tenant_roles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('name', 100);
            $table->string('legacy_role', 20);
            $table->json('permissions');
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id', 'code']);
        });
        Schema::table('tenant_users', function (Blueprint $table): void {
            $table->foreignUuid('tenant_role_id')->nullable()->after('role')->constrained('tenant_roles')->nullOnDelete();
        });
        Schema::table('tenant_invitations', function (Blueprint $table): void {
            $table->foreignUuid('tenant_role_id')->nullable()->after('role')->constrained('tenant_roles')->nullOnDelete();
        });
        $defaults = [
            ['OWNER', 'Proprietário', 'OWNER'],
            ['MANAGER', 'Gerente', 'MANAGER'],
            ['SUPERVISOR', 'Supervisor de Equipe', 'CONSULTANT'],
            ['TELEMARKETING', 'Telemarketing', 'CONSULTANT'],
            ['BACKOFFICE', 'Backoffice', 'ASSISTANT'],
            ['FINANCE', 'Financeiro', 'ASSISTANT'],
            ['ADMINISTRATIVE', 'Administrativo', 'ASSISTANT'],
        ];
        foreach (DB::table('tenants')->select('id')->cursor() as $tenant) {
            foreach ($defaults as [$code, $name, $legacy]) {
                $permissions = $legacy === 'OWNER' ? \App\Enums\TenantPermission::keys() : (json_decode(DB::table('tenant_role_permissions')->where('tenant_id', $tenant->id)->where('role', $legacy)->value('permissions') ?? '[]', true) ?: []);
                $id = (string) Str::uuid();
                DB::table('tenant_roles')->insert(['id'=>$id,'tenant_id'=>$tenant->id,'code'=>$code,'name'=>$name,'legacy_role'=>$legacy,'permissions'=>json_encode($permissions, JSON_THROW_ON_ERROR),'is_system'=>true,'is_active'=>true,'created_at'=>now(),'updated_at'=>now()]);
                if (in_array($code, ['OWNER','MANAGER','TELEMARKETING','BACKOFFICE'], true)) {
                    DB::table('tenant_users')->where('tenant_id',$tenant->id)->where('role',$legacy)->whereNull('tenant_role_id')->update(['tenant_role_id'=>$id]);
                    DB::table('tenant_invitations')->where('tenant_id',$tenant->id)->where('role',$legacy)->whereNull('tenant_role_id')->update(['tenant_role_id'=>$id]);
                }
            }
        }
    }
    public function down(): void {
        Schema::table('tenant_invitations', fn(Blueprint $table)=>$table->dropConstrainedForeignId('tenant_role_id'));
        Schema::table('tenant_users', fn(Blueprint $table)=>$table->dropConstrainedForeignId('tenant_role_id'));
        Schema::dropIfExists('tenant_roles');
    }
};
