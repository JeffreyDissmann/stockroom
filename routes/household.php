<?php

declare(strict_types=1);

use App\Http\Controllers\Household\BackupController;
use App\Http\Controllers\Household\CustomFieldController;
use App\Http\Controllers\Household\ImportController;
use App\Http\Controllers\Household\ResetController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::redirect('household', 'household/custom-fields');

    Route::get('household/custom-fields', [CustomFieldController::class, 'index'])->name('custom-fields.index');
    Route::post('household/custom-fields', [CustomFieldController::class, 'store'])->name('custom-fields.store');
    Route::put('household/custom-fields/{customField}', [CustomFieldController::class, 'update'])->name('custom-fields.update');
    Route::delete('household/custom-fields/{customField}', [CustomFieldController::class, 'destroy'])->name('custom-fields.destroy');

    Route::get('household/backup', [BackupController::class, 'index'])->name('household.backup.index');
    Route::get('household/backup/export', [BackupController::class, 'export'])->name('household.backup.export');
    Route::post('household/backup/import', [BackupController::class, 'import'])->name('household.backup.import');

    Route::post('household/reset', [ResetController::class, 'wipe'])->name('household.reset');

    Route::get('household/import', [ImportController::class, 'index'])->name('household.import.index');
    Route::post('household/import', [ImportController::class, 'start'])->name('household.import.start');
});
