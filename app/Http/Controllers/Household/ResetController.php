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

class ResetController extends Controller
{
    /**
     * Permanently delete the inventory. Deleting items cascades their images,
     * tag pivots and custom field values; tags and custom field definitions are
     * only removed when asked. Users and settings are always preserved.
     */
    public function wipe(WipeDatabaseRequest $request): RedirectResponse
    {
        $includeTags = $request->boolean('include_tags');
        $includeCustomFields = $request->boolean('include_custom_fields');

        DB::transaction(function () use ($includeTags, $includeCustomFields): void {
            Item::query()->delete();

            if ($includeTags) {
                Tag::query()->delete();
            }

            if ($includeCustomFields) {
                CustomField::query()->delete();
            }
        });

        // Image rows are gone via cascade; clear their now-orphaned files.
        Storage::disk('public')->deleteDirectory('item-images');

        return back();
    }
}
