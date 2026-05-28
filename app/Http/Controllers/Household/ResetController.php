<?php

declare(strict_types=1);

namespace App\Http\Controllers\Household;

use App\Http\Controllers\Controller;
use App\Http\Requests\Household\WipeDatabaseRequest;
use App\Models\CustomField;
use App\Models\Item;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;

class ResetController extends Controller
{
    /**
     * Permanently delete the inventory. Deleting items cascades their images,
     * tag pivots and custom field values; tags, custom field definitions and
     * the activity log are only removed when asked. Users and settings are
     * always preserved.
     */
    public function wipe(WipeDatabaseRequest $request): RedirectResponse
    {
        $includeTags = $request->boolean('include_tags');
        $includeCustomFields = $request->boolean('include_custom_fields');
        $includeActivity = $request->boolean('include_activity');

        DB::transaction(function () use ($includeTags, $includeCustomFields, $includeActivity): void {
            Item::query()->delete();

            if ($includeTags) {
                Tag::query()->delete();
            }

            if ($includeCustomFields) {
                CustomField::query()->delete();
            }

            if ($includeActivity) {
                // Deleting items writes activity rows ("deleted") that we
                // want gone too, so this runs *after* the item delete inside
                // the same transaction. Truncate would be faster but it
                // breaks under the surrounding transaction on Postgres.
                Activity::query()->delete();
            }
        });

        // Image rows are gone via cascade; clear their now-orphaned files.
        Storage::disk('public')->deleteDirectory('item-images');

        // Mass deletes skip model events, so the search index won't have cleared.
        Item::removeAllFromSearch();

        return back();
    }
}
