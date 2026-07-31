<?php

use App\Http\Controllers\Api\Admin\Analytics\UserAnalyticsController as AdminUserAnalyticsController;
use App\Http\Controllers\Api\Admin\AuditEventController as AdminAuditEventController;
use App\Http\Controllers\Api\Admin\PermissionController as AdminPermissionController;
use App\Http\Controllers\Api\Admin\Reports\UserReportController as AdminUserReportController;
use App\Http\Controllers\Api\Admin\RoleController as AdminRoleController;
use App\Http\Controllers\Api\Admin\StaffController as AdminStaffController;
use App\Http\Controllers\Api\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\Admin\WorksAccessController as AdminWorksAccessController;
use App\Http\Controllers\Api\Admin\WorksActivityController as AdminWorksActivityController;
use App\Http\Controllers\Api\Admin\WorksAuthoringController as AdminWorksAuthoringController;
use App\Http\Controllers\Api\Admin\WorksIndexController as AdminWorksIndexController;
use App\Http\Controllers\Api\Admin\WorksMediaController as AdminWorksMediaController;
use App\Http\Controllers\Api\Admin\WorksOverviewController as AdminWorksOverviewController;
use App\Http\Controllers\Api\Admin\WorksReportActionController as AdminWorksReportActionController;
use App\Http\Controllers\Api\Admin\WorksReportsController as AdminWorksReportsController;
use App\Http\Controllers\Api\Admin\WorksReviewActionController as AdminWorksReviewActionController;
use App\Http\Controllers\Api\Admin\WorksReviewQueueController as AdminWorksReviewQueueController;
use App\Http\Controllers\Api\Admin\WorksReviewSubmissionController as AdminWorksReviewSubmissionController;
use App\Http\Controllers\Api\Admin\WorksSettingsController as AdminWorksSettingsController;
use App\Http\Controllers\Api\Admin\WorksShowController as AdminWorksShowController;
use App\Http\Controllers\Api\Admin\WorksTaxonomyAssignmentController as AdminWorksTaxonomyAssignmentController;
use App\Http\Controllers\Api\Admin\WorksTaxonomyCatalogController as AdminWorksTaxonomyCatalogController;
use App\Http\Controllers\Api\Admin\WorksTaxonomyCategoryActionController as AdminWorksTaxonomyCategoryActionController;
use App\Http\Controllers\Api\Admin\WorksTaxonomyController as AdminWorksTaxonomyController;
use App\Http\Controllers\Api\Admin\WorksTaxonomyTagActionController as AdminWorksTaxonomyTagActionController;
use App\Http\Controllers\Api\Admin\WorksTaxonomyTagMergeController as AdminWorksTaxonomyTagMergeController;
use App\Http\Controllers\Api\Admin\WorksTrackedReportsController as AdminWorksTrackedReportsController;
use App\Http\Controllers\Api\Admin\WorksVisibilityActionController as AdminWorksVisibilityActionController;
use App\Http\Controllers\Api\Admin\WorksVisibilityController as AdminWorksVisibilityController;
use App\Http\Controllers\Api\Audit\PageViewAuditController;
use App\Http\Controllers\Api\AuthApiController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DashboardSearchController;
use App\Http\Controllers\Api\DesignerProfileController;
use App\Http\Controllers\Api\DesignerProfileMediaController;
use App\Http\Controllers\Api\DesignerProfileProfessionalController;
use App\Http\Controllers\Api\DesignerProfilePublicationController;
use App\Http\Controllers\Api\DesignerWorksArchiveController;
use App\Http\Controllers\Api\DesignerWorksAuthoringController;
use App\Http\Controllers\Api\DesignerWorksIndexController;
use App\Http\Controllers\Api\DesignerWorksMediaController;
use App\Http\Controllers\Api\DesignerWorksMetadataController;
use App\Http\Controllers\Api\DesignerWorksPresentationController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthApiController::class, 'register']);
    Route::post('/login', [AuthApiController::class, 'login']);
    Route::post('/forgot-password', [AuthApiController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthApiController::class, 'resetPassword']);
    Route::middleware('auth:sanctum')->post('/logout', [AuthApiController::class, 'logout']);
});

Route::middleware(['auth:sanctum', 'account.active'])->get('/user', [AuthApiController::class, 'user']);

Route::middleware(['auth:sanctum', 'account.active'])->prefix('designer')->group(function () {
    Route::post('/works', [DesignerWorksAuthoringController::class, 'store'])
        ->name('designer.works.store');
    Route::get('/works/{work}/authoring', [DesignerWorksAuthoringController::class, 'show'])
        ->whereNumber('work')
        ->name('designer.works.authoring.show');
    Route::patch('/works/{work}', [DesignerWorksAuthoringController::class, 'update'])
        ->whereNumber('work')
        ->name('designer.works.update');
    Route::get('/works', [DesignerWorksIndexController::class, 'index'])
        ->name('designer.works.index');
    Route::patch('/works/{work}/archive', [DesignerWorksArchiveController::class, 'archive'])
        ->whereNumber('work')
        ->name('designer.works.archive');
    Route::patch('/works/{work}/restore', [DesignerWorksArchiveController::class, 'restore'])
        ->whereNumber('work')
        ->name('designer.works.restore');
    Route::get('/works/{work}/metadata', [DesignerWorksMetadataController::class, 'show'])
        ->whereNumber('work')
        ->name('designer.works.metadata.show');
    Route::patch('/works/{work}/metadata', [DesignerWorksMetadataController::class, 'update'])
        ->whereNumber('work')
        ->name('designer.works.metadata.update');
    Route::get('/works/{work}/presentation', [DesignerWorksPresentationController::class, 'show'])
        ->whereNumber('work')
        ->name('designer.works.presentation.show');
    Route::patch('/works/{work}/presentation', [DesignerWorksPresentationController::class, 'update'])
        ->whereNumber('work')
        ->name('designer.works.presentation.update');
    Route::get('/works/{work}/media/{media}/content', [DesignerWorksMediaController::class, 'content'])
        ->whereNumber('work')->whereNumber('media')
        ->name('designer.works.media.content');
    Route::get('/works/{work}/media/{media}/poster', [DesignerWorksMediaController::class, 'poster'])
        ->whereNumber('work')->whereNumber('media')
        ->name('designer.works.media.poster');
    Route::get('/works/{work}/media', [DesignerWorksMediaController::class, 'index'])
        ->whereNumber('work')->name('designer.works.media.index');
    Route::post('/works/{work}/media', [DesignerWorksMediaController::class, 'store'])
        ->whereNumber('work')->name('designer.works.media.store');
    Route::patch('/works/{work}/media/order', [DesignerWorksMediaController::class, 'reorder'])
        ->whereNumber('work')->name('designer.works.media.reorder');
    Route::patch('/works/{work}/media/cover', [DesignerWorksMediaController::class, 'updateCover'])
        ->whereNumber('work')->name('designer.works.media.cover');
    Route::post('/works/{work}/media/{media}/retry-processing', [DesignerWorksMediaController::class, 'retryProcessing'])
        ->whereNumber('work')->whereNumber('media')->name('designer.works.media.retry');
    Route::patch('/works/{work}/media/{media}/video-cover/current', [DesignerWorksMediaController::class, 'useCurrentVideoCover'])
        ->whereNumber('work')->whereNumber('media')->name('designer.works.media.video-cover.current');
    Route::patch('/works/{work}/media/{media}/video-cover/frame', [DesignerWorksMediaController::class, 'selectVideoCoverFrame'])
        ->whereNumber('work')->whereNumber('media')->name('designer.works.media.video-cover.frame');
    Route::post('/works/{work}/media/{media}/video-cover/upload', [DesignerWorksMediaController::class, 'uploadVideoCover'])
        ->whereNumber('work')->whereNumber('media')->name('designer.works.media.video-cover.upload');
    Route::delete('/works/{work}/media/{media}', [DesignerWorksMediaController::class, 'destroy'])
        ->whereNumber('work')->whereNumber('media')->name('designer.works.media.destroy');
    Route::get('/profile/username-availability', [DesignerProfileController::class, 'usernameAvailability'])
        ->middleware('throttle:30,1');
    Route::get('/profile', [DesignerProfileController::class, 'show']);
    Route::put('/profile', [DesignerProfileController::class, 'upsert']);
    Route::get('/profile/professional', [DesignerProfileProfessionalController::class, 'show'])
        ->name('designer.profile.professional.show');
    Route::put('/profile/professional', [DesignerProfileProfessionalController::class, 'update'])
        ->name('designer.profile.professional.update');
    Route::get('/profile/publication', [DesignerProfilePublicationController::class, 'show'])
        ->name('designer.profile.publication.show');
    Route::get('/profile/publication/preview', [DesignerProfilePublicationController::class, 'preview'])
        ->name('designer.profile.publication.preview');
    Route::patch('/profile/publication/publish', [DesignerProfilePublicationController::class, 'publish'])
        ->name('designer.profile.publication.publish');
    Route::patch('/profile/publication/hide', [DesignerProfilePublicationController::class, 'hide'])
        ->name('designer.profile.publication.hide');
    Route::get('/profile/avatar/content', [DesignerProfileMediaController::class, 'avatarContent']);
    Route::post('/profile/avatar', [DesignerProfileMediaController::class, 'storeAvatar']);
    Route::delete('/profile/avatar', [DesignerProfileMediaController::class, 'destroyAvatar']);
    Route::get('/profile/cover/content', [DesignerProfileMediaController::class, 'coverContent']);
    Route::post('/profile/cover', [DesignerProfileMediaController::class, 'storeCover']);
    Route::patch('/profile/cover/focal-point', [DesignerProfileMediaController::class, 'updateCoverFocalPoint']);
    Route::delete('/profile/cover', [DesignerProfileMediaController::class, 'destroyCover']);
});

Route::middleware(['auth:sanctum', 'account.active'])->post('/audit/page-view', PageViewAuditController::class);

Route::middleware(['auth:sanctum', 'account.active'])->prefix('dashboard')->group(function () {
    Route::get('/stats', [DashboardController::class, 'stats']);
    Route::get('/activity', [DashboardController::class, 'activity']);
    Route::get('/chart', [DashboardController::class, 'chart']);
    Route::get('/overview', [DashboardController::class, 'overview']);
    Route::get('/search', DashboardSearchController::class);
});

Route::middleware(['auth:sanctum', 'account.active'])->prefix('admin')->group(function () {
    Route::get('/audit-events', [AdminAuditEventController::class, 'index']);
    Route::get('/analytics/users', AdminUserAnalyticsController::class);
    Route::get('/reports/users', AdminUserReportController::class);
    Route::get('/works/access', [AdminWorksAccessController::class, 'index']);
    Route::get('/works/activity', [AdminWorksActivityController::class, 'index']);
    Route::get('/works/overview', [AdminWorksOverviewController::class, 'index']);
    Route::get('/works/review', [AdminWorksReviewQueueController::class, 'index']);
    Route::get('/works/{work}/review/readiness', [AdminWorksReviewSubmissionController::class, 'readiness'])->whereNumber('work');
    Route::patch('/works/{work}/review/submit', [AdminWorksReviewSubmissionController::class, 'submit'])->whereNumber('work');
    Route::patch('/works/taxonomy/assign/category', [AdminWorksTaxonomyAssignmentController::class, 'bulkUpdateCategory']);
    Route::patch('/works/taxonomy/assign/tags', [AdminWorksTaxonomyAssignmentController::class, 'bulkUpdateTags']);
    Route::patch('/works/{work}/review/start', [AdminWorksReviewActionController::class, 'start'])->whereNumber('work');
    Route::patch('/works/{work}/review/assign-reviewer', [AdminWorksReviewActionController::class, 'assignReviewer'])->whereNumber('work');
    Route::patch('/works/{work}/review/approve', [AdminWorksReviewActionController::class, 'approve'])->whereNumber('work');
    Route::patch('/works/{work}/review/request-changes', [AdminWorksReviewActionController::class, 'requestChanges'])->whereNumber('work');
    Route::patch('/works/{work}/review/reject', [AdminWorksReviewActionController::class, 'reject'])->whereNumber('work');
    Route::patch('/works/{work}/review/publish', [AdminWorksReviewActionController::class, 'publishAfterApproval'])->whereNumber('work');
    Route::patch('/works/{work}/review/reopen', [AdminWorksReviewActionController::class, 'reopen'])->whereNumber('work');
    Route::get('/works/visibility', [AdminWorksVisibilityController::class, 'index']);
    Route::get('/works/reports', [AdminWorksReportsController::class, 'index']);
    Route::get('/works/reports/{report}', [AdminWorksTrackedReportsController::class, 'show'])->whereNumber('report');
    Route::patch('/works/reports/{report}/review', [AdminWorksReportActionController::class, 'review'])->whereNumber('report');
    Route::patch('/works/reports/{report}/dismiss', [AdminWorksReportActionController::class, 'dismiss'])->whereNumber('report');
    Route::patch('/works/reports/{report}/archive', [AdminWorksReportActionController::class, 'archive'])->whereNumber('report');
    Route::get('/works/{work}/reports', [AdminWorksTrackedReportsController::class, 'index'])->whereNumber('work');
    Route::get('/works/taxonomy/categories', [AdminWorksTaxonomyCatalogController::class, 'categories']);
    Route::post('/works/taxonomy/categories', [AdminWorksTaxonomyCategoryActionController::class, 'store']);
    Route::patch('/works/taxonomy/categories/{category}/disable', [AdminWorksTaxonomyCategoryActionController::class, 'disable'])->whereNumber('category');
    Route::patch('/works/taxonomy/categories/{category}', [AdminWorksTaxonomyCategoryActionController::class, 'update'])->whereNumber('category');
    Route::get('/works/taxonomy/tags', [AdminWorksTaxonomyCatalogController::class, 'tags']);
    Route::post('/works/taxonomy/tags', [AdminWorksTaxonomyTagActionController::class, 'store']);
    Route::patch('/works/taxonomy/tags/merge', [AdminWorksTaxonomyTagMergeController::class, 'merge']);
    Route::patch('/works/taxonomy/tags/{tag}/disable', [AdminWorksTaxonomyTagActionController::class, 'disable'])->whereNumber('tag');
    Route::patch('/works/taxonomy/tags/{tag}', [AdminWorksTaxonomyTagActionController::class, 'update'])->whereNumber('tag');
    Route::get('/works/taxonomy', [AdminWorksTaxonomyController::class, 'index']);
    Route::get('/works/settings', [AdminWorksSettingsController::class, 'index']);
    Route::patch('/works/settings', [AdminWorksSettingsController::class, 'update']);
    Route::patch('/works/{work}/visibility/publish', [AdminWorksVisibilityActionController::class, 'publish'])->whereNumber('work');
    Route::patch('/works/{work}/visibility/unpublish', [AdminWorksVisibilityActionController::class, 'unpublish'])->whereNumber('work');
    Route::patch('/works/{work}/visibility/hide', [AdminWorksVisibilityActionController::class, 'hide'])->whereNumber('work');
    Route::patch('/works/{work}/visibility/restore', [AdminWorksVisibilityActionController::class, 'restore'])->whereNumber('work');
    Route::patch('/works/{work}/visibility/feature', [AdminWorksVisibilityActionController::class, 'feature'])->whereNumber('work');
    Route::patch('/works/{work}/visibility/unfeature', [AdminWorksVisibilityActionController::class, 'unfeature'])->whereNumber('work');
    Route::patch('/works/{work}/visibility/pin', [AdminWorksVisibilityActionController::class, 'pin'])->whereNumber('work');
    Route::patch('/works/{work}/visibility/unpin', [AdminWorksVisibilityActionController::class, 'unpin'])->whereNumber('work');
    Route::patch('/works/{work}/taxonomy/category', [AdminWorksTaxonomyAssignmentController::class, 'updateCategory'])->whereNumber('work');
    Route::patch('/works/{work}/taxonomy/tags', [AdminWorksTaxonomyAssignmentController::class, 'updateTags'])->whereNumber('work');
    Route::post('/works', [AdminWorksAuthoringController::class, 'store']);
    Route::patch('/works/{work}', [AdminWorksAuthoringController::class, 'update'])->whereNumber('work');
    Route::get('/works/authoring/options', [AdminWorksAuthoringController::class, 'options']);
    Route::get('/works', [AdminWorksIndexController::class, 'index']);
    Route::get('/works/{work}/authoring', [AdminWorksAuthoringController::class, 'show'])
        ->whereNumber('work');
    Route::get('/works/{work}/media', [AdminWorksMediaController::class, 'index'])->whereNumber('work');
    Route::post('/works/{work}/media', [AdminWorksMediaController::class, 'store'])->whereNumber('work');
    Route::patch('/works/{work}/media/order', [AdminWorksMediaController::class, 'reorder'])
        ->whereNumber('work');
    Route::patch('/works/{work}/media/cover', [AdminWorksMediaController::class, 'updateCover'])
        ->whereNumber('work');
    Route::get('/works/{work}/media/{media}/content', [AdminWorksMediaController::class, 'content'])
        ->whereNumber('work')
        ->whereNumber('media');
    Route::get('/works/{work}/media/{media}/poster', [AdminWorksMediaController::class, 'poster'])
        ->whereNumber('work')
        ->whereNumber('media');
    Route::post('/works/{work}/media/{media}/retry-processing', [AdminWorksMediaController::class, 'retryProcessing'])
        ->whereNumber('work')
        ->whereNumber('media');
    Route::delete('/works/{work}/media/{media}', [AdminWorksMediaController::class, 'destroy'])
        ->whereNumber('work')
        ->whereNumber('media');
    Route::get('/works/{work}', [AdminWorksShowController::class, 'show'])->whereNumber('work');

    Route::get('/users', [AdminUserController::class, 'index']);
    Route::put('/users/{user}/roles', [AdminUserController::class, 'syncRoles']);
    Route::get('/staff', [AdminStaffController::class, 'index']);
    Route::patch('/staff/{staff}', [AdminStaffController::class, 'update'])->whereNumber('staff');
    Route::put('/staff/{staff}/roles', [AdminStaffController::class, 'syncRoles'])->whereNumber('staff');
    Route::get('/staff/{staff}/permissions', [AdminStaffController::class, 'permissions'])->whereNumber('staff');
    Route::put('/staff/{staff}/permissions', [AdminStaffController::class, 'syncPermissions'])->whereNumber('staff');
    Route::get('/staff/{staff}/activity', [AdminStaffController::class, 'activity'])->whereNumber('staff');
    Route::patch('/staff/{staff}/disable', [AdminStaffController::class, 'disable'])->whereNumber('staff');
    Route::patch('/staff/{staff}/restore', [AdminStaffController::class, 'restore'])->whereNumber('staff');
    Route::delete('/staff/{staff}', [AdminStaffController::class, 'destroy'])->whereNumber('staff');
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
