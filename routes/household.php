<?php

declare(strict_types=1);

use App\Http\Controllers\Household\BackupController;
use App\Http\Controllers\Household\CustomFieldController;
use App\Http\Controllers\Household\ImportController;
use App\Http\Controllers\Household\InvitationController;
use App\Http\Controllers\Household\ResetController;
use App\Http\Controllers\Household\SearchIndexController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::redirect('household', 'household/custom-fields');

    // Read-only views — any authenticated user may look at the household settings.
    Route::get('household/custom-fields', [CustomFieldController::class, 'index'])->name('custom-fields.index');
    Route::get('household/backup', [BackupController::class, 'index'])->name('household.backup.index');
    Route::get('household/import', [ImportController::class, 'index'])->name('household.import.index');
    Route::get('household/search-index', [SearchIndexController::class, 'index'])->name('household.search-index.index');
    Route::get('household/members', [InvitationController::class, 'index'])->name('household.members.index');

    // Mutations / household tools — admins only.
    Route::middleware('can:admin')->group(function () {
        Route::post('household/custom-fields', [CustomFieldController::class, 'store'])->name('custom-fields.store');
        Route::put('household/custom-fields/{customField}', [CustomFieldController::class, 'update'])->name('custom-fields.update');
        Route::delete('household/custom-fields/{customField}', [CustomFieldController::class, 'destroy'])->name('custom-fields.destroy');

        Route::get('household/backup/export', [BackupController::class, 'export'])->name('household.backup.export');
        Route::post('household/backup/import', [BackupController::class, 'import'])->name('household.backup.import');

        Route::post('household/reset', [ResetController::class, 'wipe'])->name('household.reset');

        Route::post('household/import', [ImportController::class, 'start'])->name('household.import.start');

        Route::post('household/search-index', [SearchIndexController::class, 'rebuild'])->name('household.search-index.rebuild');

        Route::post('household/invitations', [InvitationController::class, 'store'])->name('household.invitations.store');
        Route::delete('household/invitations/{invitation}', [InvitationController::class, 'destroy'])->name('household.invitations.destroy');
    });
});
