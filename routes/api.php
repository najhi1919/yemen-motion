<?php

use App\Http\Controllers\Api\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\Admin\Analytics\UserAnalyticsController as AdminUserAnalyticsController;
use App\Http\Controllers\Api\Admin\AuditEventController as AdminAuditEventController;
use App\Http\Controllers\Api\Admin\RoleController as AdminRoleController;
use App\Http\Controllers\Api\Admin\PermissionController as AdminPermissionController;
use App\Http\Controllers\Api\Admin\Reports\UserReportController as AdminUserReportController;
use App\Http\Controllers\Api\Admin\StaffController as AdminStaffController;
use App\Http\Controllers\Api\Admin\WorksAccessController as AdminWorksAccessController;
use App\Http\Controllers\Api\Admin\WorksActivityController as AdminWorksActivityController;
use App\Http\Controllers\Api\Admin\WorksIndexController as AdminWorksIndexController;
use App\Http\Controllers\Api\Admin\WorksOverviewController as AdminWorksOverviewController;
use App\Http\Controllers\Api\Admin\WorksReportsController as AdminWorksReportsController;
use App\Http\Controllers\Api\Admin\WorksReviewActionController as AdminWorksReviewActionController;
use App\Http\Controllers\Api\Admin\WorksReviewQueueController as AdminWorksReviewQueueController;
use App\Http\Controllers\Api\Admin\WorksSettingsController as AdminWorksSettingsController;
use App\Http\Controllers\Api\Admin\WorksShowController as AdminWorksShowController;
use App\Http\Controllers\Api\Admin\WorksTaxonomyController as AdminWorksTaxonomyController;
use App\Http\Controllers\Api\Admin\WorksVisibilityActionController as AdminWorksVisibilityActionController;
use App\Http\Controllers\Api\Admin\WorksVisibilityController as AdminWorksVisibilityController;
use App\Http\Controllers\Api\Audit\PageViewAuditController;
use App\Http\Controllers\Api\AuthApiController;
use App\Http\Controllers\Api\DashboardSearchController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthApiController::class, 'register']);
    Route::post('/login', [AuthApiController::class, 'login']);
    Route::post('/forgot-password', [AuthApiController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthApiController::class, 'resetPassword']);
    Route::middleware('auth:sanctum')->post('/logout', [AuthApiController::class, 'logout']);
});

Route::middleware(['auth:sanctum'])->get('/user', [AuthApiController::class, 'user']);

Route::middleware(['auth:sanctum'])->post('/audit/page-view', PageViewAuditController::class);

Route::middleware(['auth:sanctum'])->prefix('dashboard')->group(function () {
    Route::get('/stats', [\App\Http\Controllers\Api\DashboardController::class, 'stats']);
    Route::get('/activity', [\App\Http\Controllers\Api\DashboardController::class, 'activity']);
    Route::get('/chart', [\App\Http\Controllers\Api\DashboardController::class, 'chart']);
    Route::get('/overview', [\App\Http\Controllers\Api\DashboardController::class, 'overview']);
    Route::get('/search', DashboardSearchController::class);
});

Route::middleware(['auth:sanctum'])->prefix('admin')->group(function () {
    Route::get('/audit-events', [AdminAuditEventController::class, 'index']);
    Route::get('/analytics/users', AdminUserAnalyticsController::class);
    Route::get('/reports/users', AdminUserReportController::class);
    Route::get('/works/access', [AdminWorksAccessController::class, 'index']);
    Route::get('/works/activity', [AdminWorksActivityController::class, 'index']);
    Route::get('/works/overview', [AdminWorksOverviewController::class, 'index']);
    Route::get('/works/review', [AdminWorksReviewQueueController::class, 'index']);
    Route::patch('/works/{work}/review/start', [AdminWorksReviewActionController::class, 'start'])->whereNumber('work');
    Route::patch('/works/{work}/review/assign-reviewer', [AdminWorksReviewActionController::class, 'assignReviewer'])->whereNumber('work');
    Route::patch('/works/{work}/review/approve', [AdminWorksReviewActionController::class, 'approve'])->whereNumber('work');
    Route::patch('/works/{work}/review/request-changes', [AdminWorksReviewActionController::class, 'requestChanges'])->whereNumber('work');
    Route::patch('/works/{work}/review/reject', [AdminWorksReviewActionController::class, 'reject'])->whereNumber('work');
    Route::patch('/works/{work}/review/publish', [AdminWorksReviewActionController::class, 'publishAfterApproval'])->whereNumber('work');
    Route::patch('/works/{work}/review/reopen', [AdminWorksReviewActionController::class, 'reopen'])->whereNumber('work');
    Route::get('/works/visibility', [AdminWorksVisibilityController::class, 'index']);
    Route::get('/works/reports', [AdminWorksReportsController::class, 'index']);
    Route::get('/works/taxonomy', [AdminWorksTaxonomyController::class, 'index']);
    Route::get('/works/settings', [AdminWorksSettingsController::class, 'index']);
    Route::patch('/works/{work}/visibility/publish', [AdminWorksVisibilityActionController::class, 'publish'])->whereNumber('work');
    Route::patch('/works/{work}/visibility/unpublish', [AdminWorksVisibilityActionController::class, 'unpublish'])->whereNumber('work');
    Route::patch('/works/{work}/visibility/hide', [AdminWorksVisibilityActionController::class, 'hide'])->whereNumber('work');
    Route::patch('/works/{work}/visibility/restore', [AdminWorksVisibilityActionController::class, 'restore'])->whereNumber('work');
    Route::patch('/works/{work}/visibility/feature', [AdminWorksVisibilityActionController::class, 'feature'])->whereNumber('work');
    Route::patch('/works/{work}/visibility/unfeature', [AdminWorksVisibilityActionController::class, 'unfeature'])->whereNumber('work');
    Route::patch('/works/{work}/visibility/pin', [AdminWorksVisibilityActionController::class, 'pin'])->whereNumber('work');
    Route::patch('/works/{work}/visibility/unpin', [AdminWorksVisibilityActionController::class, 'unpin'])->whereNumber('work');
    Route::get('/works', [AdminWorksIndexController::class, 'index']);
    Route::get('/works/{work}', [AdminWorksShowController::class, 'show'])->whereNumber('work');

    Route::get('/users', [AdminUserController::class, 'index']);
    Route::put('/users/{user}/roles', [AdminUserController::class, 'syncRoles']);
    Route::post('/staff', [AdminStaffController::class, 'store']);

    Route::get('/permissions', [AdminPermissionController::class, 'index']);
    Route::post('/permissions', [AdminPermissionController::class, 'store']);
    Route::patch('/permissions/{permission}', [AdminPermissionController::class, 'update']);
    Route::delete('/permissions/{permission}', [AdminPermissionController::class, 'destroy']);

    Route::get('/roles', [AdminRoleController::class, 'index']);
    Route::post('/roles', [AdminRoleController::class, 'store']);
    Route::get('/roles/{role}', [AdminRoleController::class, 'show']);
    Route::patch('/roles/{role}', [AdminRoleController::class, 'update']);
    Route::delete('/roles/{role}', [AdminRoleController::class, 'destroy']);
    Route::put('/roles/{role}/permissions', [AdminRoleController::class, 'syncPermissions']);
});
