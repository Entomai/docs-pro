<?php

use Botble\Base\Facades\AdminHelper;
use Botble\DocsPro\Http\Controllers\DocController;
use Botble\DocsPro\Http\Controllers\DocProductController;
use Botble\DocsPro\Http\Controllers\DocsImportController;
use Botble\DocsPro\Http\Controllers\PublicDocsController;
use Illuminate\Support\Facades\Route;

AdminHelper::registerRoutes(function (): void {
    Route::prefix('docs-pro/products')->name('docs-pro.products.')->group(function (): void {
        Route::match(['GET', 'POST'], '/', [DocProductController::class, 'index'])->name('index');
        Route::get('create', [DocProductController::class, 'create'])->name('create');
        Route::post('create', [DocProductController::class, 'store'])->name('store');
        Route::get('{product}/edit', [DocProductController::class, 'edit'])->name('edit');
        Route::put('{product}', [DocProductController::class, 'update'])->name('update');
        Route::delete('{product}', [DocProductController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('docs-pro/products/{product}/docs')->name('docs-pro.docs.')->group(function (): void {
        Route::get('/', [DocController::class, 'index'])->name('index');
        Route::get('data', [DocController::class, 'data'])->name('data');
        Route::post('add-node', [DocController::class, 'store'])->name('store');
        Route::post('save-all', [DocController::class, 'saveAll'])->name('save-all');
        Route::post('preview', [DocController::class, 'preview'])->name('preview');
        Route::post('structure', [DocController::class, 'updateStructure'])->name('structure');
        Route::get('create', [DocController::class, 'create'])->name('create');
        Route::get('{doc}/edit', [DocController::class, 'edit'])->name('edit');
        Route::put('{doc}', [DocController::class, 'update'])->name('update');
        Route::delete('{doc}', [DocController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('docs-pro/products/{product}/import')
        ->name('docs-pro.import.')
        ->group(function (): void {
            Route::group(['permission' => 'docs-pro.import'], function (): void {
                Route::get('/', [DocsImportController::class, 'create'])->name('create');
                Route::post('/', [DocsImportController::class, 'store'])->name('store');
            });

            Route::group(['permission' => 'docs-pro.export'], function (): void {
                Route::get('export', [DocsImportController::class, 'export'])->name('export');
            });
        });
});

Route::prefix('docs')->name('public.docs.')->group(function (): void {
    Route::get('/', [PublicDocsController::class, 'index'])->name('index');
    Route::get('assets/{productSlug}/{path}', [PublicDocsController::class, 'asset'])
        ->where('path', '.*')
        ->name('asset');
    Route::get('{productSlug}/{path?}', [PublicDocsController::class, 'show'])
        ->where('path', '.*')
        ->name('show');
});
