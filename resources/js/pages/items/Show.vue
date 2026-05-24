<script setup lang="ts">
import ItemThumbnail from '@/components/ItemThumbnail.vue';
import ItemTypeIcon from '@/components/ItemTypeIcon.vue';
import TagBadge from '@/components/TagBadge.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItemType, ItemImageSummary, ItemSummary } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronRight, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    item: ItemSummary;
    breadcrumb: ItemSummary[];
    children: ItemSummary[];
}>();

const breadcrumbs = computed<BreadcrumbItemType[]>(() => {
    const base: BreadcrumbItemType[] = [{ title: 'Inventory', href: '/items' }];
    for (const item of props.breadcrumb) base.push({ title: item.name, href: `/items/${item.id}` });
    base.push({ title: props.item.name, href: `/items/${props.item.id}` });
    return base;
});

const images = computed<ItemImageSummary[]>(() => props.item.images ?? []);
const initialActive = computed<ItemImageSummary | null>(() => images.value.find((i) => i.is_primary) ?? images.value[0] ?? null);
const activeImageId = ref<number | null>(initialActive.value?.id ?? null);
const activeImage = computed<ItemImageSummary | null>(
    () => images.value.find((i) => i.id === activeImageId.value) ?? initialActive.value,
);

watch(initialActive, (img) => {
    if (img && (activeImageId.value === null || !images.value.find((i) => i.id === activeImageId.value))) {
        activeImageId.value = img.id;
    }
});

function destroyItem() {
    if (!confirm(`Delete "${props.item.name}"? Any items inside will become top-level.`)) return;
    router.delete(`/items/${props.item.id}`);
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="item.name" />

        <template #topbar-actions>
            <Link :href="`/items/${item.id}/edit`" class="btn-pill">
                <Pencil :size="14" />
                Edit
            </Link>
            <button class="btn-pill btn-danger" type="button" @click="destroyItem">
                <Trash2 :size="14" />
                Delete
            </button>
            <Link :href="`/items/create?parent=${item.id}`" class="btn-primary">
                <Plus :size="14" />
                Add child
            </Link>
        </template>

        <div class="page">
            <div class="detail-grid">
                <div class="gallery">
                    <div class="main-img">
                        <img v-if="activeImage" :src="activeImage.large_url" :alt="item.name" class="gallery-img" />
                        <ItemTypeIcon v-else :type="item.type.value" />
                    </div>
                    <div v-if="images.length > 1" class="gallery-row">
                        <button
                            v-for="img in images"
                            :key="img.id"
                            type="button"
                            class="gallery-mini"
                            :class="{ 'gallery-mini-active': img.id === activeImage?.id }"
                            @click="activeImageId = img.id"
                        >
                            <img :src="img.thumb_url" :alt="''" />
                        </button>
                    </div>
                </div>

                <div class="flex flex-col gap-4">
                    <div>
                        <p class="section-label">{{ item.type.label }}</p>
                        <h1 style="margin: 4px 0 0; font-size: 26px; font-weight: 600; letter-spacing: -0.02em">{{ item.name }}</h1>
                        <p v-if="item.description" style="margin: 12px 0 0; color: var(--fg-muted); font-size: 14px">{{ item.description }}</p>
                        <div v-if="item.tags?.length" class="flex flex-wrap gap-1 mt-3">
                            <TagBadge v-for="tag in item.tags" :key="tag.id" :tag="tag" />
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-head">
                            <h3>Where</h3>
                        </div>
                        <div class="card-pad">
                            <div v-if="breadcrumb.length === 0" style="color: var(--fg-muted); font-size: 13px">Top level — not inside anything.</div>
                            <div v-else class="flex items-center flex-wrap gap-1.5" style="font-size: 13px">
                                <template v-for="(crumb, i) in breadcrumb" :key="crumb.id">
                                    <ChevronRight v-if="i > 0" :size="12" style="color: var(--fg-subtle)" />
                                    <Link :href="`/items/${crumb.id}`" class="flex items-center gap-1.5">
                                        <ItemTypeIcon :type="crumb.type.value" class="size-3.5" />
                                        <span>{{ crumb.name }}</span>
                                    </Link>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <section class="mt-8">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="section-label" style="margin: 0">Contents</h3>
                    <Link :href="`/items/create?parent=${item.id}`" class="btn-pill">
                        <Plus :size="14" />
                        Add child
                    </Link>
                </div>

                <div v-if="children.length === 0" class="card card-pad" style="text-align: center; color: var(--fg-muted)">
                    Nothing inside this {{ item.type.label.toLowerCase() }} yet.
                </div>

                <div v-else class="items-grid">
                    <Link v-for="child in children" :key="child.id" :href="`/items/${child.id}`" class="item-card">
                        <div class="thumb">
                            <ItemThumbnail :item="child" size="md" />
                        </div>
                        <div class="info">
                            <div class="nm">{{ child.name }}</div>
                            <div class="meta">
                                <span>{{ child.type.label }}</span>
                                <span v-if="(child.children_count ?? 0) > 0" class="mono">{{ child.children_count }} inside</span>
                            </div>
                            <div v-if="child.tags?.length" class="flex flex-wrap gap-1 mt-2">
                                <TagBadge v-for="tag in child.tags" :key="tag.id" :tag="tag" />
                            </div>
                        </div>
                    </Link>
                </div>
            </section>
        </div>
    </AppLayout>
</template>

<style scoped>
.gallery-img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    background: var(--bg-sunken);
}
.gallery-row {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(70px, 1fr));
    gap: 8px;
    margin-top: 8px;
}
.gallery-mini {
    aspect-ratio: 1 / 1;
    border-radius: var(--radius-sm);
    overflow: hidden;
    border: 1px solid var(--border);
    background: var(--bg-sunken);
    padding: 0;
    cursor: pointer;
}
.gallery-mini img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}
.gallery-mini-active {
    outline: 2px solid var(--fg);
    outline-offset: -2px;
}
</style>
