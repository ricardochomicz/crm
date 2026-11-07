<?php

namespace App\Actions\OrderStatus;

use App\Enums\TenantUserRole;
use App\Models\CommercialOrder;
use App\Models\OrderStatus;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManageOrderStatusesAction
{
    public function authorizeManager(Tenant $tenant, int $userId): void
    {
        $membership = $tenant->users()->where('users.id', $userId)->first();

        abort_unless(
            $membership && $membership->membership->role === TenantUserRole::Owner,
            403
        );
    }

    public function save(string $tenantId, string $name, ?string $id = null): OrderStatus
    {
        return DB::transaction(function () use ($tenantId, $name, $id): OrderStatus {
            $name = trim($name);
            $duplicate = OrderStatus::query()->where('tenant_id', $tenantId)
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                ->when($id, fn ($query) => $query->where('id', '!=', $id))
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages(['name' => 'Este status já está cadastrado.']);
            }

            $status = $id
                ? OrderStatus::query()->where('tenant_id', $tenantId)->findOrFail($id)
                : new OrderStatus(['tenant_id' => $tenantId]);

            $status->name = $name;
            if (! $id && ! OrderStatus::query()->where('tenant_id', $tenantId)->where('is_initial', true)->exists()) {
                $status->is_initial = true;
            }
            $status->save();

            return $status;
        });
    }

    public function setInitial(string $tenantId, string $id): OrderStatus
    {
        return DB::transaction(function () use ($tenantId, $id): OrderStatus {
            $status = OrderStatus::query()->where('tenant_id', $tenantId)->findOrFail($id);
            OrderStatus::query()->where('tenant_id', $tenantId)->update(['is_initial' => false]);
            $status->update(['is_initial' => true]);

            return $status;
        });
    }

    public function delete(string $tenantId, string $id): void
    {
        $status = OrderStatus::query()->where('tenant_id', $tenantId)->findOrFail($id);

        if (CommercialOrder::query()->where('tenant_id', $tenantId)->where('order_status_id', $id)->exists()) {
            throw ValidationException::withMessages(['status' => 'Este status está em uso e não pode ser excluído.']);
        }

        if ($status->is_initial) {
            throw ValidationException::withMessages(['status' => 'Defina outro status inicial antes de excluir este.']);
        }

        $status->delete();
    }
}
