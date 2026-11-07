<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Attachment extends Model {
    use HasUuids;
    protected $fillable = ['tenant_id', 'tenant_user_id', 'comment_id', 'original_name', 'storage_path', 'mime_type', 'size_bytes'];
}
