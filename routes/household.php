<?php

declare(strict_types=1);

use App\Http\Controllers\Household\BackupController;
use App\Http\Controllers\Household\CustomFieldController;
use App\Http\Controllers\Household\ImportController;
use App\Http\Controllers\Household\InvitationController;
use App\Http\Controllers\Household\MemberController;
use App\Http\Controllers\Household\PreferencesController;
use App\Http\Controllers\Household\ResetController;
use App\Http\Controllers\Household\SearchIndexController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::redirect('household', 'household/custom-fields');

    // Read-only views — any authenticated user may look at the household settings.
    Route::get('household/custom-fields', [CustomFieldController::class, 'index'])->name('custom-fields.index');
    Route::get('household/backup', [BackupController::class, 'index'])->name('household.backup.index');
    // /household/import used to host the HomeBox flow; it now lives on the
    // Backup & Import page. Redirect for any stale bookmarks. Must be a
    // GET-only redirect — Route::redirect() is internally Route::any(), so
    // using it here would also swallow the POST from the import form at
    // line ~40 below (since routes match in registration order). That made
    // submitting the form silently do nothing on every NAS install: the
    // POST hit this redirect, returned 302 → /household/backup, and the
    // controller never ran.
    Route::get('household/import', fn () => redirect('/household/backup'))->name('household.import.index');
    Route::get('household/search-index', [SearchIndexController::class, 'index'])->name('household.search-index.index');
    Route::get('household/members', [InvitationController::class, 'index'])->name('household.members.index');
    Route::get('household/preferences', [PreferencesController::class, 'edit'])
        ->middleware('can:admin')
        ->name('household.preferences.edit');

    // JSON picker data for the Paperless intake parent. Admin-only because
    // it powers an admin-only settings field; mirrors the moveTargets endpoint.
    Route::get('household/preferences/paperless-parent-targets', [PreferencesController::class, 'paperlessParentTargets'])
        ->middleware('can:admin')
        ->name('household.preferences.paperless-parent-targets');

    // Mutations / household tools — admins only.
    Route::middleware('can:admin')->group(function () {
        Route::post('household/custom-fields', [CustomFieldController::class, 'store'])->name('custom-fields.store');
        Route::put('household/custom-fields/{customField}', [CustomFieldController::class, 'update'])->name('custom-fields.update');
        Route::delete('household/custom-fields/{customField}', [CustomFieldController::class, 'destroy'])->name('custom-fields.destroy');

        Route::get('household/backup/export', [BackupController::class, 'export'])->name('household.backup.export');
        Route::post('household/backup/import', [BackupController::class, 'import'])->name('household.backup.import');

        Route::post('household/reset', [ResetController::class, 'wipe'])->name('household.reset');

        Route::put('household/preferences', [PreferencesController::class, 'update'])->name('household.preferences.update');

        // Operator repair: re-apply Stockroom annotations (linked tag +
        // backlink URL) on every Paperless doc that local items are
        // currently linked to. EnsurePaperlessEnabled is also declared on
        // the controller method via attribute — belt and braces.
        Route::post('household/preferences/paperless/relink-all', [PreferencesController::class, 'relinkAllPaperless'])
            ->name('household.preferences.paperless.relink-all');
        // The read-only variant: refresh cached document title/type only,
        // no writes back to Paperless. Also run daily by the scheduler.
        Route::post('household/preferences/paperless/refresh-metadata', [PreferencesController::class, 'refreshPaperlessMetadata'])
            ->name('household.preferences.paperless.refresh-metadata');

        Route::post('household/import', [ImportController::class, 'start'])->name('household.import.start');

        Route::post('household/search-index', [SearchIndexController::class, 'rebuild'])->name('household.search-index.rebuild');

        Route::post('household/invitations', [InvitationController::class, 'store'])->name('household.invitations.store');
        Route::delete('household/invitations/{invitation}', [InvitationController::class, 'destroy'])->name('household.invitations.destroy');
        // Re-mail a pending invite to its stored address (lost mails,
        // spam folders) without recreating the link.
        Route::post('household/invitations/{invitation}/resend', [InvitationController::class, 'resend'])->name('household.invitations.resend');

        Route::patch('household/members/{user}', [MemberController::class, 'update'])->name('household.members.update');
        Route::delete('household/members/{user}', [MemberController::class, 'destroy'])->name('household.members.destroy');
    });
});
