<?php

declare(strict_types=1);

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ImageSearchController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\ItemImageController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\TagController;
use App\Http\Middleware\EnsureImageSearchEnabled;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

Route::middleware('auth')->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('search', SearchController::class)->name('search');

    Route::get('activity', ActivityController::class)->name('activity');

    Route::get('items/{item}/move-targets', [ItemController::class, 'moveTargets'])->name('items.move-targets');
    Route::patch('items/{item}/move', [ItemController::class, 'move'])->name('items.move');
    Route::resource('items', ItemController::class);

    Route::middleware(EnsureImageSearchEnabled::class)->group(function () {
        Route::get('items/{item}/image-search', [ImageSearchController::class, 'search'])->name('items.image-search');
        Route::post('items/{item}/images/from-search', [ImageSearchController::class, 'attach'])->name('items.images.from-search');
    });

    Route::scopeBindings()->group(function () {
        Route::post('items/{item}/images', [ItemImageController::class, 'store'])->name('items.images.store');
        Route::patch('items/{item}/images/order', [ItemImageController::class, 'reorder'])->name('items.images.reorder');
        Route::patch('items/{item}/images/{image}', [ItemImageController::class, 'update'])->name('items.images.update');
        Route::delete('items/{item}/images/{image}', [ItemImageController::class, 'destroy'])->name('items.images.destroy');
    });

    Route::resource('tags', TagController::class)->except(['show', 'create', 'edit']);
});

require __DIR__.'/settings.php';
require __DIR__.'/household.php';
require __DIR__.'/auth.php';
