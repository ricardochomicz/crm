<?php

use App\Http\Controllers\Operator\ListOperatorsController;
use App\Http\Controllers\Operator\StoreOperatorController;
use App\Http\Controllers\Operator\UpdateOperatorController;
use App\Http\Controllers\Operator\ListTenantOperatorsController;
use App\Http\Controllers\Operator\SyncTenantOperatorsController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\SubscriptionPlan\ListSubscriptionPlansController;
use App\Http\Controllers\Team\ListTeamMembersController;
use App\Http\Controllers\Team\ListTeamsController;
use App\Http\Controllers\Team\StoreTeamController;
use App\Http\Controllers\Team\UpdateTeamController;
use App\Http\Controllers\Team\UpdateTeamSupervisorController;
use App\Http\Controllers\Tenant\Invitations\AcceptTenantInvitationController;
use App\Http\Controllers\Tenant\Platform\ListPlatformTenantsController;
use App\Http\Controllers\Tenant\Invitations\ListTenantInvitationsController;
use App\Http\Controllers\Tenant\Members\ListTenantMembersController;
use App\Http\Controllers\Tenant\Members\ShowTenantMemberProfileController;
use App\Http\Controllers\Tenant\Members\UpdateTenantMemberProfileController;
use App\Http\Controllers\Tenant\Audit\ListTenantPermissionChangesController;
use App\Http\Controllers\Tenant\Management\ListTenantsController;
use App\Http\Controllers\Tenant\Audit\ListTenantStatusChangesController;
use App\Http\Controllers\Tenant\Units\ListTenantUnitAssignmentsController;
use App\Http\Controllers\Tenant\Units\ListTenantUnitsController;
use App\Http\Controllers\Tenant\Invitations\ResendTenantInvitationController;
use App\Http\Controllers\Tenant\Invitations\RevokeTenantInvitationController;
use App\Http\Controllers\Tenant\Management\SelectTenantController;
use App\Http\Controllers\Tenant\Management\ShowActiveTenantController;
use App\Http\Controllers\Tenant\Platform\ShowPlatformTenantController;
use App\Http\Controllers\Tenant\Invitations\ShowTenantInvitationController;
use App\Http\Controllers\Tenant\Roles\ShowTenantRolePermissionsController;
use App\Http\Controllers\Tenant\Subscriptions\ShowTenantSubscriptionController;
use App\Http\Controllers\Tenant\Management\StoreTenantController;
use App\Http\Controllers\Tenant\Invitations\StoreTenantInvitationController;
use App\Http\Controllers\Tenant\Members\StoreTenantMemberController;
use App\Http\Controllers\Tenant\Units\StoreTenantUnitController;
use App\Http\Controllers\Tenant\Platform\UpdatePlatformTenantStatusController;
use App\Http\Controllers\Tenant\Management\UpdateTenantController;
use App\Http\Controllers\Tenant\Management\UpdateTenantLogoController;
use App\Http\Controllers\Tenant\Members\RemoveTenantMemberController;
use App\Http\Controllers\Tenant\Members\UpdateTenantMemberRoleController;
use App\Http\Controllers\Tenant\Members\UpdateTenantMemberStatusController;
use App\Http\Controllers\Tenant\Members\UpdateTenantMemberTeamController;
use App\Http\Controllers\Tenant\Roles\UpdateTenantRolePermissionsController;
use App\Http\Controllers\Tenant\Units\UpdateTenantUnitAssignmentsController;
use App\Http\Controllers\Tenant\Units\UpdateTenantUnitController;
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

        Route::get('/operators', \App\Http\Controllers\Operator\ListAvailableOperatorsController::class)->name('operators.available');

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

                Route::get('/operator-classifications', [\App\Http\Controllers\Client\OperatorClassificationController::class, 'globalIndex']);
                Route::post('/operator-classifications', [\App\Http\Controllers\Client\OperatorClassificationController::class, 'globalStore']);
                Route::patch('/operator-classifications/{classificationId}', [\App\Http\Controllers\Client\OperatorClassificationController::class, 'globalUpdate'])->whereNumber('classificationId');
                Route::patch('/operator-classifications/{classificationId}/renewal-rules/{ruleId}', [\App\Http\Controllers\Client\OperatorClassificationController::class, 'globalUpdateRule'])->whereNumber('classificationId')->whereUuid('ruleId');
                Route::post('/operator-classifications/{classificationId}/renewal-rules', [\App\Http\Controllers\Client\OperatorClassificationController::class, 'globalSaveRule'])->whereNumber('classificationId');
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
                Route::get('/commercial-participants', [\App\Http\Controllers\Commission\CommercialParticipantController::class, 'index']);
                Route::get('/commercial-participants/{participantId}/commissions', [\App\Http\Controllers\Commission\CommercialParticipantController::class, 'commissions'])->whereUuid('participantId');
                Route::get('/commercial-goals', [\App\Http\Controllers\Commission\CommercialGoalController::class, 'index']);
                Route::get('/team-commissions', [\App\Http\Controllers\Commission\TeamCommissionRuleController::class, 'index']);
                Route::get('/team-commissions/options', [\App\Http\Controllers\Commission\TeamCommissionRuleController::class, 'options']);
                Route::get('/commissions', [\App\Http\Controllers\Commission\CommissionTableController::class, 'index'])->name('commissions.index');
                Route::get('/commissions/order-types', [\App\Http\Controllers\Commission\CommissionTableController::class, 'orderTypes']);
                Route::get('/commissions/settings', [\App\Http\Controllers\Commission\CommissionTableController::class, 'settings'])->name('commissions.settings.show');
                Route::get('/calendar/events', [\\App\\Http\\Controllers\\Calendar\\CalendarEventController::class, 'index']);
                Route::get('/clients', [\App\Http\Controllers\Client\ClientController::class, 'index']);
                Route::get('/clients/options', [\App\Http\Controllers\Client\ClientController::class, 'options']);
                Route::get('/clients/{clientId}', [\App\Http\Controllers\Client\ClientController::class, 'show'])->whereUuid('clientId');
                Route::get('/operator-classifications', [\App\Http\Controllers\Client\OperatorClassificationController::class, 'index']);
                Route::get('/commercial-orders', [\App\Http\Controllers\Commercial\CommercialOrderController::class, 'index']);
                Route::get('/commercial-orders/options', [\App\Http\Controllers\Commercial\CommercialOrderController::class, 'options']);
                Route::get('/products', \App\Http\Controllers\Product\ListProductsController::class)->name('products.index');
                Route::get('/operators', ListTenantOperatorsController::class)->name('tenant-operators.index');
                Route::middleware('subscription.writable')->group(function (): void {
                    Route::post('/commercial-participants', [\App\Http\Controllers\Commission\CommercialParticipantController::class, 'store']);
                    Route::patch('/commercial-participants/{participantId}', [\App\Http\Controllers\Commission\CommercialParticipantController::class, 'update'])->whereUuid('participantId');
                    Route::post('/commercial-participants/{participantId}/commissions', [\App\Http\Controllers\Commission\CommercialParticipantController::class, 'saveCommission'])->whereUuid('participantId');
                    Route::patch('/commercial-participants/{participantId}/commissions/{overrideId}', [\App\Http\Controllers\Commission\CommercialParticipantController::class, 'saveCommission'])->whereUuid('participantId')->whereUuid('overrideId');
                    Route::delete('/commercial-participants/{participantId}/commissions/{overrideId}', [\App\Http\Controllers\Commission\CommercialParticipantController::class, 'deleteCommission'])->whereUuid('participantId')->whereUuid('overrideId');
                    Route::post('/commercial-goals', [\App\Http\Controllers\Commission\CommercialGoalController::class, 'store']);
                    Route::patch('/commercial-goals/{goalId}', [\App\Http\Controllers\Commission\CommercialGoalController::class, 'update'])->whereUuid('goalId');
                    Route::post('/team-commissions', [\App\Http\Controllers\Commission\TeamCommissionRuleController::class, 'store']);
                    Route::patch('/team-commissions/{ruleId}', [\App\Http\Controllers\Commission\TeamCommissionRuleController::class, 'update'])->whereUuid('ruleId');
                    Route::patch('/commissions/{commissionId}', [\App\Http\Controllers\Commission\CommissionTableController::class, 'update'])->whereUuid('commissionId');
                    Route::put('/commissions/settings', [\App\Http\Controllers\Commission\CommissionTableController::class, 'updateSettings']);
                    Route::post('/commissions', [\App\Http\Controllers\Commission\CommissionTableController::class, 'store'])->name('commissions.store');
                    Route::post('/calendar/events', [\\App\\Http\\Controllers\\Calendar\\CalendarEventController::class, 'store']);
                    Route::patch('/calendar/events/{eventId}', [\\App\\Http\\Controllers\\Calendar\\CalendarEventController::class, 'update'])->whereUuid('eventId');
                    Route::delete('/calendar/events/{eventId}', [\\App\\Http\\Controllers\\Calendar\\CalendarEventController::class, 'destroy'])->whereUuid('eventId');
                    Route::post('/clients', [\App\Http\Controllers\Client\ClientController::class, 'store']);
                    Route::patch('/clients/{clientId}', [\App\Http\Controllers\Client\ClientController::class, 'update'])->whereUuid('clientId');
                    Route::delete('/clients/{clientId}', [\App\Http\Controllers\Client\ClientController::class, 'destroy'])->whereUuid('clientId');
                    Route::post('/clients/{clientId}/contacts', [\App\Http\Controllers\Client\ClientController::class, 'saveContact'])->whereUuid('clientId');
                    Route::patch('/clients/{clientId}/contacts/{contactId}', [\App\Http\Controllers\Client\ClientController::class, 'saveContact'])->whereUuid('clientId')->whereUuid('contactId');
                    Route::post('/operator-classifications', [\App\Http\Controllers\Client\OperatorClassificationController::class, 'store']);
                    Route::patch('/operator-classifications/{classificationId}', [\App\Http\Controllers\Client\OperatorClassificationController::class, 'update'])->whereNumber('classificationId');
                    Route::patch('/operator-classifications/{classificationId}/renewal-rules/{ruleId}', [\App\Http\Controllers\Client\OperatorClassificationController::class, 'updateRule'])->whereNumber('classificationId')->whereUuid('ruleId');
                    Route::post('/operator-classifications/{classificationId}/renewal-rules', [\App\Http\Controllers\Client\OperatorClassificationController::class, 'saveRule'])->whereNumber('classificationId');
                    Route::post('/commercial-orders', [\App\Http\Controllers\Commercial\CommercialOrderController::class, 'store']);
                    Route::patch('/commercial-orders/{orderId}', [\App\Http\Controllers\Commercial\CommercialOrderController::class, 'update'])->whereUuid('orderId');
                    Route::post('/products', \App\Http\Controllers\Product\StoreProductController::class)->name('products.store');
                    Route::patch('/products/{productId}', \App\Http\Controllers\Product\UpdateProductController::class)->whereUuid('productId')->name('products.update');
                    Route::post('/products/{productId}/promotions', \App\Http\Controllers\Product\StorePromotionController::class)->whereUuid('productId')->name('promotions.store');
                    Route::patch('/products/{productId}/promotions/{promotionId}', \App\Http\Controllers\Product\UpdatePromotionController::class)->whereUuid('productId')->whereUuid('promotionId')->name('promotions.update');
                    Route::put('/operators', SyncTenantOperatorsController::class)->middleware('throttle:20,1')->name('tenant-operators.update');
                    Route::post('/units', StoreTenantUnitController::class)->middleware('throttle:20,1')->name('tenant-units.store');
                    Route::patch('/units/{unitId}', UpdateTenantUnitController::class)->whereUuid('unitId')->name('tenant-units.update');
                    Route::patch('/units/{unitId}/assignments', UpdateTenantUnitAssignmentsController::class)->whereUuid('unitId')->name('tenant-units.assignments.update');
                });

                Route::get('/roles', [\App\Http\Controllers\Tenant\Roles\TenantRoleController::class, 'index'])->name('tenant-roles.index');
                Route::post('/roles', [\App\Http\Controllers\Tenant\Roles\TenantRoleController::class, 'store'])->middleware('subscription.writable')->name('tenant-roles.store');
                Route::patch('/roles/{roleId}', [\App\Http\Controllers\Tenant\Roles\TenantRoleController::class, 'update'])->whereUuid('roleId')->middleware('subscription.writable')->name('tenant-roles.update');

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
