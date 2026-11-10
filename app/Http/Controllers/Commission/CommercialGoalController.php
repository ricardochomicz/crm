<?php

namespace App\Http\Controllers\Commission;

use App\Actions\Commission\FilterCommercialGoalAction;
use App\Actions\Commission\SaveCommercialGoalAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Commission\IndexCommercialGoalRequest;
use App\Http\Requests\Commission\BulkSaveCommercialGoalsRequest;
use Illuminate\Validation\ValidationException;
use App\Http\Requests\Commission\SaveCommercialGoalRequest;
use App\Http\Resources\Commission\CommercialGoalResource;

class CommercialGoalController extends Controller
{
    public function index(
        IndexCommercialGoalRequest $request,
        string $tenantId,
        FilterCommercialGoalAction $action
    ): array {
        $tenant = $request->attributes->get('active_tenant');

        return [
            'data' => CommercialGoalResource::collection(
                $action->execute($tenant->id, $request->validated())
            ),
            'meta' => [
                'can_manage' => $request->user()->can('manageCommercialGoals', $tenant),
            ],
        ];
    }

    public function store(
        SaveCommercialGoalRequest $request,
        string $tenantId,
        SaveCommercialGoalAction $action
    ): array {
        return ['data' => new CommercialGoalResource($action->execute(
            $request->attributes->get('active_tenant')->id,
            $request->validated()
        ))];
    }

    public function bulkStore(
        BulkSaveCommercialGoalsRequest $request,
        string $tenantId,
        SaveCommercialGoalAction $action
    ): array {
        $tenant = $request->attributes->get('active_tenant');
        $data = $request->validated();
        $memberIds = $data['responsible_membership_ids'];
        unset($data['responsible_membership_ids']);

        $created = [];
        $skipped = [];

        foreach ($memberIds as $memberId) {
            try {
                $goal = $action->execute($tenant->id, [
                    ...$data,
                    'responsible_membership_id' => $memberId,
                ]);
                $created[] = $goal->id;
            } catch (ValidationException $exception) {
                $skipped[] = [
                    'responsible_membership_id' => $memberId,
                    'reason' => collect($exception->errors())->flatten()->first() ?? 'Meta não cadastrada.',
                ];
            }
        }

        return [
            'data' => [
                'created_count' => count($created),
                'skipped_count' => count($skipped),
                'created_ids' => $created,
                'skipped' => $skipped,
            ],
        ];
    }

    public function update(
        SaveCommercialGoalRequest $request,
        string $tenantId,
        string $goalId,
        SaveCommercialGoalAction $action
    ): array {
        return ['data' => new CommercialGoalResource($action->execute(
            $request->attributes->get('active_tenant')->id,
            $request->validated(),
            $goalId
        ))];
    }
}
