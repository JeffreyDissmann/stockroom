<?php

declare(strict_types=1);

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\AssistantController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ImageSearchController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\ItemImageController;
use App\Http\Controllers\ItemPhotoAnalysisController;
use App\Http\Controllers\Items\BoxController;
use App\Http\Controllers\Items\BulkController;
use App\Http\Controllers\Items\HomeAssistantLinkController;
use App\Http\Controllers\Items\MaintenanceEntryController;
use App\Http\Controllers\Items\MaintenanceTaskController;
use App\Http\Controllers\Items\PaperlessLinkController;
use App\Http\Controllers\Items\RelatedItemController;
use App\Http\Controllers\MaintenanceController;
use App\Http\Controllers\PaperlessWebhookController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\TagController;
use App\Http\Middleware\EnsurePaperlessEnabled;
use App\Http\Middleware\VerifyPaperlessSignature;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

// Paperless-ngx workflow webhook (#7). Sits outside the `auth` group on
// purpose — Paperless authenticates via the static X-Stockroom-Secret
// header, not a user session. EnsurePaperlessEnabled 404s the route when
// the integration is disabled; VerifyPaperlessSignature 401s on missing
// or wrong secret.
Route::post('webhooks/paperless/document', [PaperlessWebhookController::class, 'store'])
    ->middleware([EnsurePaperlessEnabled::class, VerifyPaperlessSignature::class])
    ->name('webhooks.paperless.document');

Route::middleware('auth')->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('search', SearchController::class)->name('search');

    Route::get('activity', ActivityController::class)->name('activity');

    // Household-wide maintenance overview (per-item maintenance CRUD lives
    // in the items/{item}/maintenance-* routes below).
    Route::get('maintenance', MaintenanceController::class)->name('maintenance');

    Route::get('items/{item}/move-targets', [ItemController::class, 'moveTargets'])->name('items.move-targets');
    Route::patch('items/{item}/move', [ItemController::class, 'move'])->name('items.move');

    // Analyse an uploaded photo into draft item fields (gated by EnsureAiEnabled on the controller).
    Route::post('items/analyze-photo', ItemPhotoAnalysisController::class)->name('items.analyze-photo');

    // Inventory chat assistant (gated by EnsureAiEnabled on the controller).
    Route::post('assistant/messages', [AssistantController::class, 'messages'])->name('assistant.messages');
    Route::get('assistant/conversation', [AssistantController::class, 'conversation'])->name('assistant.conversation');

    Route::resource('items', ItemController::class);

    // Bulk operations on selected items (delete / move / attach-tag /
    // detach-tag). Single endpoint with action dispatch; see BulkController.
    Route::post('items/bulk', BulkController::class)->name('items.bulk');

    // JSON-only endpoints used by item dialogs (search-as-you-type pickers).
    // Sit outside the resource because they're not REST verbs on the item itself.
    Route::get('items/{item}/related-item-targets', [ItemController::class, 'relatedItemTargets'])->name('items.related-item-targets');

    // Gated by the EnsureImageSearchEnabled middleware declared on the controller.
    Route::get('items/{item}/image-search', [ImageSearchController::class, 'search'])->name('items.image-search');
    Route::post('items/{item}/images/from-search', [ImageSearchController::class, 'attach'])->name('items.images.from-search');

    // "Create a box for this item" (#9) — spawns a Container child representing
    // the source item's original packaging. Open to every authenticated user;
    // item edit is also unrestricted, so gating box-creation differently would
    // be inconsistent.
    Route::post('items/{item}/box', [BoxController::class, 'store'])->name('items.box.store');

    // Symmetric "related items" link — see Item::linkRelated for the data
    // model. Each request operates on a specific item, but the underlying
    // pivot write touches both sides of the pair.
    Route::post('items/{item}/related-items', [RelatedItemController::class, 'store'])->name('items.related-items.store');
    Route::delete('items/{item}/related-items/{related}', [RelatedItemController::class, 'destroy'])->name('items.related-items.destroy');

    // Paperless-ngx link maintenance (#7). Links are created by the intake
    // job from a webhook or by hand from the Connections card. Store needs
    // the integration up (it verifies the doc against Paperless); destroy
    // is pure local and stays available even when Paperless is turned off.
    Route::post('items/{item}/paperless-links', [PaperlessLinkController::class, 'store'])
        ->middleware(EnsurePaperlessEnabled::class)
        ->name('items.paperless-links.store');
    Route::delete('items/{item}/paperless-links/{document}', [PaperlessLinkController::class, 'destroy'])
        ->whereNumber('document')
        ->name('items.paperless-links.destroy');

    // Home Assistant link maintenance. The link is created by the HA
    // integration via the v1 API; the user can remove it from the item page.
    Route::delete('items/{item}/home-assistant-link', [HomeAssistantLinkController::class, 'destroy'])
        ->name('items.home-assistant-link.destroy');

    Route::scopeBindings()->group(function () {
        Route::post('items/{item}/images', [ItemImageController::class, 'store'])->name('items.images.store');
        Route::patch('items/{item}/images/order', [ItemImageController::class, 'reorder'])->name('items.images.reorder');
        Route::patch('items/{item}/images/{image}', [ItemImageController::class, 'update'])->name('items.images.update');
        Route::delete('items/{item}/images/{image}', [ItemImageController::class, 'destroy'])->name('items.images.destroy');
    });

    // Maintenance schedules on an item. {maintenanceTask} is scope-bound to
    // its item (the parameter name resolves the maintenanceTasks relation).
    Route::scopeBindings()->group(function () {
        Route::post('items/{item}/maintenance-tasks', [MaintenanceTaskController::class, 'store'])->name('items.maintenance-tasks.store');
        Route::patch('items/{item}/maintenance-tasks/{maintenanceTask}', [MaintenanceTaskController::class, 'update'])->name('items.maintenance-tasks.update');
        Route::delete('items/{item}/maintenance-tasks/{maintenanceTask}', [MaintenanceTaskController::class, 'destroy'])->name('items.maintenance-tasks.destroy');
        Route::post('items/{item}/maintenance-tasks/{maintenanceTask}/complete', [MaintenanceTaskController::class, 'complete'])->name('items.maintenance-tasks.complete');
        Route::post('items/{item}/maintenance-tasks/{maintenanceTask}/skip', [MaintenanceTaskController::class, 'skip'])->name('items.maintenance-tasks.skip');

        // Ad-hoc maintenance history (entries without a schedule); task
        // completions are created via the complete endpoint above.
        Route::post('items/{item}/maintenance-entries', [MaintenanceEntryController::class, 'store'])->name('items.maintenance-entries.store');
        Route::delete('items/{item}/maintenance-entries/{maintenanceEntry}', [MaintenanceEntryController::class, 'destroy'])->name('items.maintenance-entries.destroy');
    });

    // Anyone may browse tags; only admins create/edit/delete them.
    Route::resource('tags', TagController::class)->only(['index']);
    Route::resource('tags', TagController::class)->only(['store', 'update', 'destroy'])->middleware('can:admin');
});

require __DIR__.'/settings.php';
require __DIR__.'/household.php';
require __DIR__.'/auth.php';
