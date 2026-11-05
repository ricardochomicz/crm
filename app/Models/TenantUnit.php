<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'email', 'phone', 'address', 'is_headquarters'])]
class TenantUnit extends Model
{
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return ['is_headquarters' => 'boolean'];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(TenantUser::class, 'tenant_unit_memberships', 'tenant_unit_id', 'membership_id')->withPivot('tenant_id');
    }

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }
}
