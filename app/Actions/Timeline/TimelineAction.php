<?php
namespace App\Actions\Timeline;

use App\Actions\Tenant\ResolveTenantPermissionsAction;
use App\Enums\TenantPermission;
use App\Enums\TenantUserRole;
use App\Models\Attachment;
use App\Models\Opportunity;
use App\Models\CommercialOrder;
use Illuminate\Database\Eloquent\Builder;
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

    public function list(Tenant $tenant, User $user, string $type, string $id, ?string $origin = null): array
    {
        $this->authorize($tenant, $user, $type, $id);

        $member = $this->permissions->membership($user, $tenant);
        $memberId = $member?->id;
        $query = Comment::query()->where('tenant_id', $tenant->id);

        if ($type === 'client') {
            $canViewOpportunities = $this->permissions->allows($user, $tenant, TenantPermission::ViewOpportunities);
            $canViewAll = $member && in_array($member->role, [TenantUserRole::Owner, TenantUserRole::Manager], true);

            $query->where(function (Builder $query) use ($tenant, $id, $memberId, $canViewOpportunities, $canViewAll): void {
                $query->where(function (Builder $direct) use ($id): void {
                    $direct->where('commentable_type', 'client')->where('commentable_id', $id);
                });

                if ($canViewOpportunities) {
                    $opportunityIds = Opportunity::query()
                        ->where('tenant_id', $tenant->id)
                        ->where('client_id', $id)
                        ->when(!$canViewAll, fn (Builder $query) => $query->where('tenant_user_id', $memberId))
                        ->select('id');

                    $query->orWhere(function (Builder $related) use ($opportunityIds): void {
                        $related->where('commentable_type', 'opportunity')
                            ->whereIn('commentable_id', $opportunityIds);
                    });

                    $orderIds = CommercialOrder::query()
                        ->where('tenant_id', $tenant->id)
                        ->whereIn('opportunity_id', $opportunityIds)
                        ->select('id');

                    $query->orWhere(function (Builder $related) use ($orderIds): void {
                        $related->where('commentable_type', 'commercial_order')
                            ->whereIn('commentable_id', $orderIds);
                    });
                }
            });
        } else {
            $query->where('commentable_type', $type)->where('commentable_id', $id);
        }

        if ($type === 'client' && in_array($origin, ['client', 'opportunity', 'commercial_order'], true)) {
            $query->where('commentable_type', $origin);
        }

        $page = $query->with(['author', 'attachments'])->latest()->paginate(20);
        $opportunityIds = $page->getCollection()->where('commentable_type', 'opportunity')->pluck('commentable_id')->unique();
        $orderIds = $page->getCollection()->where('commentable_type', 'commercial_order')->pluck('commentable_id')->unique();

        $opportunities = Opportunity::query()->where('tenant_id', $tenant->id)
            ->whereIn('id', $opportunityIds)->get(['id', 'code', 'title'])->keyBy('id');
        $orders = CommercialOrder::query()->where('tenant_id', $tenant->id)
            ->whereIn('id', $orderIds)->get(['id', 'opportunity_id'])->keyBy('id');

        return $page->through(function (Comment $comment) use ($memberId, $type, $opportunities, $orders): array {
            $data = $comment->toArray();
            $data['can_edit'] = $comment->tenant_user_id === $memberId;
            $data['origin'] = ['type' => $comment->commentable_type, 'id' => $comment->commentable_id];

            if ($type === 'client' && $comment->commentable_type === 'opportunity') {
                $opportunity = $opportunities->get($comment->commentable_id);
                $data['origin']['code'] = $opportunity?->code;
                $data['origin']['title'] = $opportunity?->title;
            }

            if ($type === 'client' && $comment->commentable_type === 'commercial_order') {
                $order = $orders->get($comment->commentable_id);
                $opportunity = $order ? $opportunities->get($order->opportunity_id) : null;
                $data['origin']['code'] = $opportunity?->code;
                $data['origin']['title'] = $opportunity?->title;
            }

            return $data;
        })->toArray();
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
