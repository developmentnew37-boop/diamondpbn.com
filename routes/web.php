<?php

use App\Http\Controllers\Admin\PluginPackageController;
use Illuminate\Support\Facades\Route;

require __DIR__.'/admin.php';

Route::get('/plugin-deployments/download/{uuid}', [PluginPackageController::class, 'download'])
    ->middleware('throttle:300,1')
    ->name('plugin-deployments.download');

Route::get('/', function () {
    return view('home');
})->name('home');

// Route::get('/', function () {
//     return view('welcome');
// })->name('index');

// Route::get('/post', function () {
//     return view('pbn-campaigns.post');
// })->name('post');

// // article routes set routes

// Route::get('/article', function () {
//     return view('article.articles');
// })->name('article');

// Route::get('/category', function () {
//     return view('article.category');
// })->name('category');

// Route::get('/article/option', function () {
//     return view('article.options');
// })->name('opt'); // showing article options

// Route::get('/article/create', function () {
//     return view('article.add-article');
// })->name('manual_create'); // showing article options wiht simple manual fields
// // this is to read article
// Route::get('/article/view/{id}', function ($id) {
//     return view('article.view-article', ['id' => $id]); // viewing articles
// })->name('view-article');
// Route::get('/article/edit/{id}', function ($id) {
//     return view('article.edit-article', ['id' => $id]); // editing articles
// })->name('edit-article');

// // delete route remianing

// // article set routes

// Route::get('/article/set', function () {
//     return view('article.article-set'); // showing article set
// })->name('article-set');

// Route::get('article/set/options/{id}', function ($id) {
//     return view('article.article-set.article-set-option', ['id' => $id]); // showing options with entered or specific article set
// })->name('article-set-option');

// Route::get('/article/set/articles/{id}', function ($id) {
//     return view('article.article-set.articleset-articles', ['id' => $id]); // showing set articles
// })->name('set-articles');

// Route::get('/article/set/articles/edit/{id}', function ($id) {
//     return view('article.article-set.edit-articleset-articles', ['id' => $id]); // showing set articles
// })->name('edit-articles');

// // domains now here

// Route::get('/domain', function () {
//     return view('domains.domains');
// })->name('domain');

// Route::get('/domain/add', function () {
//     return view('domains.add-domains');
// })->name('domain.add');

// Route::get('/domain/category', function () {
//     return view('domains.domain-category');
// })->name('domain.category');

// Route::get('/domain/edit/{id}', function ($id) {
//     return view('domains.edit-domains', ['id' => $id]);
// })->name('domain.edit');

// Route::get('/domain/set', function () {
//     return view('domains.domain-set');
// })->name('domain.set');

// Route::get('/domain/set/create/{id}', function ($id) {
//     return view('domains.create-domain-set', ['id' => $id]);
// })->name('domain.create.set');

// Route::get('/domain/set/view/{id}', function ($id) {
//     return view('domains.view-domain-set', ['id' => $id]);
// })->name('domain.view.set');

// Route::get('/domain/set/create/{id}', function ($id) {
//     return view('domains.create-domain-set', ['id' => $id]);
// })->name('domain.create.set');

// Route::get('/domain/set/edit/{id}', function ($id) {
//     return view('domains.edit-domain-set', ['id' => $id]);
// })->name('domain.set.edit');

// Route::get('/campaign/post/create', function () {
//     return view('campaigns.pbn-post.create-campaign');
// })->name('create.pbn.post');

// Route::get('/campaign/sidebar/create', function () {
//     return view('campaigns.pbn-sidebar.create-sidebar-campaign');
// })->name('create.pbn.sidebar');

// Route::get('/campaign/hidden/create', function () {
//     return view('campaigns.pbn-hidden-links.create-hidden-links-campaign');
// })->name('create.pbn.hidden');

// // schedule post should show here

// Route::get('/campaign/post/schedule',function(){
//     return view('campaigns.pbn-post.create-schedule-campaign');
// })->name('create.pbn.post.schedule');

// route::get('/campaign/post/sticky',function(){
//     return view('sticky.make-sticky-campaign');
// })->name('make.sticky');

// Route::view('/test', 'test');
