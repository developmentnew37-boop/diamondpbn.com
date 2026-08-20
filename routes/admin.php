<?php

use App\Http\Controllers\Admin\AdminAuthenticatorController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\AdminOtpController;
use App\Http\Controllers\Admin\AdminPasswordResetController;
use App\Http\Controllers\Admin\ArticleCategoryController;
use App\Http\Controllers\Admin\ArticleController;
use App\Http\Controllers\Admin\ArticleLanguageController;
use App\Http\Controllers\Admin\ArticleSetController;
use App\Http\Controllers\Admin\CampaignBulkDomainReplacementController;
use App\Http\Controllers\Admin\CampaignController;
use App\Http\Controllers\Admin\CampaignDomainReplacementController;
use App\Http\Controllers\Admin\CampaignPostConversionController;
use App\Http\Controllers\Admin\CampaignReportLookupController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DomainCategoryController;
use App\Http\Controllers\Admin\DomainController;
use App\Http\Controllers\Admin\DomainSetController;
use App\Http\Controllers\Admin\DomainStatusCheckerController;
use App\Http\Controllers\Admin\DomainRecheckDisconnectedController;
use App\Http\Controllers\Admin\HiddenLinkCampaignController;
use App\Http\Controllers\Admin\InvoiceController;
use App\Http\Controllers\Admin\LiveTaskBulkDomainReplacementController;
use App\Http\Controllers\Admin\LiveTaskDomainReplacementController;
use App\Http\Controllers\Admin\LocalClientBillingReportController;
use App\Http\Controllers\Admin\LocalClientController;
use App\Http\Controllers\Admin\LocalClientPaymentController;
use App\Http\Controllers\Admin\PendingDomainController;
use App\Http\Controllers\Admin\PluginDeploymentController;
use App\Http\Controllers\Admin\PluginPackageController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\ScheduleCampaignController;
use App\Http\Controllers\Admin\ScheduleSidebarCampaignController;
use App\Http\Controllers\Admin\SidebarCampaignController;
use App\Http\Controllers\Admin\SidebarCampaignConversionController;
use App\Http\Controllers\Admin\StickyPostCampaignController;
use App\Http\Controllers\Admin\TransferDomainController;
use App\Http\Controllers\Admin\WebhookSecretController;
use App\Http\Controllers\Admin\WpScheduledCampaignController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->middleware('admin.guest')->group(function () {
    Route::get('/login', [AdminAuthenticatorController::class, 'show'])->name('admin.login');
    Route::get('/otp', [AdminOtpController::class, 'show'])->name('admin.otp'); // fixed from /opt to /otp

    // post Route
    Route::post('/login/post', [AdminAuthenticatorController::class, 'login'])->name('admin.loggedin');
    Route::post('/otp/post', [AdminOtpController::class, 'verifyOtp'])->name('admin.verify.otp');

    // forget password
    Route::get('/forgotpassword', [AdminPasswordResetController::class, 'show'])->name('admin.forgot');
    Route::post('/forgot-password/post', [AdminPasswordResetController::class, 'sendResetLink'])->name('admin.forgot.post');

    // reset password Links
    Route::get('/reset-password/{token}', [AdminPasswordResetController::class, 'showResetForm'])->name('admin.reset.form');
    Route::post('/reset-password', [AdminPasswordResetController::class, 'resetPassword'])->name('admin.reset');
});

/*
 * PUBLIC report/export routes (token-protected, no login required).
 * withoutMiddleware() ensures shared links work on live (even if route/config cache is stale).
 */
$noCampaignAuth = \App\Http\Middleware\Admin\CanCreateCampaigns::class;

/* campaign report route */ // route('admin.campaign.report)
Route::get(
    '/campaign/report/{campaign_no}/{token}',
    [CampaignController::class, 'report']
)->name('admin.campaign.report')->withoutMiddleware($noCampaignAuth);

// Export campaign report (PUBLIC, token-protected)
Route::get(
    '/campaign/report/{campaign_no}/{token}/export',
    [CampaignController::class, 'exportReport']
)->name('admin.campaign.report.export')->withoutMiddleware($noCampaignAuth);

/* sidebar campaign report route */ // route('admin.campaign.report)
Route::get(
    '/sidebar/campaign/report/{campaign_no}/{token}',
    [SidebarCampaignController::class, 'report']
)->name('admin.sidebar.campaign.report')->withoutMiddleware($noCampaignAuth);

// Export sidebar campaign report (PUBLIC, token-protected)
Route::get(
    '/sidebar/campaign/report/{campaign_no}/{token}/export',
    [SidebarCampaignController::class, 'exportReport']
)->name('admin.sidebar.campaign.report.export')->withoutMiddleware($noCampaignAuth);

// * Hidden Link Campaign Report * //

Route::get(
    '/hidden/link/campaign/report/{campaign_no}/{token}',
    [HiddenLinkCampaignController::class, 'report']
)->name('admin.hidden.link.campaign.report')->withoutMiddleware($noCampaignAuth);

Route::get(
    '/hidden/link/campaign/report/{campaign_no}/{token}/export',
    [HiddenLinkCampaignController::class, 'exportReport']
)->name('admin.hidden.link.campaign.report.export')->withoutMiddleware($noCampaignAuth);

// * schdedule Campaign Report * //

Route::get(
    '/schedule/campaign/report/{campaign_no}/{token}',
    [ScheduleCampaignController::class, 'report']
)->name('admin.schedule.campaign.report')->withoutMiddleware($noCampaignAuth);

Route::get(
    '/schedule/campaign/report/{campaign_no}/{token}/export',
    [ScheduleCampaignController::class, 'exportReport']
)->name('admin.schedule.campaign.report.export')->withoutMiddleware($noCampaignAuth);

// * schdedule Sidebar Campaign Report * //

Route::get(
    '/schedule/sidebar/campaign/report/{campaign_no}/{token}',
    [ScheduleSidebarCampaignController::class, 'report']
)->name('admin.schedule.sidebar.campaign.report')->withoutMiddleware($noCampaignAuth);

Route::get(
    '/schedule/sidebar/campaign/report/{campaign_no}/{token}/export',
    [ScheduleSidebarCampaignController::class, 'exportReport']
)->name('admin.schedule.sidebar.campaign.report.export')->withoutMiddleware($noCampaignAuth);

// * WP Scheduled Campaign Report (token-protected) * //
Route::get(
    '/campaign/post/wp-schedule/report/{campaign_no}/{token}',
    [WpScheduledCampaignController::class, 'report']
)->name('admin.wp.schedule.campaign.report')->withoutMiddleware($noCampaignAuth);
Route::get(
    '/campaign/post/wp-schedule/report/{campaign_no}/{token}/export',
    [WpScheduledCampaignController::class, 'exportReport']
)->name('admin.wp.schedule.campaign.report.export')->withoutMiddleware($noCampaignAuth);

Route::get(
    '/client/billing/{id}/{token}',
    [LocalClientBillingReportController::class, 'show']
)->name('admin.local-client.billing.report')->withoutMiddleware($noCampaignAuth);

Route::get(
    '/client/billing/{id}/{token}/export',
    [LocalClientBillingReportController::class, 'export']
)->name('admin.local-client.billing.report.export')->withoutMiddleware($noCampaignAuth);

// export csv
// Route::get(
//     '/campaign/report/{campaign_no}/{token}/export-csv',
//     [CampaignController::class, 'exportReportCsv']
// )->name('admin.campaign.report.export.csv');

Route::prefix('admin')->name('admin.')->middleware('admin.auth')->group(function () {

    // logout route
    Route::post('/admin/logout', [AdminAuthenticatorController::class, 'logout'])->name('logout');
    // logout route ends here

    // Dashboard Route Here
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/campaigns', [DashboardController::class, 'getCampaignsByType'])->name('dashboard.campaigns');
    // Dashboard Route Ends Here

    Route::middleware(\App\Http\Middleware\Admin\CanCreateCampaigns::class)->group(function () {
        Route::get('/reports/find-campaign', [CampaignReportLookupController::class, 'index'])
            ->name('reports.find-campaign');
        Route::post('/reports/find-campaign', [CampaignReportLookupController::class, 'find'])
            ->name('reports.find-campaign.lookup');
        Route::post('/reports/find-campaign/by-keyword-url', [CampaignReportLookupController::class, 'findByKeywordUrl'])
            ->name('reports.find-campaign.by-keyword-url');
    });

    // Profile Routes
    Route::get('/profile', [ProfileController::class, 'index'])->name('profile');
    Route::get('/profile/{slug}', [ProfileController::class, 'show'])->name('profile.show');
    Route::put('/profile/update', [ProfileController::class, 'update'])->name('profile.update');
    // Profile Routes End

    // User Management (Super Admin only can see all users)
    Route::resource('/user', AdminController::class);

    // users routes ends here

    // Domain Category Routes Here

    /** Bulk Domain Delete Function **/
    Route::post('/domain/category/delete', [DomainCategoryController::class, 'delete'])->name('domain.category.delete');

    /** Bulk Domain Delete Function ends here **/
    Route::resource('/domain/category', DomainCategoryController::class)->names('domain.category');

    /** domain category registration ends here **/

    /** domain route starts here **/

    // redirect to list route | select category for redirect | Ui

    Route::get('/domain/select/category', [DomainController::class, 'selectDomainCategory'])->name('select.category');

    /** -- -- -- -- -- -- **/

    /** redirect to domain list page base on domain category **/
    Route::post('/domain/redirect/list', [DomainController::class, 'redirect__func'])->name('redirect.to.list');
    Route::get('/domain/extract', [DomainController::class, 'extractByCategory'])->name('domain.extract');
    Route::get('/domain/move-category', [DomainController::class, 'moveCategoryForm'])->name('domain.move-category');
    Route::post('/domain/move-category', [DomainController::class, 'processMoveCategory'])->name('domain.move-category.process');

    /** ends here **/

    /** Bulk Domain Delete Function **/
    Route::post('/domain/delete', [DomainController::class, 'delete'])->name('domain.delete');

    Route::get('/domain/status-checker', [DomainStatusCheckerController::class, 'index'])->name('domain.status-checker');
    Route::post('/domain/status-checker/start', [DomainStatusCheckerController::class, 'start'])
        ->middleware('throttle:30,1')
        ->name('domain.status-checker.start');
    Route::get('/domain/status-checker/{uuid}/progress', [DomainStatusCheckerController::class, 'progress'])
        ->middleware('throttle:180,1')
        ->name('domain.status-checker.progress');

    Route::get('/domain/recheck-disconnected', [DomainRecheckDisconnectedController::class, 'index'])
        ->name('domain.recheck-disconnected');
    Route::post('/domain/recheck-disconnected/start', [DomainRecheckDisconnectedController::class, 'start'])
        ->middleware('throttle:30,1')
        ->name('domain.recheck-disconnected.start');
    Route::get('/domain/recheck-disconnected/{uuid}/progress', [DomainRecheckDisconnectedController::class, 'progress'])
        ->middleware('throttle:180,1')
        ->name('domain.recheck-disconnected.progress');
    Route::get('/domain/recheck-disconnected/{uuid}/export', [DomainRecheckDisconnectedController::class, 'export'])
        ->name('domain.recheck-disconnected.export');
    Route::post('/domain/recheck-disconnected/{uuid}/cancel', [DomainRecheckDisconnectedController::class, 'cancel'])
        ->middleware('throttle:30,1')
        ->name('domain.recheck-disconnected.cancel');

    /** ends here **/
    Route::resource('/domain', DomainController::class);

    /** Domain Routes Ends Here **/

    /** Plugin Manager (separate from Domains — /admin/plugin-manager) **/
    Route::prefix('plugin-manager')
        ->name('plugin-manager.')
        ->middleware(\App\Http\Middleware\Admin\CanCreateCampaigns::class)
        ->group(function () {
            Route::get('/', [PluginPackageController::class, 'index'])->name('index');
            Route::post('/packages', [PluginPackageController::class, 'store'])->name('packages.store');
            Route::delete('/packages/{uuid}', [PluginPackageController::class, 'destroy'])->name('packages.destroy');
            Route::get('/packages/{uuid}/download', [PluginPackageController::class, 'adminDownload'])->name('packages.download');

            Route::get('/deploy', [PluginDeploymentController::class, 'create'])->name('deploy.create');
            Route::post('/deploy', [PluginDeploymentController::class, 'start'])
                ->middleware('throttle:10,1')
                ->name('deploy.start');

            Route::get('/deployments', [PluginDeploymentController::class, 'index'])->name('deployments.index');
            Route::get('/deployments/export-history', [PluginDeploymentController::class, 'exportHistory'])
                ->name('deployments.export-history');
            Route::delete('/deployments/bulk', [PluginDeploymentController::class, 'bulkDestroy'])->name('deployments.bulk-destroy');
            Route::delete('/deployments/clear', [PluginDeploymentController::class, 'clearHistory'])->name('deployments.clear');
            Route::get('/deployments/{uuid}', [PluginDeploymentController::class, 'show'])->name('deployments.show');
            Route::get('/deployments/{uuid}/progress', [PluginDeploymentController::class, 'progress'])
                ->middleware('throttle:120,1')
                ->name('deployments.progress');
            Route::post('/deployments/{uuid}/retry-failed', [PluginDeploymentController::class, 'retryFailed'])
                ->name('deployments.retry-failed');
            Route::post('/deployments/{uuid}/cancel', [PluginDeploymentController::class, 'cancel'])
                ->name('deployments.cancel');
            Route::delete('/deployments/{uuid}', [PluginDeploymentController::class, 'destroy'])
                ->name('deployments.destroy');
            Route::get('/deployments/{uuid}/export-failures', [PluginDeploymentController::class, 'exportFailures'])
                ->name('deployments.export-failures');
        });

    /** Webhook Secrets Routes Start Here **/
    Route::resource('/webhook-secrets', WebhookSecretController::class)
        ->names('webhook-secrets')
        ->except(['show']);
    Route::post('/webhook-secrets/rotation-settings', [WebhookSecretController::class, 'updateRotationSettings'])
        ->name('webhook-secrets.rotation-settings');
    Route::post('/webhook-secrets/{webhookSecret}/regenerate', [WebhookSecretController::class, 'regenerate'])->name('webhook-secrets.regenerate');

    /** Pending Domains Routes Start Here **/
    Route::post('/pending-domains/sync-existing', [PendingDomainController::class, 'syncExistingInventory'])->name('pending-domains.sync-existing');
    Route::post('/pending-domains/{pendingDomain}/sync-inventory', [PendingDomainController::class, 'syncSingleInventory'])->name('pending-domains.sync-inventory');
    Route::post('/pending-domains/bulk-reject', [PendingDomainController::class, 'bulkReject'])->name('pending-domains.bulk.reject');
    Route::post('/pending-domains/{pendingDomain}/reject', [PendingDomainController::class, 'reject'])->name('pending-domains.reject');
    Route::resource('/pending-domains', PendingDomainController::class)
        ->names('pending-domains')
        ->only(['index', 'show', 'destroy']);

    /** Transfer Domains Routes Start Here **/
    Route::get('/transfer-domains/step1', [TransferDomainController::class, 'step1'])->name('transfer-domains.step1');
    Route::match(['get', 'post'], '/transfer-domains/step2', [TransferDomainController::class, 'step2'])->name('transfer-domains.step2');
    Route::post('/transfer-domains/create-category', [TransferDomainController::class, 'createCategory'])->name('transfer-domains.create-category');
    Route::post('/transfer-domains/process', [TransferDomainController::class, 'process'])->name('transfer-domains.process');

    /** Webhook & Pending Domains Routes End Here **/

    /** Domain Set Routes Start Here **/
    Route::post('/domains/set/delete', [DomainSetController::class, 'delete'])->name('set.delete');

    Route::resource('/domains/set', DomainSetController::class);

    /* domains section ends here */

    /* Article category Routes Here */

    /* bulk delete route for article category */

    Route::post('/article/category/delete', [ArticleCategoryController::class, 'delete'])->name('articles.category.delete');

    Route::resource('/article/category', ArticleCategoryController::class)->names('articles.category');

    /* Article language Routes Here */

    Route::resource('/article/language', ArticleLanguageController::class)->names('articles.language');

    /* ** articles routes here ** */
    /* Article Routes Here */

    Route::get('/article/opt', [ArticleController::class, 'opt'])->name('articles.opt');

    Route::get('/article/upload/docx', [ArticleController::class, 'uploadDocx'])->name('articles.upload.docx');

    Route::post('/article/import', [ArticleController::class, 'import'])->name('articles.import');

    Route::post('/article/delete', [ArticleController::class, 'delete'])->name('articles.delete');

    /* Soft-deleted articles: list + permanent remove (must be before resource /article/{article}) */
    Route::get('/article/trashed', [ArticleController::class, 'trashedIndex'])->name('article.trashed.index');
    Route::delete('/article/trashed/{id}', [ArticleController::class, 'forceDestroy'])->name('article.trashed.destroy');
    Route::post('/article/trashed/force-delete', [ArticleController::class, 'forceDestroyBulk'])->name('article.trashed.force-delete');
    Route::post('/article/trashed/queue-purge-all-used', [ArticleController::class, 'queuePurgeAllTrashedUsed'])->name('article.trashed.queue-purge-all-used');
    Route::post('/article/trashed/queue-purge-by-quantity', [ArticleController::class, 'queuePurgeTrashedUsedByQuantity'])->name('article.trashed.queue-purge-by-quantity');

    /* Restore soft-deleted used articles back to the normal library */
    Route::get('/article/restore-used', [ArticleController::class, 'restoreUsedIndex'])->name('article.restore-used.index');
    Route::post('/article/restore-used/{id}/restore', [ArticleController::class, 'restoreTrashedUsed'])->name('article.restore-used.restore');
    Route::post('/article/restore-used/restore-bulk', [ArticleController::class, 'restoreTrashedUsedBulk'])->name('article.restore-used.restore-bulk');
    Route::post('/article/restore-used/queue-restore-all', [ArticleController::class, 'queueRestoreAllTrashedUsed'])->name('article.restore-used.queue-restore-all');
    Route::post('/article/restore-used/queue-restore-by-quantity', [ArticleController::class, 'queueRestoreTrashedUsedByQuantity'])->name('article.restore-used.queue-restore-by-quantity');

    Route::resource('/article', ArticleController::class);

    /* article set thing */ /* adding s in it we will optimized it further */

    /* article set option */

    Route::get('/article/set/options', [ArticleSetController::class, 'option'])->name('articles.set.create.options');

    Route::post('/article/set/add/', [ArticleSetController::class, 'createArticles'])->name('articles.set.add.article'); // option -> add article using createArticles

    Route::post('/article/set/import/', [ArticleSetController::class, 'import'])->name('articles.set.import.articles'); // option -> add article using createArticles

    Route::delete('/article/set/destroy/{id}', [ArticleSetController::class, 'articleDestroy'])->name('article.set.destroy.article'); // destroying the articles

    Route::post('/article/set/bulk/delete', [ArticleSetController::class, 'deleteSetArticles'])->name('article.set.bulk.delete'); // destroying articles in bulk

    Route::resource('/articles/set', ArticleSetController::class)->names('articles.set');

    /* campaigns route starts here */

    Route::middleware(\App\Http\Middleware\Admin\CanCreateCampaigns::class)->group(function () {
        Route::get('/campaign/posts/{post}/replace-domain', [CampaignDomainReplacementController::class, 'create'])
            ->name('campaign.domain-replacement.create');
        Route::post('/campaign/posts/{post}/replace-domain', [CampaignDomainReplacementController::class, 'store'])
            ->name('campaign.domain-replacement.store');

        Route::get('/campaign/{campaign}/bulk-replace-domains', [CampaignBulkDomainReplacementController::class, 'create'])
            ->name('campaign.bulk-domain-replacement.create');
        Route::post('/campaign/{campaign}/bulk-replace-domains', [CampaignBulkDomainReplacementController::class, 'store'])
            ->name('campaign.bulk-domain-replacement.store');

        Route::get('/campaign/sidebar/tasks/{task}/replace-domain', [LiveTaskDomainReplacementController::class, 'createSidebar'])
            ->name('sidebar.campaign.domain-replacement.create');
        Route::post('/campaign/sidebar/tasks/{task}/replace-domain', [LiveTaskDomainReplacementController::class, 'storeSidebar'])
            ->name('sidebar.campaign.domain-replacement.store');

        Route::get('/hidden/link/campaign/tasks/{task}/replace-domain', [LiveTaskDomainReplacementController::class, 'createHiddenLinks'])
            ->name('hidden.link.campaign.domain-replacement.create');
        Route::post('/hidden/link/campaign/tasks/{task}/replace-domain', [LiveTaskDomainReplacementController::class, 'storeHiddenLinks'])
            ->name('hidden.link.campaign.domain-replacement.store');

        Route::get('/sidebar/campaign/{campaign}/bulk-replace-domains', [LiveTaskBulkDomainReplacementController::class, 'createSidebar'])
            ->name('sidebar.campaign.bulk-domain-replacement.create');
        Route::post('/sidebar/campaign/{campaign}/bulk-replace-domains', [LiveTaskBulkDomainReplacementController::class, 'storeSidebar'])
            ->name('sidebar.campaign.bulk-domain-replacement.store');

        Route::get('/hidden/link/campaign/{campaign}/bulk-replace-domains', [LiveTaskBulkDomainReplacementController::class, 'createHiddenLinks'])
            ->name('hidden.link.campaign.bulk-domain-replacement.create');
        Route::post('/hidden/link/campaign/{campaign}/bulk-replace-domains', [LiveTaskBulkDomainReplacementController::class, 'storeHiddenLinks'])
            ->name('hidden.link.campaign.bulk-domain-replacement.store');

        Route::get('/campaign/post/schedule/posts/{post}/replace-domain', [LiveTaskDomainReplacementController::class, 'createSchedulePost'])
            ->name('schedule.campaign.domain-replacement.create');
        Route::post('/campaign/post/schedule/posts/{post}/replace-domain', [LiveTaskDomainReplacementController::class, 'storeSchedulePost'])
            ->name('schedule.campaign.domain-replacement.store');

        Route::get('/campaign/sidebar/schedule/tasks/{task}/replace-domain', [LiveTaskDomainReplacementController::class, 'createScheduleSidebar'])
            ->name('schedule.sidebar.campaign.domain-replacement.create');
        Route::post('/campaign/sidebar/schedule/tasks/{task}/replace-domain', [LiveTaskDomainReplacementController::class, 'storeScheduleSidebar'])
            ->name('schedule.sidebar.campaign.domain-replacement.store');

        Route::get('/campaign/post/schedule/{schedule}/bulk-replace-domains', [LiveTaskBulkDomainReplacementController::class, 'createSchedulePost'])
            ->name('schedule.campaign.bulk-domain-replacement.create');
        Route::post('/campaign/post/schedule/{schedule}/bulk-replace-domains', [LiveTaskBulkDomainReplacementController::class, 'storeSchedulePost'])
            ->name('schedule.campaign.bulk-domain-replacement.store');

        Route::get('/campaign/sidebar/schedule/{schedule}/bulk-replace-domains', [LiveTaskBulkDomainReplacementController::class, 'createScheduleSidebar'])
            ->name('schedule.sidebar.campaign.bulk-domain-replacement.create');
        Route::post('/campaign/sidebar/schedule/{schedule}/bulk-replace-domains', [LiveTaskBulkDomainReplacementController::class, 'storeScheduleSidebar'])
            ->name('schedule.sidebar.campaign.bulk-domain-replacement.store');
    });

    // Route::get('/post/campaign', function () {
    //     return view('admin.campaigns.pbn-post.create-campaign');
    // })->name('post.campaign');

    /* campaign report route ends here */

    Route::post('/campaign/retry/{id}', [CampaignController::class, 'retry'])->name('campaign.retry');

    Route::get('/campaign/editpost/{id}', [CampaignController::class, 'editcampaignpost'])->name('campaign.edit.post'); // admin.campaign.blogpost

    Route::post('/campaign/updatecampaignpost/{id}', [CampaignController::class, 'updateCampaignPost'])->name('campaign.update.post'); // admin.campaign.blogpost

    Route::get('/campaign/deleteCampaignPost/{id}', [CampaignController::class, 'deleteCampaignPost'])->name('campaign.delete.post'); // admin.campaign.blogpost

    Route::post('/campaign/bulk/update/{id}', [CampaignController::class, 'bulkUpdateCampaignPosts'])->name('campaign.bulk.update');

    Route::post('/campaign/multi-keywords/{id}', [CampaignController::class, 'multiLevelUpdateCampaignKeywords'])->name('campaign.multi.keywords.update');

    Route::post('/campaign/update-post-keywords/{id}', [CampaignController::class, 'updateCampaignPostKeywords'])->name('campaign.update.post.keywords');

    Route::post('/campaign/{id}/purge-local', [CampaignController::class, 'purgeLocalOnly'])->name('campaign.purge.local');

    Route::post('/campaign/bulk-purge-local', [CampaignController::class, 'bulkPurgeLocal'])->name('campaign.bulk.purge.local');

    Route::post('/campaign/bulk-retry-failed', [CampaignController::class, 'bulkRetryFailed'])->name('campaign.bulk.retry.failed');

    Route::resource('/campaign', CampaignController::class);
    /* sidebar campaign */
    Route::get('/sidebar/campaign/retry-task/{id}', [SidebarCampaignController::class, 'retryTask'])->name('sidebar.campaign.retry.task');
    Route::get('/sidebar/campaign/edit-task/{id}', [SidebarCampaignController::class, 'editSidebarTask'])->name('sidebar.campaign.edit.task');
    Route::post('/sidebar/campaign/update-task/{id}', [SidebarCampaignController::class, 'updateSidebarTask'])->name('sidebar.campaign.update.task');
    Route::get('/sidebar/campaign/delete-task/{id}', [SidebarCampaignController::class, 'deleteSidebarTask'])->name('sidebar.campaign.delete.task');
    Route::post('/sidebar/campaign/{id}/bulk-delete-tasks', [SidebarCampaignController::class, 'bulkDeleteTasks'])->name('sidebar.campaign.bulk.delete.tasks');

    Route::post('/sidebar/campaign/{id}/purge-local', [SidebarCampaignController::class, 'purgeLocalOnly'])->name('sidebar.campaign.purge.local');

    Route::post('/sidebar/campaign/bulk-purge-local', [SidebarCampaignController::class, 'bulkPurgeLocal'])->name('sidebar.campaign.bulk.purge.local');
    Route::post('/sidebar/campaign/bulk-retry-failed', [SidebarCampaignController::class, 'bulkRetryFailed'])->name('sidebar.campaign.bulk.retry.failed');
    Route::get('/sidebar/campaign/extract-domains', [SidebarCampaignController::class, 'extractDomains'])->name('sidebar.campaign.extract.domains');

    Route::resource('/sidebar/campaign', SidebarCampaignController::class)->names('sidebar.campaign');

    /* Hidden Link campaign */
    Route::get('/hidden/link/campaign/edit-task/{id}', [HiddenLinkCampaignController::class, 'editTask'])->name('hidden.link.campaign.edit.task');
    Route::post('/hidden/link/campaign/update-task/{id}', [HiddenLinkCampaignController::class, 'updateTask'])->name('hidden.link.campaign.update.task');
    Route::get('/hidden/link/campaign/retry-task/{id}', [HiddenLinkCampaignController::class, 'retryTask'])->name('hidden.link.campaign.retry.task');
    Route::get('/hidden/link/campaign/delete-task/{id}', [HiddenLinkCampaignController::class, 'deleteTask'])->name('hidden.link.campaign.delete.task');
    Route::post('/hidden/link/campaign/{id}/bulk-delete-tasks', [HiddenLinkCampaignController::class, 'bulkDeleteTasks'])->name('hidden.link.campaign.bulk.delete.tasks');
    Route::post('/hidden/link/campaign/bulk-delete', [HiddenLinkCampaignController::class, 'bulkDeleteCampaigns'])->name('hidden.link.campaign.bulk.delete');

    Route::post('/hidden/link/campaign/bulk-purge-local', [HiddenLinkCampaignController::class, 'bulkPurgeLocalCampaigns'])->name('hidden.link.campaign.bulk.purge.local');

    Route::post('/hidden/link/campaign/bulk-retry-failed', [HiddenLinkCampaignController::class, 'bulkRetryFailed'])->name('hidden.link.campaign.bulk.retry.failed');

    Route::post('/hidden/link/campaign/{id}/purge-local', [HiddenLinkCampaignController::class, 'purgeLocalOnly'])->name('hidden.link.campaign.purge.local');

    Route::resource('/hidden/link/campaign', HiddenLinkCampaignController::class)->names('hidden.link.campaign');

    /* Live → dripfeed conversion */
    Route::middleware(\App\Http\Middleware\Admin\CanCreateCampaigns::class)
        ->prefix('convert/post')
        ->name('convert.post.')
        ->group(function () {
            Route::get('/', [CampaignPostConversionController::class, 'wizardStep1'])->name('step1');
            Route::get('/step/1', [CampaignPostConversionController::class, 'wizardStep1'])->name('step1.alias');
            Route::get('/step/2/{campaign}', [CampaignPostConversionController::class, 'wizardStep2'])->name('step2')->whereNumber('campaign');
            Route::get('/step/3/{campaign}', [CampaignPostConversionController::class, 'wizardStep3'])->name('step3')->whereNumber('campaign');
            Route::match(['get', 'post'], '/step/4/{campaign}', [CampaignPostConversionController::class, 'wizardStep4'])->name('step4')->whereNumber('campaign');
            Route::post('/store', [CampaignPostConversionController::class, 'store'])->name('store');
            Route::get('/campaigns/search', [CampaignPostConversionController::class, 'searchCampaigns'])->name('search');
            Route::post('/campaigns/lookup-report-url', [CampaignPostConversionController::class, 'lookupByReportUrl'])->name('lookup-report-url');
            Route::get('/campaigns/{campaign}/eligibility', [CampaignPostConversionController::class, 'eligibility'])->name('eligibility')->whereNumber('campaign');
            Route::post('/campaigns/{campaign}/retry-failed', [CampaignPostConversionController::class, 'retryFailed'])->name('retry-failed')->whereNumber('campaign');
            Route::post('/preflight', [CampaignPostConversionController::class, 'preflight'])->name('preflight');
            Route::get('/campaigns/{campaign}/preflight', [CampaignPostConversionController::class, 'preflightForCampaign'])->name('preflight.campaign')->whereNumber('campaign');
            Route::get('/converted', [CampaignPostConversionController::class, 'convertedIndex'])->name('converted.index');
            Route::get('/schedule/{schedule}/status', [CampaignPostConversionController::class, 'conversionStatus'])->name('status')->whereNumber('schedule');
            Route::post('/schedule-post/{post}/retry', [CampaignPostConversionController::class, 'retryConversionPost'])->name('retry-post')->whereNumber('post');
            Route::post('/schedule/{schedule}/bulk-retry-failed', [CampaignPostConversionController::class, 'bulkRetryConversionFailed'])->name('bulk-retry')->whereNumber('schedule');
        });

    /* Live sidebar → scheduled sidebar conversion */
    Route::middleware(\App\Http\Middleware\Admin\CanCreateCampaigns::class)
        ->prefix('convert/sidebar')
        ->name('convert.sidebar.')
        ->group(function () {
            Route::get('/', [SidebarCampaignConversionController::class, 'wizardStep1'])->name('step1');
            Route::get('/step/1', [SidebarCampaignConversionController::class, 'wizardStep1'])->name('step1.alias');
            Route::get('/step/2/{campaign}', [SidebarCampaignConversionController::class, 'wizardStep2'])->name('step2')->whereNumber('campaign');
            Route::get('/step/3/{campaign}', [SidebarCampaignConversionController::class, 'wizardStep3'])->name('step3')->whereNumber('campaign');
            Route::match(['get', 'post'], '/step/4/{campaign}', [SidebarCampaignConversionController::class, 'wizardStep4'])->name('step4')->whereNumber('campaign');
            Route::post('/store', [SidebarCampaignConversionController::class, 'store'])->name('store');
            Route::get('/campaigns/search', [SidebarCampaignConversionController::class, 'searchCampaigns'])->name('search');
            Route::post('/campaigns/lookup-report-url', [SidebarCampaignConversionController::class, 'lookupByReportUrl'])->name('lookup-report-url');
            Route::get('/campaigns/{campaign}/eligibility', [SidebarCampaignConversionController::class, 'eligibility'])->name('eligibility')->whereNumber('campaign');
            Route::post('/campaigns/{campaign}/retry-failed', [SidebarCampaignConversionController::class, 'retryFailed'])->name('retry-failed')->whereNumber('campaign');
            Route::get('/campaigns/{campaign}/preflight', [SidebarCampaignConversionController::class, 'preflightForCampaign'])->name('preflight.campaign')->whereNumber('campaign');
            Route::get('/converted', [SidebarCampaignConversionController::class, 'convertedIndex'])->name('converted.index');
            Route::get('/schedule/{schedule}/status', [SidebarCampaignConversionController::class, 'conversionStatus'])->name('status')->whereNumber('schedule');
            Route::post('/schedule-task/{task}/retry', [SidebarCampaignConversionController::class, 'retryConversionTask'])->name('retry-task')->whereNumber('task');
            Route::post('/schedule/{schedule}/bulk-retry-failed', [SidebarCampaignConversionController::class, 'bulkRetryConversionFailed'])->name('bulk-retry')->whereNumber('schedule');
        });

    /* Schedule sticky post (same as schedule post + is_sticky on API; stored on schedule_campaigns.is_sticky_campaign) */
    Route::get('/campaign/post/schedule-sticky', [ScheduleCampaignController::class, 'indexSticky'])->name('schedule.sticky.campaign.index');
    Route::get('/campaign/post/schedule-sticky/create', [ScheduleCampaignController::class, 'createSticky'])->name('schedule.sticky.campaign.create');

    /* Schedule campaign */
    Route::post('/campaign/post/schedule/bulk/update/{id}', [ScheduleCampaignController::class, 'bulkUpdate'])->name('schedule.campaign.bulk.update');
    Route::post('/campaign/post/schedule/multi-keywords/{id}', [ScheduleCampaignController::class, 'multiLevelUpdateScheduleKeywords'])->name('schedule.campaign.multi.keywords.update');
    Route::get('/campaign/post/schedule/edit-post/{postId}', [ScheduleCampaignController::class, 'editPost'])->name('schedule.campaign.edit.post');
    Route::post('/campaign/post/schedule/update-post/{postId}', [ScheduleCampaignController::class, 'updatePost'])->name('schedule.campaign.update.post');
    Route::post('/campaign/post/schedule/update-post-keywords/{postId}', [ScheduleCampaignController::class, 'updatePostKeywords'])->name('schedule.campaign.update.post.keywords');
    Route::post('/campaign/post/schedule/retry-post/{postId}', [ScheduleCampaignController::class, 'retryPost'])->name('schedule.campaign.retry.post');
    Route::post('/campaign/post/schedule/delete-post/{postId}', [ScheduleCampaignController::class, 'deletePost'])->name('schedule.campaign.delete.post');

    Route::post('/campaign/post/schedule/{id}/purge-local', [ScheduleCampaignController::class, 'purgeLocalOnly'])->name('schedule.campaign.purge.local');

    Route::post('/campaign/post/schedule/bulk-purge-local', [ScheduleCampaignController::class, 'bulkPurgeLocal'])->name('schedule.campaign.bulk.purge.local');

    Route::post('/campaign/post/schedule/bulk-retry-failed', [ScheduleCampaignController::class, 'bulkRetryFailed'])->name('schedule.campaign.bulk.retry.failed');

    Route::resource('/campaign/post/schedule', ScheduleCampaignController::class)->names('schedule.campaign');

    /* WordPress-native scheduled campaign (posts scheduled on remote WP) */
    Route::post('/campaign/post/wp-schedule/run/{id}', [WpScheduledCampaignController::class, 'run'])->name('wp.schedule.campaign.run');
    Route::post('/campaign/post/wp-schedule/sync/{id}', [WpScheduledCampaignController::class, 'syncCampaign'])->name('wp.schedule.campaign.sync');
    Route::post('/campaign/post/wp-schedule/retry-post/{postId}', [WpScheduledCampaignController::class, 'retryPost'])->name('wp.schedule.campaign.retry.post');
    Route::post('/campaign/post/wp-schedule/sync-post/{postId}', [WpScheduledCampaignController::class, 'syncPost'])->name('wp.schedule.campaign.sync.post');
    Route::get('/campaign/post/wp-schedule/editpost/{postId}', [WpScheduledCampaignController::class, 'editPost'])->name('wp.schedule.campaign.edit.post');
    Route::post('/campaign/post/wp-schedule/updatepost/{postId}', [WpScheduledCampaignController::class, 'updatePost'])->name('wp.schedule.campaign.update.post');
    Route::post('/campaign/post/wp-schedule/deletepost/{postId}', [WpScheduledCampaignController::class, 'deletePost'])->name('wp.schedule.campaign.delete.post');
    Route::post('/campaign/post/wp-schedule/bulk/update/{id}', [WpScheduledCampaignController::class, 'bulkUpdate'])->name('wp.schedule.campaign.bulk.update');
    Route::post('/campaign/post/wp-schedule/multi-keywords/{id}', [WpScheduledCampaignController::class, 'multiLevelUpdateWpScheduleKeywords'])->name('wp.schedule.campaign.multi.keywords.update');

    Route::post('/campaign/post/wp-schedule/{id}/purge-local', [WpScheduledCampaignController::class, 'purgeLocalOnly'])->name('wp.schedule.campaign.purge.local');

    Route::post('/campaign/post/wp-schedule/bulk-purge-local', [WpScheduledCampaignController::class, 'bulkPurgeLocal'])->name('wp.schedule.campaign.bulk.purge.local');

    Route::resource('/campaign/post/wp-schedule', WpScheduledCampaignController::class)->names('wp.schedule.campaign');

    /* Schedule Sidebar campaign */
    Route::post('/campaign/sidebar/schedule/bulk/update/{id}', [ScheduleSidebarCampaignController::class, 'bulkUpdate'])->name('schedule.sidebar.campaign.bulk.update');
    Route::get('/campaign/sidebar/schedule/edit-task/{id}', [ScheduleSidebarCampaignController::class, 'editTask'])->name('schedule.sidebar.campaign.edit.task');
    Route::post('/campaign/sidebar/schedule/update-task/{id}', [ScheduleSidebarCampaignController::class, 'updateTask'])->name('schedule.sidebar.campaign.update.task');
    Route::post('/campaign/sidebar/schedule/retry-task/{id}', [ScheduleSidebarCampaignController::class, 'retryTask'])->name('schedule.sidebar.campaign.retry.task');
    Route::post('/campaign/sidebar/schedule/delete-task/{id}', [ScheduleSidebarCampaignController::class, 'deleteTask'])->name('schedule.sidebar.campaign.delete.task');

    Route::post('/campaign/sidebar/schedule/{id}/purge-local', [ScheduleSidebarCampaignController::class, 'purgeLocalOnly'])->name('schedule.sidebar.campaign.purge.local');

    Route::post('/campaign/sidebar/schedule/bulk-purge-local', [ScheduleSidebarCampaignController::class, 'bulkPurgeLocal'])->name('schedule.sidebar.campaign.bulk.purge.local');

    Route::post('/campaign/sidebar/schedule/bulk-retry-failed', [ScheduleSidebarCampaignController::class, 'bulkRetryFailed'])->name('schedule.sidebar.campaign.bulk.retry.failed');

    Route::resource('/campaign/sidebar/schedule', ScheduleSidebarCampaignController::class)->names('schedule.sidebar.campaign');

    /* sticky Sidebar campaign */
    Route::get('/sticky/campaign/create', [StickyPostCampaignController::class, 'create'])->name('sticky.campaign.create');

    Route::get('/sticky/campaign/', [StickyPostCampaignController::class, 'index'])->name('sticky.campaign.index');

    Route::middleware('can.create.campaigns')->group(function () {
        Route::get('/local-clients/{localClient}/estimate', [LocalClientController::class, 'estimate'])
            ->name('local-clients.estimate');
        Route::patch('/local-clients/payment/{billableType}/{id}', [LocalClientPaymentController::class, 'update'])
            ->name('local-clients.payment.update');
        Route::get('/local-clients/campaign-invoice/{billableType}/{id}', [LocalClientPaymentController::class, 'invoice'])
            ->name('local-clients.campaign-invoice');
    });

    Route::middleware('permission:local_clients.manage')->group(function () {
        Route::post('/local-clients/{localClient}/toggle-active', [LocalClientController::class, 'toggleActive'])
            ->name('local-clients.toggle-active');
        Route::post('/local-clients/{localClient}/mark-period-paid', [LocalClientController::class, 'markPeriodPaid'])
            ->name('local-clients.mark-period-paid');
        Route::delete('/local-clients/{localClient}/billing-periods/{billingPeriod}', [LocalClientController::class, 'destroyBillingPeriod'])
            ->name('local-clients.billing-periods.destroy');
        Route::delete('/local-clients/{localClient}/billing-periods', [LocalClientController::class, 'destroyBillingPeriodByPaidAt'])
            ->name('local-clients.billing-periods.destroy-by-paid-at');
        Route::post('/local-clients/{localClient}/regenerate-token', [LocalClientController::class, 'regenerateToken'])
            ->name('local-clients.regenerate-token');
        Route::resource('/local-clients', LocalClientController::class)->names('local-clients');
    });

    /* Invoice Generator Routes */
    Route::get('/invoice/generator', [InvoiceController::class, 'create'])->name('invoice.generator');
    Route::post('/invoice/generate-pdf', [InvoiceController::class, 'generatePdf'])->name('invoice.generate');

    // Domain routes here...
});

// LOGIN

// Route::post('/login', [AdminLoginController::class, 'login'])
//     ->name('admin.login.submit');

// // FORGOT PASSWORD (send reset link)
// Route::get('/forgot-password', [AdminForgotPasswordController::class, 'showLinkRequestForm'])
//     ->name('admin.password.request');

// Route::post('/forgot-password', [AdminForgotPasswordController::class, 'sendResetLinkEmail'])
//     ->name('admin.password.email');

// // RESET PASSWORD (token link)
// Route::get('/reset-password/{token}', [AdminResetPasswordController::class, 'showResetForm'])
//     ->name('admin.password.reset');

// Route::post('/reset-password', [AdminResetPasswordController::class, 'reset'])
//     ->name('admin.password.update');

// // PROTECTED ADMIN AREA
// Route::middleware('auth:admin')->group(function () {
//     Route::get('/', [AdminDashboardController::class, 'index'])
//         ->name('admin.dashboard');

//     Route::post('/logout', [AdminLoginController::class, 'logout'])
//         ->name('admin.logout');
// });
