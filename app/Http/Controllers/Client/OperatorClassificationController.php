<?php

namespace App\Http\Controllers\Client;

use App\Actions\Client\FilterOperatorClassificationsAction;
use App\Actions\Client\SaveOperatorClassificationAction;
use App\Actions\Client\SaveRenewalRuleAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\IndexOperatorClassificationRequest;
use App\Http\Requests\Client\SaveOperatorClassificationRequest;
use App\Http\Requests\Client\SaveRenewalRuleRequest;
use App\Http\Resources\Client\ClassificationRenewalRuleResource;
use App\Http\Resources\Client\OperatorClassificationResource;

class OperatorClassificationController extends Controller
{
    public function index(
        IndexOperatorClassificationRequest $request,
        string $tenantId,
        FilterOperatorClassificationsAction $action
    ) {
        $tenant = $request->attributes->get('active_tenant');
        $classifications = $action->execute($tenant->id, $request->validated());

        return OperatorClassificationResource::collection($classifications)
            ->additional(['meta' => [
                'can_manage' => $request->user()->can('manageOperatorClassifications', $tenant),
            ]]);
    }

    public function globalIndex(
        IndexOperatorClassificationRequest $request,
        FilterOperatorClassificationsAction $action
    ) {
        return OperatorClassificationResource::collection($action->execute(null, $request->validated()));
    }

    public function store(
        SaveOperatorClassificationRequest $request,
        string $tenantId,
        SaveOperatorClassificationAction $action
    ): OperatorClassificationResource {
        $classification = $action->execute(
            $request->attributes->get('active_tenant')->id,
            $request->validated()
        );

        return new OperatorClassificationResource($classification);
    }

    public function update(
        SaveOperatorClassificationRequest $request,
        string $tenantId,
        int $classificationId,
        SaveOperatorClassificationAction $action
    ): OperatorClassificationResource {
        $classification = $action->execute(
            $request->attributes->get('active_tenant')->id,
            $request->validated(),
            $classificationId
        );

        return new OperatorClassificationResource($classification);
    }

    public function globalStore(
        SaveOperatorClassificationRequest $request,
        SaveOperatorClassificationAction $action
    ): OperatorClassificationResource {
        return new OperatorClassificationResource($action->execute(null, $request->validated()));
    }

    public function globalUpdate(
        SaveOperatorClassificationRequest $request,
        int $classificationId,
        SaveOperatorClassificationAction $action
    ): OperatorClassificationResource {
        return new OperatorClassificationResource($action->execute(null, $request->validated(), $classificationId));
    }

    public function saveRule(
        SaveRenewalRuleRequest $request,
        string $tenantId,
        int $classificationId,
        SaveRenewalRuleAction $action
    ): ClassificationRenewalRuleResource {
        return new ClassificationRenewalRuleResource($action->execute(
            $request->attributes->get('active_tenant')->id,
            $classificationId,
            $request->validated()
        ));
    }

    public function globalSaveRule(
        SaveRenewalRuleRequest $request,
        int $classificationId,
        SaveRenewalRuleAction $action
    ): ClassificationRenewalRuleResource {
        return new ClassificationRenewalRuleResource($action->execute(null, $classificationId, $request->validated()));
    }

    public function updateRule(
        SaveRenewalRuleRequest $request,
        string $tenantId,
        int $classificationId,
        string $ruleId,
        SaveRenewalRuleAction $action
    ): ClassificationRenewalRuleResource {
        return new ClassificationRenewalRuleResource($action->execute(
            $request->attributes->get('active_tenant')->id,
            $classificationId,
            $request->validated(),
            $ruleId
        ));
    }

    public function globalUpdateRule(
        SaveRenewalRuleRequest $request,
        int $classificationId,
        string $ruleId,
        SaveRenewalRuleAction $action
    ): ClassificationRenewalRuleResource {
        return new ClassificationRenewalRuleResource($action->execute(null, $classificationId, $request->validated(), $ruleId));
    }
}
