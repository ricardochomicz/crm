<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Comment extends Model {
    use HasUuids, SoftDeletes;
    protected $fillable = ['tenant_id', 'tenant_user_id', 'commentable_type', 'commentable_id', 'content', 'edited_at'];
    protected function casts(): array { return ['edited_at' => 'datetime']; }
    public function author() { return $this->belongsTo(TenantUser::class, 'tenant_user_id')->with('user'); }
    public function attachments() { return $this->hasMany(Attachment::class); }
}
