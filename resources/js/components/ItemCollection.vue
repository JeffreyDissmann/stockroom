<script setup lang="ts">
import ItemCardCarousel from '@/components/ItemCardCarousel.vue';
import ItemThumbnail from '@/components/ItemThumbnail.vue';
import TagBadge from '@/components/TagBadge.vue';
import itemRoutes from '@/routes/items';
import type { ItemSummary, ItemViewMode } from '@/types';
import { Link, router } from '@inertiajs/vue3';
import { X } from 'lucide-vue-next';

const props = defineProps<{
    items: ItemSummary[];
    view: ItemViewMode;
    // When true, each row/card renders an × that emits `remove` with the
    // item. Used by the Related items section on item Show to unlink.
    removable?: boolean;
}>();

const emit = defineEmits<{
    remove: [item: ItemSummary];
}>();

// Stop the click on the × from bubbling to the parent row/card's
// navigation handler — otherwise removing also opens the item.
function onRemoveClick(item: ItemSummary, event: MouseEvent) {
    event.stopPropagation();
    event.preventDefault();
    emit('remove', item);
}

// Keep TS happy about the unused `props` when `removable` isn't read in
// some template branches; this also serves as a clear contract surface.
void props;
</script>

<template>
    <table v-if="view === 'list'" class="table card" style="border-radius: var(--radius)">
        <thead>
            <tr>
                <th>Item</th>
                <th>Type</th>
                <th>Tags</th>
                <th class="num">Inside</th>
                <th v-if="removable" class="num" />
            </tr>
        </thead>
        <tbody>
            <tr v-for="item in items" :key="item.id" class="row-clickable" @click="router.visit(itemRoutes.show(item.id).url)">
                <td>
                    <div class="row-name">
                        <span class="row-thumb"><ItemThumbnail :item="item" size="sm" /></span>
                        <div>
                            <div class="nm">{{ item.name }}</div>
                            <div
                                v-if="item.description"
                                class="sub"
                                style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 320px"
                            >
                                {{ item.description }}
                            </div>
                        </div>
                    </div>
                </td>
                <td><span class="tag">{{ item.type.label }}</span></td>
                <td>
                    <div class="flex flex-wrap gap-1">
                        <TagBadge v-for="tag in item.tags ?? []" :key="tag.id" :tag="tag" />
                    </div>
                </td>
                <td class="num mono">{{ item.children_count ?? 0 }}</td>
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
        <div v-for="item in items" :key="item.id" class="item-card-wrap">
            <Link :href="itemRoutes.show(item.id).url" class="item-card">
                <div class="thumb">
                    <ItemCardCarousel v-if="(item.image_thumbs?.length ?? 0) > 1" :thumbs="item.image_thumbs ?? []" :alt="item.name" />
                    <ItemThumbnail v-else :item="item" size="md" />
                </div>
                <div class="info">
                    <div class="nm">{{ item.name }}</div>
                    <div class="meta">
                        <span>{{ item.type.label }}</span>
                        <span v-if="(item.children_count ?? 0) > 0" class="mono">{{ item.children_count }} inside</span>
                    </div>
                    <div v-if="item.tags?.length" class="flex flex-wrap gap-1 mt-2">
                        <TagBadge v-for="tag in item.tags" :key="tag.id" :tag="tag" />
                    </div>
                </div>
            </Link>
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
/* Wrapper around each card needed so we can absolutely-position the remove
   button without disturbing the Link's clickable area. */
.item-card-wrap {
    position: relative;
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
    transition: opacity 0.12s, color 0.12s, border-color 0.12s;
}
.item-card-wrap:hover .item-remove-btn,
.item-remove-btn:focus-visible {
    opacity: 1;
}
.item-remove-btn:hover {
    color: var(--neg);
    border-color: var(--neg);
}
</style>
