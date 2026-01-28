<?php

use App\Http\Controllers\Api\admin\ArticleSetController;
use App\Http\Controllers\Api\admin\DomainCategoryController;
use App\Http\Controllers\Api\admin\DomainController;
use App\Http\Controllers\Api\admin\DomainSetController;
use App\Http\Controllers\Api\admin\ArticleController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


//
Route::prefix('admin')->name('admin.api.')->middleware('admin.api.auth')->group(function () {

    Route::post('/domain/category/{id}', [DomainCategoryController::class, 'update'])->name('domain.category.update');

    Route::post('/domain/bulk/add', [DomainController::class, 'bulk_upload'])->name('domain.bulk.upload');

    Route::post('/domain/set/initialize', [DomainSetController::class, 'initialize'])->name('domain.set.initialize');

    Route::get('/domain/set/fetch/{id}', [DomainSetController::class, 'setDomains'])->name('fetch.set.domains');

    Route::get('/domains/{id}', [DomainController::class, 'domains'])->name('domains.list'); // this is the routes for showing routes

    Route::post('/domains/validate', [DomainController::class, 'validateDomains'])->name('validate.domains'); // this is the routes for showing routes

    Route::post('/article/set/initialize', [ArticleSetController::class, 'initialize'])->name('article.set.initialize');

    Route::get('/set/articles/{id}', [ArticleSetController::class, 'SetArticles'])->name('set.article');

    Route::post('/article/search', [ArticleController::class, 'search'])->name('articles.search');

    Route::post('/article/by-language', [ArticleController::class, 'getByLanguage'])->name('articles.by.language');
});
