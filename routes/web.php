<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\TagController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

Route::middleware('auth')->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::patch('items/{item}/move', [ItemController::class, 'move'])->name('items.move');
    Route::resource('items', ItemController::class);
    Route::resource('tags', TagController::class)->except(['show', 'create', 'edit']);
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
