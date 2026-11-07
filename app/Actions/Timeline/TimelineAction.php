<?php
namespace App\Actions\Timeline;

use App\Actions\Tenant\ResolveTenantPermissionsAction;
use App\Enums\TenantPermission;
use App\Enums\TenantUserRole;
use App\Models\Attachment;
use App\Models\Comment;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class TimelineAction {
    public function __construct(private ResolveTenantPermissionsAction $permissions) {}

    public function authorize(Tenant $tenant, User $user, string $type, string $id, bool $write = false): void {
        $models = ['opportunity' => \App\Models\Opportunity::class, 'client' => \App\Models\Client::class, 'commercial_order' => \App\Models\CommercialOrder::class];
        abort_unless(isset($models[$type]), 404);
        $permission = match ($type) {
            'client' => $write ? TenantPermission::UpdateClients : TenantPermission::ViewClients,
            default => $write ? TenantPermission::UpdateOpportunities : TenantPermission::ViewOpportunities,
        };
        abort_unless($this->permissions->allows($user, $tenant, $permission), 403);
        $member = $this->permissions->membership($user, $tenant);
        abort_unless($member, 403);
        $subject = $models[$type]::query()->where('tenant_id', $tenant->id)->findOrFail($id);
        if ($type === 'opportunity' && !in_array($member->role, [TenantUserRole::Owner, TenantUserRole::Manager], true)) {
            abort_unless($subject->tenant_user_id === $member->id, 403);
        }
    }

    public function comment(Tenant $tenant, User $user, string $id, bool $write = false): Comment {
        $comment = Comment::where('tenant_id', $tenant->id)->findOrFail($id);
        $this->authorize($tenant, $user, $comment->commentable_type, $comment->commentable_id, $write);
        return $comment;
    }

    public function list(Tenant $tenant, User $user, string $type, string $id): array {
        $this->authorize($tenant, $user, $type, $id);
        return Comment::where('tenant_id', $tenant->id)->where('commentable_type', $type)
            ->where('commentable_id', $id)->with(['author', 'attachments'])->latest()->paginate(20)->toArray();
    }

    public function create(Tenant $tenant, User $user, string $type, string $id, string $content): Comment {
        $this->authorize($tenant, $user, $type, $id, true);
        return Comment::create([
            'tenant_id' => $tenant->id, 'tenant_user_id' => $this->permissions->membership($user, $tenant)->id,
            'commentable_type' => $type, 'commentable_id' => $id, 'content' => $content,
        ])->load(['author', 'attachments']);
    }

    private function own(Tenant $tenant, User $user, Comment $comment): void {
        abort_unless($comment->tenant_user_id === $this->permissions->membership($user, $tenant)?->id, 403);
    }

    public function update(Tenant $tenant, User $user, string $id, string $content): Comment {
        $comment = $this->comment($tenant, $user, $id, true);
        $this->own($tenant, $user, $comment);
        $comment->update(['content' => $content, 'edited_at' => now()]);
        return $comment->load(['author', 'attachments']);
    }

    public function delete(Tenant $tenant, User $user, string $id): void {
        $comment = $this->comment($tenant, $user, $id, true);
        $this->own($tenant, $user, $comment);
        $comment->delete();
    }

    public function attach(Tenant $tenant, User $user, string $id, \Illuminate\Http\UploadedFile $file): Attachment {
        $comment = $this->comment($tenant, $user, $id, true);
        $this->own($tenant, $user, $comment);
        if ($comment->attachments()->count() >= 5) {
            throw ValidationException::withMessages(['file' => 'Limite de 5 anexos por comentário.']);
        }
        $path = $file->store('tenants/' . $tenant->id . '/comments/' . $comment->id, 'local');
        return $comment->attachments()->create([
            'tenant_id' => $tenant->id, 'tenant_user_id' => $comment->tenant_user_id,
            'original_name' => $file->getClientOriginalName(), 'storage_path' => $path,
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream', 'size_bytes' => $file->getSize(),
        ]);
    }

    public function download(Tenant $tenant, User $user, string $id): \Symfony\Component\HttpFoundation\StreamedResponse {
        $file = Attachment::where('tenant_id', $tenant->id)->findOrFail($id);
        $this->comment($tenant, $user, $file->comment_id);
        abort_unless(Storage::disk('local')->exists($file->storage_path), 404);
        return Storage::disk('local')->download($file->storage_path, $file->original_name);
    }
}
