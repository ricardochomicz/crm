<?php

use App\Http\Controllers\AfterSales\ProtocolController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Calendar\CalendarEventController;
use App\Http\Controllers\Calendar\ActivityNotificationController;
use App\Http\Controllers\Client\ClientContractConsolidationOptionsController;
use App\Http\Controllers\Client\ClientController;
use App\Http\Controllers\Client\ClientDocumentController;
use App\Http\Controllers\Client\ClientOperatorClassificationController;
use App\Http\Controllers\Client\ClientPortfolioRedistributionController;
use App\Http\Controllers\Client\ClientTransferController;
use App\Http\Controllers\Client\ConsolidateClientOrderController;
use App\Http\Controllers\Client\ContractPortfolioController;
use App\Http\Controllers\Client\HistoricalOrderRenewalController;
use App\Http\Controllers\Client\OperatorClassificationController;
use App\Http\Controllers\Client\UpdateClientContractClassificationController;
use App\Http\Controllers\Commercial\CommercialOrderController;
use App\Http\Controllers\Commercial\ConsultantProductivityController;
use App\Http\Controllers\Commercial\ConsultantPrioritiesController;
use App\Http\Controllers\Commercial\OrderActivationController;
use App\Http\Controllers\Commercial\OrderWorkflowController;
use App\Http\Controllers\Commission\CommercialGoalController;
use App\Http\Controllers\Commission\CommercialParticipantController;
use App\Http\Controllers\Commission\CommissionTableController;
use App\Http\Controllers\Commission\OrderCommissionController;
use App\Http\Controllers\Commission\TeamCommissionRuleController;
use App\Http\Controllers\CustomerPortal\CustomerPortalController;
use App\Http\Controllers\Operator\ListAvailableOperatorsController;
use App\Http\Controllers\Operator\ListOperatorsController;
use App\Http\Controllers\Operator\ListTenantOperatorsController;
use App\Http\Controllers\Operator\StoreOperatorController;
use App\Http\Controllers\Operator\SyncTenantOperatorsController;
use App\Http\Controllers\Operator\UpdateOperatorController;
use App\Http\Controllers\Opportunity\OpportunityComparisonController;
use App\Http\Controllers\Opportunity\OpportunityController;
use App\Http\Controllers\Opportunity\OpportunityHighlightController;
use App\Http\Controllers\Opportunity\OpportunityProposalController;
use App\Http\Controllers\OrderStatus\OrderStatusController;
use App\Http\Controllers\OrderStatus\PlatformOrderStatusController;
use App\Http\Controllers\OrderType\OrderTypeController;
use App\Http\Controllers\Product\ListProductsController;
use App\Http\Controllers\Product\StoreProductController;
use App\Http\Controllers\Product\StorePromotionController;
use App\Http\Controllers\Product\UpdateProductController;
use App\Http\Controllers\Product\UpdatePromotionController;
use App\Http\Controllers\SubscriptionPlan\ListSubscriptionPlansController;
use App\Http\Controllers\Team\ListTeamMembersController;
use App\Http\Controllers\Team\ListTeamsController;
use App\Http\Controllers\Team\StoreTeamController;
use App\Http\Controllers\Team\UpdateTeamController;
use App\Http\Controllers\Team\UpdateTeamSupervisorController;
use App\Http\Controllers\Tenant\Audit\ListTenantPermissionChangesController;
use App\Http\Controllers\Tenant\Audit\ListTenantStatusChangesController;
use App\Http\Controllers\Tenant\Invitations\AcceptTenantInvitationController;
use App\Http\Controllers\Tenant\Invitations\ListTenantInvitationsController;
use App\Http\Controllers\Tenant\Invitations\ResendTenantInvitationController;
use App\Http\Controllers\Tenant\Invitations\RevokeTenantInvitationController;
use App\Http\Controllers\Tenant\Invitations\ShowTenantInvitationController;
use App\Http\Controllers\Tenant\Invitations\StoreTenantInvitationController;
use App\Http\Controllers\Tenant\Management\ListTenantsController;
use App\Http\Controllers\Tenant\Management\SelectTenantController;
use App\Http\Controllers\Tenant\Management\ShowActiveTenantController;
use App\Http\Controllers\Tenant\Management\StoreTenantController;
use App\Http\Controllers\Tenant\Management\UpdateTenantController;
use App\Http\Controllers\Tenant\Management\UpdateTenantLogoController;
use App\Http\Controllers\Tenant\Members\ListTenantMembersController;
use App\Http\Controllers\Tenant\Members\RemoveTenantMemberController;
use App\Http\Controllers\Tenant\Members\ShowTenantMemberProfileController;
use App\Http\Controllers\Tenant\Members\StoreTenantMemberController;
use App\Http\Controllers\Tenant\Members\UpdateTenantMemberProfileController;
use App\Http\Controllers\Tenant\Members\UpdateTenantMemberRoleController;
use App\Http\Controllers\Tenant\Members\UpdateTenantMemberStatusController;
use App\Http\Controllers\Tenant\Members\UpdateTenantMemberTeamController;
use App\Http\Controllers\Tenant\Platform\ListPlatformTenantsController;
use App\Http\Controllers\Tenant\Platform\ShowPlatformTenantController;
use App\Http\Controllers\Tenant\Platform\UpdatePlatformTenantStatusController;
use App\Http\Controllers\Tenant\Roles\ShowTenantRolePermissionsController;
use App\Http\Controllers\Tenant\Roles\TenantRoleController;
use App\Http\Controllers\Tenant\Roles\UpdateTenantRolePermissionsController;
use App\Http\Controllers\Tenant\Subscriptions\ShowTenantSubscriptionController;
use App\Http\Controllers\Tenant\Units\ListTenantUnitAssignmentsController;
use App\Http\Controllers\Tenant\Units\ListTenantUnitsController;
use App\Http\Controllers\Tenant\Units\StoreTenantUnitController;
use App\Http\Controllers\Tenant\Units\UpdateTenantUnitAssignmentsController;
use App\Http\Controllers\Tenant\Units\UpdateTenantUnitController;
use App\Http\Controllers\Timeline\TimelineController;
use App\Http\Controllers\Search\GlobalSearchController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::prefix('auth')->name('auth.')->group(function (): void {
        Route::post('/login', LoginController::class)
            ->middleware('throttle:login')
            ->name('login');

        Route::post('/register', RegisterController::class)
            ->middleware('throttle:5,1')
            ->name('register');

        Route::post('/password/email', [PasswordController::class, 'sendLink'])
            ->middleware('throttle:5,1')
            ->name('password.email');

        Route::post('/password/reset', [PasswordController::class, 'update'])
            ->middleware('throttle:10,1')
            ->name('password.reset');

        Route::post('/logout', LogoutController::class)
            ->middleware('auth:sanctum')
            ->name('logout');
    });

    Route::post('/public/customer-portal/request-code', [CustomerPortalController::class, 'requestCode'])->middleware('throttle:5,1');
    Route::post('/public/customer-portal/verify', [CustomerPortalController::class, 'verify'])->middleware('throttle:10,1');

    Route::prefix('public/customer-portal/{tenantId}')->where(['tenantId' => '[0-9a-fA-F-]{36}'])->group(function (): void {
        Route::get('/orders', [CustomerPortalController::class, 'orders'])->middleware('throttle:30,1');
        Route::get('/orders/{orderId}', [CustomerPortalController::class, 'showOrder'])->whereUuid('orderId')->middleware('throttle:30,1');
        Route::post('/logout', [CustomerPortalController::class, 'logout'])->middleware('throttle:10,1');
    });

    Route::get('/public/proposals/{token}', [OpportunityProposalController::class, 'publicShow'])->where('token', '[A-Za-z0-9]{64}')->middleware('throttle:30,1');

    Route::get('/public/comparisons/{token}', [OpportunityComparisonController::class, 'publicShow'])->where('token', '[A-Za-z0-9]{64}')->middleware('throttle:30,1');

    Route::get('/invitations/{token}', ShowTenantInvitationController::class)
        ->where('token', '[A-Za-z0-9]{64}')
        ->middleware('throttle:30,1')
        ->name('tenant-invitations.show');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/me', function (Request $request): array {
            $user = $request->user();

            return [
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'is_platform_admin' => (bool) $user->is_platform_admin,
                ],
            ];
        })->name('me');

        Route::post('/invitations/accept', AcceptTenantInvitationController::class)
            ->middleware('throttle:10,1')
            ->name('tenant-invitations.accept');

        Route::get('/operators', ListAvailableOperatorsController::class)->name('operators.available');

        Route::get('/order-types', [OrderTypeController::class, 'available']);

        Route::get('/subscription-plans', ListSubscriptionPlansController::class)
            ->name('subscription-plans.index');

        Route::get('/tenants', ListTenantsController::class)
            ->name('tenants.index');

        Route::post('/tenants', StoreTenantController::class)
            ->middleware('throttle:5,1')
            ->name('tenants.store');

        Route::put('/active-tenant', SelectTenantController::class)
            ->name('active-tenant.update');

        Route::get('/active-tenant', ShowActiveTenantController::class)
            ->middleware('tenant.active:selected')
            ->name('active-tenant.show');

        Route::prefix('admin')
            ->name('admin.')
            ->middleware('can:access-platform-admin')
            ->group(function (): void {
                Route::get('/me', function (Request $request): array {
                    $user = $request->user();

                    return [
                        'data' => [
                            'id' => $user->id,
                            'name' => $user->name,
                            'email' => $user->email,
                            'is_platform_admin' => true,
                        ],
                    ];
                })->name('me');

                Route::get('/operator-classifications', [OperatorClassificationController::class, 'globalIndex']);
                Route::post('/operator-classifications', [OperatorClassificationController::class, 'globalStore']);
                Route::patch('/operator-classifications/{classificationId}', [OperatorClassificationController::class, 'globalUpdate'])->whereNumber('classificationId');
                Route::patch('/operator-classifications/{classificationId}/renewal-rules/{ruleId}', [OperatorClassificationController::class, 'globalUpdateRule'])->whereNumber('classificationId')->whereUuid('ruleId');
                Route::post('/operator-classifications/{classificationId}/renewal-rules', [OperatorClassificationController::class, 'globalSaveRule'])->whereNumber('classificationId');
                Route::get('/order-statuses', [PlatformOrderStatusController::class, 'index']);
                Route::post('/order-statuses', [PlatformOrderStatusController::class, 'store']);
                Route::patch('/order-statuses/{statusId}', [PlatformOrderStatusController::class, 'update'])->whereUuid('statusId');
                Route::delete('/order-statuses/{statusId}', [PlatformOrderStatusController::class, 'destroy'])->whereUuid('statusId');
                Route::get('/order-types', [OrderTypeController::class, 'index']);
                Route::post('/order-types', [OrderTypeController::class, 'store']);
                Route::patch('/order-types/{orderTypeId}', [OrderTypeController::class, 'update'])->whereUuid('orderTypeId');
                Route::get('/operators', ListOperatorsController::class)->name('operators.index');
                Route::post('/operators', StoreOperatorController::class)->middleware('throttle:20,1')->name('operators.store');
                Route::patch('/operators/{operator}', UpdateOperatorController::class)->whereNumber('operator')->middleware('throttle:20,1')->name('operators.update');

                Route::get('/tenants', ListPlatformTenantsController::class)
                    ->name('tenants.index');

                Route::get('/tenants/{tenant}', ShowPlatformTenantController::class)
                    ->whereUuid('tenant')
                    ->name('tenants.show');

                Route::get(
                    '/tenants/{tenant}/status-changes',
                    ListTenantStatusChangesController::class,
                )
                    ->whereUuid('tenant')
                    ->name('tenants.status-changes.index');

                Route::patch(
                    '/tenants/{tenant}/status',
                    UpdatePlatformTenantStatusController::class,
                )
                    ->whereUuid('tenant')
                    ->middleware('throttle:10,1')
                    ->name('tenants.status.update');
            });

        Route::prefix('tenants/{tenantId}')
            ->whereUuid('tenantId')
            ->middleware('tenant.active')
            ->group(function (): void {
                Route::get('/', ShowActiveTenantController::class)
                    ->name('tenants.show');

                Route::get('/units/{unitId}/assignments', ListTenantUnitAssignmentsController::class)->whereUuid('unitId')->name('tenant-units.assignments.index');
                Route::get('/units', ListTenantUnitsController::class)->name('tenant-units.index');
                Route::get('/consultants/productivity', ConsultantProductivityController::class);
                Route::put('/consultants/productivity/monthly-target', [ConsultantProductivityController::class, 'saveMonthlyTarget']);
                Route::get('/consultants/productivity/goals', [ConsultantProductivityController::class, 'goalConfigurations']);
                Route::get('/consultants/productivity/records', [ConsultantProductivityController::class, 'records']);
                Route::get('/commercial-participants', [CommercialParticipantController::class, 'index']);
                Route::get('/commercial-participants/{participantId}/commissions', [CommercialParticipantController::class, 'commissions'])->whereUuid('participantId');
                Route::get('/commercial-goals', [CommercialGoalController::class, 'index']);
                Route::get('/team-commissions', [TeamCommissionRuleController::class, 'index']);
                Route::get('/team-commissions/options', [TeamCommissionRuleController::class, 'options']);
                Route::get('/commissions', [CommissionTableController::class, 'index'])->name('commissions.index');
                Route::get('/commissions/order-types', [CommissionTableController::class, 'orderTypes']);
                Route::get('/commissions/settings', [CommissionTableController::class, 'settings'])->name('commissions.settings.show');
                Route::get('/global-search', GlobalSearchController::class)->middleware('throttle:60,1');
                Route::get('/opportunity-funnel-stages', [OpportunityController::class, 'stages']);
                Route::get('/timeline/{type}/{id}', [TimelineController::class, 'index'])->whereUuid('id')->whereIn('type', ['opportunity', 'client', 'commercial_order']);
                Route::get('/timeline/attachments/{id}/download', [TimelineController::class, 'download'])->whereUuid('id');
                Route::get('/opportunities/funnel-overview', [OpportunityController::class, 'funnelOverview'])->name('opportunities.funnel-overview');
                Route::get('/opportunities/overview', [OpportunityController::class, 'overview'])->name('opportunities.overview');
                Route::get('/commercial-radar/members', \App\Http\Controllers\Commercial\CommercialRadarMembersController::class)->middleware('throttle:60,1');
                Route::get('/commercial-radar', \App\Http\Controllers\Commercial\CommercialRadarController::class)->middleware('throttle:60,1');
                Route::get('/commercial-priorities', [ConsultantPrioritiesController::class, 'index']);
                Route::put('/commercial-priorities/acknowledge-all', [ConsultantPrioritiesController::class, 'acknowledgeAll'])->middleware('subscription.writable');
                Route::put('/commercial-priorities/renewals/{contractId}/acknowledge', [ConsultantPrioritiesController::class, 'acknowledgeRenewal'])->whereUuid('contractId')->middleware('subscription.writable');
                Route::put('/commercial-priorities/{opportunityId}/acknowledge', [ConsultantPrioritiesController::class, 'acknowledge'])->whereUuid('opportunityId')->middleware('subscription.writable');
                Route::get('/opportunities', [OpportunityController::class, 'index']);
                Route::get('/opportunities/highlights', [OpportunityHighlightController::class, 'index']);
                Route::put('/opportunities/{opportunityId}/highlight', [OpportunityHighlightController::class, 'update'])->whereUuid('opportunityId')->middleware('subscription.writable');
                Route::get('/opportunities/{opportunityId}', [OpportunityController::class, 'show'])->whereUuid('opportunityId');
                Route::get('/opportunities/{opportunityId}/proposals', [OpportunityProposalController::class, 'index'])->whereUuid('opportunityId');
                Route::get('/opportunities/{opportunityId}/comparisons', [OpportunityComparisonController::class, 'index'])->whereUuid('opportunityId');
                Route::get('/after-sales/protocols', [ProtocolController::class, 'index']);
                Route::get('/after-sales/options', [ProtocolController::class, 'options']);
                Route::get('/after-sales/protocols/{protocolId}', [ProtocolController::class, 'show'])->whereUuid('protocolId');
                Route::get('/activities', [CalendarEventController::class, 'activities']);
                Route::get('/activity-notifications', [ActivityNotificationController::class, 'index']);
                Route::patch('/activity-notifications/read-all', [ActivityNotificationController::class, 'readAll']);
                Route::patch('/activity-notifications/{notificationId}/read', [ActivityNotificationController::class, 'read'])->whereUuid('notificationId');
                Route::get('/activities/overview', [CalendarEventController::class, 'activitiesOverview']);
                Route::get('/calendar/assignees', [CalendarEventController::class, 'assignees']);
                Route::get('/calendar/events/{eventId}', [CalendarEventController::class, 'show'])->whereUuid('eventId');
                Route::get('/calendar/events/{eventId}/history', [CalendarEventController::class, 'history'])->whereUuid('eventId');
                Route::get('/calendar/events', [CalendarEventController::class, 'index']);
                Route::get('/clients', [ClientController::class, 'index']);
                Route::get('/clients/portfolio-redistributions', [ClientPortfolioRedistributionController::class, 'index']);
                Route::get('/clients/portfolio-redistributions/{sourceId}/clients', [ClientPortfolioRedistributionController::class, 'clients'])->whereUuid('sourceId');
                Route::post('/clients/portfolio-redistributions', [ClientPortfolioRedistributionController::class, 'store']);
                Route::get('/clients/transfer-lookup', [ClientTransferController::class, 'lookup']);
                Route::get('/clients/transfers', [ClientTransferController::class, 'index']);
                Route::get('/clients/options', [ClientController::class, 'options']);
                Route::get('/client-portfolio', ContractPortfolioController::class);
                Route::get('/client-portfolio/consolidated', [ContractPortfolioController::class, 'consolidated']);
                Route::get('/clients/{clientId}/documents', [ClientDocumentController::class, 'index'])->whereUuid('clientId');
                Route::get('/clients/{clientId}/documents/{documentId}/download', [ClientDocumentController::class, 'download'])->whereUuid('clientId')->whereUuid('documentId');
                Route::get('/clients/{clientId}', [ClientController::class, 'show'])->whereUuid('clientId');
                Route::get('/operator-classifications', [OperatorClassificationController::class, 'index']);
                Route::get('/order-statuses', [OrderStatusController::class, 'index']);
                Route::get('/commercial-orders/{orderId}/commissions', [OrderCommissionController::class, 'index'])->whereUuid('orderId')->name('order-commissions.index');
                Route::get('/commercial-orders/{orderId}/commissions/options', [OrderCommissionController::class, 'options'])->whereUuid('orderId');
                Route::get('/commercial-orders', [CommercialOrderController::class, 'index']);
                Route::get('/commercial-orders/{orderId}', [OrderWorkflowController::class, 'show'])->whereUuid('orderId');
                Route::get('/commercial-orders/options', [CommercialOrderController::class, 'options']);
                Route::get('/products', ListProductsController::class)->name('products.index');
                Route::get('/operators', ListTenantOperatorsController::class)->name('tenant-operators.index');
                Route::middleware('subscription.writable')->group(function (): void {
                    Route::post('/commercial-orders/{orderId}/commissions/revisions', [OrderCommissionController::class, 'store'])->whereUuid('orderId')->middleware('throttle:10,1')->name('order-commissions.store');
                    Route::post('/after-sales/protocols', [ProtocolController::class, 'store']);
                    Route::patch('/after-sales/protocols/{protocolId}', [ProtocolController::class, 'update'])->whereUuid('protocolId');
                    Route::post('/opportunities/{opportunityId}/proposals', [OpportunityProposalController::class, 'store'])->whereUuid('opportunityId');
                    Route::post('/opportunities/{opportunityId}/proposals/{proposalId}/revoke', [OpportunityProposalController::class, 'revoke'])->whereUuid('opportunityId')->whereUuid('proposalId');
                    Route::post('/opportunities/{opportunityId}/comparisons', [OpportunityComparisonController::class, 'store'])->whereUuid('opportunityId');
                    Route::post('/opportunities/{opportunityId}/comparisons/{comparisonId}/revoke', [OpportunityComparisonController::class, 'revoke'])->whereUuid('opportunityId')->whereUuid('comparisonId');
                    Route::post('/commercial-orders/{orderId}/activate-items', [OrderActivationController::class, 'store'])->whereUuid('orderId');
                    Route::post('/commercial-orders/{orderId}/complete', [OrderActivationController::class, 'complete'])->whereUuid('orderId');
                    Route::patch('/commercial-orders/{orderId}/workflow', [OrderWorkflowController::class, 'update'])->whereUuid('orderId');
                    Route::post('/order-statuses', [OrderStatusController::class, 'store']);
                    Route::patch('/order-statuses/{statusId}', [OrderStatusController::class, 'update'])->whereUuid('statusId');
                    Route::patch('/order-statuses/{statusId}/initial', [OrderStatusController::class, 'initial'])->whereUuid('statusId');
                    Route::delete('/order-statuses/{statusId}', [OrderStatusController::class, 'destroy'])->whereUuid('statusId');
                    Route::post('/commercial-participants', [CommercialParticipantController::class, 'store']);
                    Route::patch('/commercial-participants/{participantId}', [CommercialParticipantController::class, 'update'])->whereUuid('participantId');
                    Route::post('/commercial-participants/{participantId}/commissions', [CommercialParticipantController::class, 'saveCommission'])->whereUuid('participantId');
                    Route::patch('/commercial-participants/{participantId}/commissions/{overrideId}', [CommercialParticipantController::class, 'saveCommission'])->whereUuid('participantId')->whereUuid('overrideId');
                    Route::delete('/commercial-participants/{participantId}/commissions/{overrideId}', [CommercialParticipantController::class, 'deleteCommission'])->whereUuid('participantId')->whereUuid('overrideId');
                    Route::post('/commercial-goals', [CommercialGoalController::class, 'store']);
                    Route::post('/commercial-goals/bulk', [CommercialGoalController::class, 'bulkStore']);
                    Route::patch('/commercial-goals/{goalId}', [CommercialGoalController::class, 'update'])->whereUuid('goalId');
                    Route::post('/team-commissions', [TeamCommissionRuleController::class, 'store']);
                    Route::patch('/team-commissions/{ruleId}', [TeamCommissionRuleController::class, 'update'])->whereUuid('ruleId');
                    Route::patch('/commissions/{commissionId}', [CommissionTableController::class, 'update'])->whereUuid('commissionId');
                    Route::put('/commissions/settings', [CommissionTableController::class, 'updateSettings']);
                    Route::post('/commissions', [CommissionTableController::class, 'store'])->name('commissions.store');
                    Route::post('/timeline/{type}/{id}', [TimelineController::class, 'store'])->whereUuid('id')->whereIn('type', ['opportunity', 'client', 'commercial_order']);
                    Route::patch('/timeline/comments/{id}', [TimelineController::class, 'update'])->whereUuid('id');
                    Route::delete('/timeline/comments/{id}', [TimelineController::class, 'destroy'])->whereUuid('id');
                    Route::post('/timeline/comments/{id}/attachments', [TimelineController::class, 'upload'])->whereUuid('id');
                    Route::post('/opportunities', [OpportunityController::class, 'store']);
                    Route::post('/opportunities/{opportunityId}/recover', [OpportunityController::class, 'recover'])->whereUuid('opportunityId')->name('opportunities.recover');
                    Route::post('/opportunities/{opportunityId}/mark-lost', [OpportunityController::class, 'markLost'])->whereUuid('opportunityId')->name('opportunities.sale.lost');
                    Route::post('/opportunities/{opportunityId}/resend-to-backoffice', [OpportunityController::class, 'resendToBackoffice'])->whereUuid('opportunityId')->name('opportunities.resend');
                    Route::post('/opportunities/{opportunityId}/confirm-sale', [OpportunityController::class, 'confirmSale'])->whereUuid('opportunityId')->name('opportunities.sale.confirm');
                    Route::patch('/opportunities/{opportunityId}/stage', [OpportunityController::class, 'moveStage'])->whereUuid('opportunityId')->name('opportunities.stage.move');
                    Route::patch('/opportunities/{opportunityId}', [OpportunityController::class, 'update'])->whereUuid('opportunityId');
                    Route::post('/calendar/events', [CalendarEventController::class, 'store']);
                    Route::patch('/calendar/events/{eventId}', [CalendarEventController::class, 'update'])->whereUuid('eventId');
                    Route::delete('/calendar/events/{eventId}', [CalendarEventController::class, 'destroy'])->whereUuid('eventId');
                    Route::post('/clients', [ClientController::class, 'store']);
                    Route::put('/clients/{clientId}/operators/{operatorId}/classification', [ClientOperatorClassificationController::class, 'update'])->whereUuid('clientId')->whereNumber('operatorId');
                    Route::post('/client-portfolio/orders/{orderId}/renew', HistoricalOrderRenewalController::class)->whereUuid('orderId');
                    Route::patch('/clients/{clientId}', [ClientController::class, 'update'])->whereUuid('clientId');
                    Route::get('/clients/{clientId}/consolidation-options', ClientContractConsolidationOptionsController::class)->whereUuid('clientId');
                    Route::post('/clients/transfers', [ClientTransferController::class, 'store']);
                    Route::post('/clients/transfers/{transferId}/review', [ClientTransferController::class, 'review'])->whereUuid('transferId');
                    Route::post('/clients/{clientId}/orders/{orderId}/consolidate', ConsolidateClientOrderController::class)->whereUuid('clientId')->whereUuid('orderId');
                    Route::patch('/clients/{clientId}/contracts/{orderId}/classification', UpdateClientContractClassificationController::class)->whereUuid('clientId')->whereUuid('orderId');
                    Route::delete('/clients/{clientId}', [ClientController::class, 'destroy'])->whereUuid('clientId');
                    Route::post('/clients/{clientId}/documents', [ClientDocumentController::class, 'store'])->whereUuid('clientId');
                    Route::post('/clients/{clientId}/contacts', [ClientController::class, 'saveContact'])->whereUuid('clientId');
                    Route::patch('/clients/{clientId}/contacts/{contactId}', [ClientController::class, 'saveContact'])->whereUuid('clientId')->whereUuid('contactId');
                    Route::post('/operator-classifications', [OperatorClassificationController::class, 'store']);
                    Route::patch('/operator-classifications/{classificationId}', [OperatorClassificationController::class, 'update'])->whereNumber('classificationId');
                    Route::patch('/operator-classifications/{classificationId}/renewal-rules/{ruleId}', [OperatorClassificationController::class, 'updateRule'])->whereNumber('classificationId')->whereUuid('ruleId');
                    Route::post('/operator-classifications/{classificationId}/renewal-rules', [OperatorClassificationController::class, 'saveRule'])->whereNumber('classificationId');
                    Route::post('/commercial-orders', [CommercialOrderController::class, 'store']);
                    Route::patch('/commercial-orders/{orderId}', [CommercialOrderController::class, 'update'])->whereUuid('orderId');
                    Route::post('/products', StoreProductController::class)->name('products.store');
                    Route::patch('/products/{productId}', UpdateProductController::class)->whereUuid('productId')->name('products.update');
                    Route::post('/products/{productId}/promotions', StorePromotionController::class)->whereUuid('productId')->name('promotions.store');
                    Route::patch('/products/{productId}/promotions/{promotionId}', UpdatePromotionController::class)->whereUuid('productId')->whereUuid('promotionId')->name('promotions.update');
                    Route::put('/operators', SyncTenantOperatorsController::class)->middleware('throttle:20,1')->name('tenant-operators.update');
                    Route::post('/units', StoreTenantUnitController::class)->middleware('throttle:20,1')->name('tenant-units.store');
                    Route::patch('/units/{unitId}', UpdateTenantUnitController::class)->whereUuid('unitId')->name('tenant-units.update');
                    Route::patch('/units/{unitId}/assignments', UpdateTenantUnitAssignmentsController::class)->whereUuid('unitId')->name('tenant-units.assignments.update');
                });

                Route::get('/roles', [TenantRoleController::class, 'index'])->name('tenant-roles.index');
                Route::post('/roles', [TenantRoleController::class, 'store'])->middleware('subscription.writable')->name('tenant-roles.store');
                Route::patch('/roles/{roleId}', [TenantRoleController::class, 'update'])->whereUuid('roleId')->middleware('subscription.writable')->name('tenant-roles.update');

                Route::get('/role-permissions', ShowTenantRolePermissionsController::class)
                    ->name('tenant-role-permissions.show');
                Route::get('/permission-changes', ListTenantPermissionChangesController::class)
                    ->name('tenant-permission-changes.index');
                Route::put('/role-permissions/{role}', UpdateTenantRolePermissionsController::class)
                    ->whereIn('role', ['MANAGER', 'CONSULTANT', 'ASSISTANT'])
                    ->middleware(['subscription.writable', 'throttle:20,1'])
                    ->name('tenant-role-permissions.update');

                Route::get('/members', ListTenantMembersController::class)
                    ->name('tenant-members.index');
                Route::get('/members/{membershipId}/profile', ShowTenantMemberProfileController::class)
                    ->whereUuid('membershipId')->name('tenant-members.profile.show');

                Route::get('/subscription', ShowTenantSubscriptionController::class)
                    ->name('tenants.subscription.show');

                Route::get('/teams', ListTeamsController::class)
                    ->name('teams.index');

                Route::get('/teams/{teamId}/members', ListTeamMembersController::class)
                    ->whereUuid('teamId')
                    ->name('teams.members.index');

                Route::get('/invitations', ListTenantInvitationsController::class)
                    ->name('tenant-invitations.index');

                Route::post(
                    '/invitations/{invitationId}/revoke',
                    RevokeTenantInvitationController::class,
                )
                    ->whereUuid('invitationId')
                    ->middleware('throttle:10,1')
                    ->name('tenant-invitations.revoke');

                Route::middleware('subscription.writable')
                    ->group(function (): void {
                        Route::post('/logo', UpdateTenantLogoController::class)->name('tenants.logo.update');

                        Route::patch('/', UpdateTenantController::class)
                            ->name('tenants.update');

                        Route::patch('/members/{membershipId}/profile', UpdateTenantMemberProfileController::class)
                            ->whereUuid('membershipId')->name('tenant-members.profile.update');

                        Route::post('/members', StoreTenantMemberController::class)
                            ->middleware('throttle:10,1')
                            ->name('tenant-members.store');

                        Route::post('/invitations', StoreTenantInvitationController::class)
                            ->middleware('throttle:10,1')
                            ->name('tenant-invitations.store');

                        Route::post(
                            '/invitations/{invitationId}/resend',
                            ResendTenantInvitationController::class,
                        )
                            ->whereUuid('invitationId')
                            ->middleware('throttle:10,1')
                            ->name('tenant-invitations.resend');

                        Route::patch(
                            '/teams/{teamId}/supervisor',
                            UpdateTeamSupervisorController::class,
                        )
                            ->whereUuid('teamId')
                            ->middleware('throttle:10,1')
                            ->name('teams.supervisor.update');

                        Route::delete('/members/{membershipId}', RemoveTenantMemberController::class)
                            ->whereUuid('membershipId')
                            ->middleware('throttle:10,1')
                            ->name('tenant-members.destroy');

                        Route::patch(
                            '/members/{membershipId}/role',
                            UpdateTenantMemberRoleController::class,
                        )
                            ->whereUuid('membershipId')
                            ->middleware('throttle:10,1')
                            ->name('tenant-members.role.update');

                        Route::patch(
                            '/members/{membershipId}/status',
                            UpdateTenantMemberStatusController::class,
                        )
                            ->whereUuid('membershipId')
                            ->middleware('throttle:10,1')
                            ->name('tenant-members.status.update');

                        Route::patch(
                            '/members/{membershipId}/team',
                            UpdateTenantMemberTeamController::class,
                        )
                            ->whereUuid('membershipId')
                            ->middleware('throttle:10,1')
                            ->name('tenant-members.team.update');

                        Route::patch('/teams/{teamId}', UpdateTeamController::class)
                            ->whereUuid('teamId')
                            ->middleware('throttle:10,1')
                            ->name('teams.update');

                        Route::post('/teams', StoreTeamController::class)
                            ->name('teams.store');
                    });
            });
    });
});
