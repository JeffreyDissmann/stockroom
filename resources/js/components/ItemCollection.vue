<script setup lang="ts">
import ItemCardCarousel from '@/components/ItemCardCarousel.vue';
import ItemThumbnail from '@/components/ItemThumbnail.vue';
import TagBadge from '@/components/TagBadge.vue';
import { useBulkSelection } from '@/composables/useBulkSelection';
import itemRoutes from '@/routes/items';
import type { ItemSummary, ItemViewMode } from '@/types';
import { Link, router } from '@inertiajs/vue3';
import { ArrowDown, ArrowUp, Check, MapPin, X } from 'lucide-vue-next';

const props = defineProps<{
    items: ItemSummary[];
    view: ItemViewMode;
    // When true, each row/card renders an × that emits `remove` with the
    // item. Used by the Related items section on item Show to unlink.
    removable?: boolean;
    // When true, the collection participates in the bulk-select store: in
    // Select mode rows show a checkbox and clicks toggle selection instead
    // of navigating to the item. Set on the items index + search results;
    // omitted on read-only embedded lists (e.g. Related items on Show).
    selectable?: boolean;
    // When set, the list-view column headers for name/contents/location
    // become clickable sort controls (search results). `sort` is the active
    // key and `sortDir` its direction; clicking a header emits `sort`.
    sort?: string;
    sortDir?: 'asc' | 'desc';
    // Render a Location column (list) / location line (grid) — search results
    // span the whole tree, so each row shows where the item lives.
    showLocation?: boolean;
}>();

const emit = defineEmits<{
    remove: [item: ItemSummary];
    sort: [key: string];
}>();

// Singleton store. ItemCollection is the only component that reads it for
// row decoration; the topbar Select toggle and the action bar drive writes.
const bulk = useBulkSelection(() => props.items.map((i) => i.id));

// Stop the click on the × from bubbling to the parent row/card's
// navigation handler — otherwise removing also opens the item.
function onRemoveClick(item: ItemSummary, event: MouseEvent) {
    event.stopPropagation();
    event.preventDefault();
    emit('remove', item);
}

// In Select mode, clicking a row toggles selection rather than navigating.
// Bound on the <tr>/<a> via `@click.capture` so it short-circuits the
// existing navigation handler.
function onRowClick(item: ItemSummary, event: MouseEvent) {
    if (props.selectable && bulk.isSelectMode.value) {
        event.preventDefault();
        event.stopPropagation();
        bulk.toggleId(item.id);
    }
}
</script>

<template>
    <table v-if="view === 'list'" class="card table" style="border-radius: var(--radius)">
        <thead>
            <tr>
                <th v-if="selectable && bulk.isSelectMode.value" class="num" style="width: 32px" />
                <th>
                    <button v-if="sort !== undefined" type="button" class="th-sort" @click="emit('sort', 'name')">
                        {{ $t('items.collection.item') }}
                        <ArrowUp v-if="sort === 'name' && sortDir === 'asc'" :size="12" />
                        <ArrowDown v-else-if="sort === 'name'" :size="12" />
                    </button>
                    <template v-else>{{ $t('items.collection.item') }}</template>
                </th>
                <th v-if="showLocation" class="hide-on-mobile">
                    <button v-if="sort !== undefined" type="button" class="th-sort" @click="emit('sort', 'location')">
                        {{ $t('items.collection.location') }}
                        <ArrowUp v-if="sort === 'location' && sortDir === 'asc'" :size="12" />
                        <ArrowDown v-else-if="sort === 'location'" :size="12" />
                    </button>
                    <template v-else>{{ $t('items.collection.location') }}</template>
                </th>
                <th class="hide-on-mobile">{{ $t('items.collection.type') }}</th>
                <th class="hide-on-mobile">{{ $t('items.collection.tags') }}</th>
                <th class="num hide-on-mobile">
                    <button v-if="sort !== undefined" type="button" class="th-sort th-sort--num" @click="emit('sort', 'count')">
                        <ArrowUp v-if="sort === 'count' && sortDir === 'asc'" :size="12" />
                        <ArrowDown v-else-if="sort === 'count'" :size="12" />
                        {{ $t('items.collection.inside') }}
                    </button>
                    <template v-else>{{ $t('items.collection.inside') }}</template>
                </th>
                <th v-if="removable" class="num" />
            </tr>
        </thead>
        <tbody>
            <tr
                v-for="item in items"
                :key="item.id"
                class="row-clickable"
                :class="{ 'row-selected': selectable && bulk.isSelected(item.id) }"
                @click="(e) => (selectable && bulk.isSelectMode.value ? onRowClick(item, e) : router.visit(itemRoutes.show(item.id).url))"
            >
                <td v-if="selectable && bulk.isSelectMode.value" class="num" @click.stop="bulk.toggleId(item.id)">
                    <span class="bulk-check" :class="{ 'bulk-check--on': bulk.isSelected(item.id) }">
                        <Check v-if="bulk.isSelected(item.id)" :size="12" />
                    </span>
                </td>
                <td>
                    <div class="row-name">
                        <span class="row-thumb"><ItemThumbnail :item="item" size="sm" /></span>
                        <div class="row-name-body">
                            <div class="nm">{{ item.name }}</div>
                            <!-- Inline tag row, mobile-only. Desktop keeps
                                 the dedicated Tags column for sortable
                                 visual alignment; on mobile that column
                                 is hidden and tags surface here right
                                 below the name. -->
                            <div v-if="item.tags?.length" class="row-name-tags">
                                <TagBadge v-for="tag in item.tags" :key="tag.id" :tag="tag" />
                            </div>
                            <!-- Inline location, mobile-only — mirrors the
                                 tag row; the dedicated Location column carries
                                 it on desktop. -->
                            <div v-if="showLocation && item.location_path" class="sub row-name-location">
                                <MapPin :size="11" />
                                {{ item.location_path }}
                            </div>
                            <div v-if="item.description" class="sub row-name-sub">
                                {{ item.description }}
                            </div>
                        </div>
                    </div>
                </td>
                <td v-if="showLocation" class="hide-on-mobile">
                    <span v-if="item.location_path" class="row-location">
                        <MapPin :size="11" />
                        {{ item.location_path }}
                    </span>
                </td>
                <td class="hide-on-mobile">
                    <span class="tag">{{ item.type.label }}</span>
                </td>
                <td class="hide-on-mobile">
                    <div class="flex flex-wrap gap-1">
                        <TagBadge v-for="tag in item.tags ?? []" :key="tag.id" :tag="tag" />
                    </div>
                </td>
                <td class="num mono hide-on-mobile">{{ item.children_count ?? 0 }}</td>
                <td v-if="removable" class="num">
                    <button
                        type="button"
                        class="btn-ghost"
                        style="padding: 4px 8px"
                        :data-test="`item-remove-${item.id}`"
                        :aria-label="$t('common.remove')"
                        @click="onRemoveClick(item, $event)"
                    >
                        <X :size="14" />
                    </button>
                </td>
            </tr>
        </tbody>
    </table>

    <div v-else class="items-grid">
        <div
            v-for="item in items"
            :key="item.id"
            class="item-card-wrap"
            :class="{ 'item-card-wrap--selected': selectable && bulk.isSelected(item.id) }"
        >
            <!--
                In Select mode we swap <Link> for a <button> rather than
                relying on `event.preventDefault()` inside an @click on the
                Link. Inertia's <Link> attaches its own click listener that
                navigates regardless of whether the user's @click prevented
                the default — they run as independent event listeners and
                Vue's `@click.prevent` doesn't propagate to Inertia's. A
                native <button> has no navigation behaviour to fight.
            -->
            <component
                :is="selectable && bulk.isSelectMode.value ? 'button' : Link"
                :href="selectable && bulk.isSelectMode.value ? undefined : itemRoutes.show(item.id).url"
                :type="selectable && bulk.isSelectMode.value ? 'button' : undefined"
                class="item-card"
                @click="selectable && bulk.isSelectMode.value ? bulk.toggleId(item.id) : null"
            >
                <span
                    v-if="selectable && bulk.isSelectMode.value"
                    class="bulk-check bulk-check--on-card"
                    :class="{ 'bulk-check--on': bulk.isSelected(item.id) }"
                >
                    <Check v-if="bulk.isSelected(item.id)" :size="12" />
                </span>
                <div class="thumb">
                    <ItemCardCarousel v-if="(item.image_thumbs?.length ?? 0) > 1" :thumbs="item.image_thumbs ?? []" :alt="item.name" />
                    <ItemThumbnail v-else :item="item" size="md" />
                </div>
                <div class="info">
                    <div class="nm">{{ item.name }}</div>
                    <div v-if="item.location_path" class="meta row-location">
                        <MapPin :size="11" />
                        <span class="truncate">{{ item.location_path }}</span>
                    </div>
                    <div class="meta">
                        <span>{{ item.type.label }}</span>
                        <span v-if="(item.children_count ?? 0) > 0" class="mono">{{ item.children_count }} inside</span>
                    </div>
                    <div v-if="item.tags?.length" class="mt-2 flex flex-wrap gap-1">
                        <TagBadge v-for="tag in item.tags" :key="tag.id" :tag="tag" />
                    </div>
                </div>
            </component>
            <button
                v-if="removable"
                type="button"
                class="item-remove-btn"
                :data-test="`item-remove-${item.id}`"
                :aria-label="$t('common.remove')"
                @click="onRemoveClick(item, $event)"
            >
                <X :size="14" />
            </button>
        </div>
    </div>
</template>

<style scoped>
/* Wrapper around each card needed so we can absolutely-position the
   remove button without disturbing the Link's clickable area. Flex
   column + height:100% so cards stretch to the grid-row height — the
   grid cell stretches by default (align-items: stretch), but the card
   inside it stayed content-sized, so a thumb-less / tag-less item ended
   up shorter than its neighbours. .item-card already has
   display: flex; flex-direction: column; we just need it to grow inside
   the wrap. */
.th-sort {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 0;
    background: transparent;
    border: 0;
    font: inherit;
    color: inherit;
    cursor: pointer;
}
.th-sort:hover {
    color: var(--fg);
}
.th-sort--num {
    flex-direction: row;
}
.row-location {
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.item-card-wrap {
    position: relative;
    display: flex;
    flex-direction: column;
    height: 100%;
}
.item-card-wrap > .item-card {
    flex: 1;
}
.item-remove-btn {
    position: absolute;
    top: 6px;
    right: 6px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 24px;
    padding: 0;
    border: 1px solid var(--border);
    border-radius: 999px;
    background: var(--bg-elev);
    color: var(--fg-muted);
    cursor: pointer;
    opacity: 0;
    transition:
        opacity 0.12s,
        color 0.12s,
        border-color 0.12s;
}
.item-card-wrap:hover .item-remove-btn,
.item-remove-btn:focus-visible {
    opacity: 1;
}
.item-remove-btn:hover {
    color: var(--neg);
    border-color: var(--neg);
}

/* Bulk-selection checkbox — drawn ourselves rather than using a native
   input so the visual matches the mono theme and the row's hit area is
   the entire cell, not just the 16px box. */
.bulk-check {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 18px;
    height: 18px;
    border: 1.5px solid var(--border-strong);
    border-radius: 4px;
    background: var(--bg-elev);
    color: var(--accent-fg);
    transition:
        background-color 0.12s,
        border-color 0.12s;
}
.bulk-check--on {
    background: var(--accent);
    border-color: var(--accent);
}
.bulk-check--on-card {
    position: absolute;
    top: 8px;
    left: 8px;
    z-index: 2;
    box-shadow: var(--shadow-sm);
}
.row-selected td {
    background: color-mix(in srgb, var(--accent) 6%, transparent);
}
.item-card-wrap--selected .item-card {
    outline: 2px solid var(--accent);
    outline-offset: 1px;
}

/* Truncate the description on desktop so a long line doesn't double the
   row height — the full text is still on the item's Show page. Capped
   at 320px (~ 2/3 of the row width on standard layouts). */
.row-name-body {
    min-width: 0;
}
.row-name-sub {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 320px;
}

/* Inline tag row in the Item cell — hidden on desktop (the dedicated
   Tags column carries them there); surfaced on mobile where every other
   column has collapsed. Small gap + flex-wrap so 3+ tags break to a
   second line gracefully. */
.row-name-tags {
    display: none;
    flex-wrap: wrap;
    gap: 4px;
    margin-top: 4px;
}

/* Inline location — hidden on desktop (the Location column carries it),
   surfaced on mobile alongside the inline tags. */
.row-name-location {
    display: none;
    align-items: center;
    gap: 4px;
    margin-top: 4px;
}

/* Mobile: collapse the table to the Item column alone — Type, Tags, and
   Inside go offscreen otherwise. Tags re-appear inline under the name
   via `.row-name-tags`. The description switches from ellipsis-on-one-
   line to a soft 2-line wrap so it stays useful even when the row is
   the only thing visible. */
@media (max-width: 880px) {
    .hide-on-mobile {
        display: none;
    }
    .row-name-tags {
        display: flex;
    }
    .row-name-location {
        display: inline-flex;
    }
    .row-name-sub {
        white-space: normal;
        max-width: none;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
}
</style>
