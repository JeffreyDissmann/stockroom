<script setup lang="ts">
import ItemCardCarousel from '@/components/ItemCardCarousel.vue';
import ItemThumbnail from '@/components/ItemThumbnail.vue';
import TagBadge from '@/components/TagBadge.vue';
import type { ItemSummary, ItemViewMode } from '@/types';
import { Link, router } from '@inertiajs/vue3';

defineProps<{
    items: ItemSummary[];
    view: ItemViewMode;
}>();
</script>

<template>
    <table v-if="view === 'list'" class="table card" style="border-radius: var(--radius)">
        <thead>
            <tr>
                <th>Item</th>
                <th>Type</th>
                <th>Tags</th>
                <th class="num">Inside</th>
            </tr>
        </thead>
        <tbody>
            <tr v-for="item in items" :key="item.id" class="row-clickable" @click="router.visit(`/items/${item.id}`)">
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
            </tr>
        </tbody>
    </table>

    <div v-else class="items-grid">
        <Link v-for="item in items" :key="item.id" :href="`/items/${item.id}`" class="item-card">
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
    </div>
</template>
