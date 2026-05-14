<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AdminAuthenticatorController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\AdminOtpController;
use App\Http\Controllers\Admin\AdminPasswordResetController;
use App\Http\Controllers\Admin\ArticleCategoryController;
use App\Http\Controllers\Admin\ArticleController;
use App\Http\Controllers\Admin\ArticleLanguageController;
use App\Http\Controllers\Admin\ArticleSetController;
use App\Http\Controllers\Admin\campaignController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DomainCategoryController;
use App\Http\Controllers\Admin\DomainController;
use App\Http\Controllers\Admin\DomainSetController;
use App\Http\Controllers\Admin\HiddenLinkCampaignController;
use App\Http\Controllers\Admin\InvoiceController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\ScheduleCampaignController;
use App\Http\Controllers\Admin\ScheduleSidebarCampaignController;
use App\Http\Controllers\Admin\SidebarCampaignController;
use App\Http\Controllers\Admin\StickyPostCampaignController;
use App\Http\Controllers\Admin\WpScheduledCampaignController;
use App\Models\Admin\ArticleSet;

Route::prefix('admin')->middleware('admin.guest')->group(function () {
    Route::get('/login', [AdminAuthenticatorController::class, 'show'])->name('admin.login');
    Route::get('/otp', [AdminOtpController::class, 'show'])->name('admin.otp'); // fixed from /opt to /otp

    // post Route
    Route::post('/login/post', [AdminAuthenticatorController::class, 'login'])->name('admin.loggedin');
    Route::post('/otp/post', [AdminOtpController::class, 'verifyOtp'])->name('admin.verify.otp');

    // forget password
    Route::get('/forgotpassword', [AdminPasswordResetController::class, 'show'])->name('admin.forgot');
    Route::post('/forgot-password/post', [AdminPasswordResetController::class, 'sendResetLink'])->name('admin.forgot.post');

    //reset password Links
    Route::get('/reset-password/{token}', [AdminPasswordResetController::class, 'showResetForm'])->name('admin.reset.form');
    Route::post('/reset-password', [AdminPasswordResetController::class, 'resetPassword'])->name('admin.reset');
});

/*
 * PUBLIC report/export routes (token-protected, no login required).
 * withoutMiddleware() ensures shared links work on live (even if route/config cache is stale).
 */
$noCampaignAuth = \App\Http\Middleware\Admin\CanCreateCampaigns::class;

/* campaign report route*/ // route('admin.campaign.report)
Route::get(
    '/campaign/report/{campaign_no}/{token}',
    [campaignController::class, 'report']
)->name('admin.campaign.report')->withoutMiddleware($noCampaignAuth);

// Export campaign report (PUBLIC, token-protected)
Route::get(
    '/campaign/report/{campaign_no}/{token}/export',
    [campaignController::class, 'exportReport']
)->name('admin.campaign.report.export')->withoutMiddleware($noCampaignAuth);

/* sidebar campaign report route*/ // route('admin.campaign.report)
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

// export csv
// Route::get(
//     '/campaign/report/{campaign_no}/{token}/export-csv',
//     [campaignController::class, 'exportReportCsv']
// )->name('admin.campaign.report.export.csv');


Route::prefix('admin')->name('admin.')->middleware('admin.auth')->group(function () {

    // logout route 
    Route::post('/admin/logout', [AdminAuthenticatorController::class, 'logout'])->name('logout');
    //logout route ends here

    // Dashboard Route Here
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/campaigns', [DashboardController::class, 'getCampaignsByType'])->name('dashboard.campaigns');
    // Dashboard Route Ends Here

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

    /** ends here **/

    /** Bulk Domain Delete Function **/

    Route::post('/domain/delete', [DomainController::class, 'delete'])->name('domain.delete');

    /** ends here **/

    Route::resource('/domain', DomainController::class);

    /** Domain Routes Ends Here **/

    /** Domain Set Routes Start Here **/

    Route::post('/domains/set/delete', [DomainSetController::class, 'delete'])->name('set.delete');

    Route::resource('/domains/set', DomainSetController::class);

    /* domains section ends here */

    /*Article category Routes Here*/

    /* bulk delete route for article category */

    Route::post('/article/category/delete', [ArticleCategoryController::class, 'delete'])->name('articles.category.delete');

    Route::resource('/article/category', ArticleCategoryController::class)->names('articles.category');

    /*Article language Routes Here*/

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

    // Route::get('/post/campaign', function () {
    //     return view('admin.campaigns.pbn-post.create-campaign');
    // })->name('post.campaign');


    /* campaign report route ends here*/

    Route::get('/campaign/retry/{id}', [campaignController::class, 'retry'])->name('campaign.retry');

    Route::get('/campaign/editpost/{id}',[campaignController::class,'editcampaignpost'])->name('campaign.edit.post'); // admin.campaign.blogpost

    Route::post('/campaign/updatecampaignpost/{id}',[campaignController::class,'updateCampaignPost'])->name('campaign.update.post'); // admin.campaign.blogpost

    Route::get('/campaign/deleteCampaignPost/{id}',[campaignController::class,'deleteCampaignPost'])->name('campaign.delete.post'); // admin.campaign.blogpost

    Route::post('/campaign/bulk/update/{id}', [campaignController::class, 'bulkUpdateCampaignPosts'])->name('campaign.bulk.update');

    Route::post('/campaign/multi-keywords/{id}', [campaignController::class, 'multiLevelUpdateCampaignKeywords'])->name('campaign.multi.keywords.update');

    Route::post('/campaign/update-post-keywords/{id}', [campaignController::class, 'updateCampaignPostKeywords'])->name('campaign.update.post.keywords');

    Route::post('/campaign/{id}/purge-local', [campaignController::class, 'purgeLocalOnly'])->name('campaign.purge.local');

    Route::post('/campaign/bulk-purge-local', [campaignController::class, 'bulkPurgeLocal'])->name('campaign.bulk.purge.local');

    Route::post('/campaign/bulk-retry-failed', [campaignController::class, 'bulkRetryFailed'])->name('campaign.bulk.retry.failed');

    Route::resource('/campaign', campaignController::class);
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
    // Route::view('/article','admin.article.articles')->name('articles
    // Route::view('/article/category','admin.article.category.category')->name('articles.category');

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