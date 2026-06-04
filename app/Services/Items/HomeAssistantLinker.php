<?php

declare(strict_types=1);

namespace App\Services\Items;

use App\Models\HomeAssistantLink;
use App\Models\Item;
use App\Models\Setting;
use App\Models\Tag;

/**
 * Centralises the Home Assistant link lifecycle so every entry point — the v1
 * API (set/replace + delete) and the web unlink — shares one path: write the
 * 1:1 link row and keep the auto-managed "HomeAssistant" tag in sync.
 *
 * The tag is created on demand the first time anything is linked and recorded
 * as the household's `home_assistant_tag_id` setting; once selected, the Tags
 * UI protects it from deletion (mirroring the Box tag). Unlinking an item
 * removes the tag from that item but leaves the tag itself in place.
 */
class HomeAssistantLinker
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function link(Item $item, array $attributes): HomeAssistantLink
    {
        $link = $item->homeAssistantLink()->updateOrCreate(
            ['item_id' => $item->id],
            $attributes,
        );

        // Additive — never disturbs the item's other tags. Re-index because
        // the search document embeds tag names.
        $item->tags()->syncWithoutDetaching([$this->tag()->id]);
        $item->searchable();

        return $link;
    }

    public function unlink(Item $item): void
    {
        // Delete the model instance (not the relation query) so the row goes
        // through Eloquent; first() keeps this safe under shouldBeStrict().
        $item->homeAssistantLink()->first()?->delete();

        // Reindex only when the tag was actually removed — detach() returns
        // the number of pivot rows deleted, so a no-op unlink skips the work.
        $tagId = Setting::int('home_assistant_tag_id');

        if ($tagId !== null && $item->tags()->detach($tagId) > 0) {
            $item->searchable();
        }
    }

    /**
     * The tag to auto-assign. Honours the household's choice: if a Home
     * Assistant tag has been selected in settings, that tag is used. Only when
     * nothing is configured (or the selected tag has since been deleted) is the
     * default "HomeAssistant" tag created on demand and recorded as selected —
     * which then protects it from deletion in the Tags UI.
     */
    private function tag(): Tag
    {
        $selectedId = Setting::int('home_assistant_tag_id');

        if ($selectedId !== null && ($selected = Tag::query()->find($selectedId)) !== null) {
            return $selected;
        }

        $tag = Tag::firstOrCreate(
            ['name' => 'HomeAssistant'],
            ['color' => '#41bdf5'], // Home Assistant brand blue
        );

        Setting::set('home_assistant_tag_id', $tag->id);

        return $tag;
    }
}
