<script setup lang="ts">
import ItemThumbnail from '@/components/ItemThumbnail.vue';
import TagBadge from '@/components/TagBadge.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItemType, ItemSummary } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { Grid, List, Pencil, Plus, Search } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    parent: ItemSummary | null;
    breadcrumb: ItemSummary[];
    items: ItemSummary[];
}>();

const view = ref<'list' | 'grid'>('grid');
const search = ref('');

const filtered = computed(() => {
    const q = search.value.trim().toLowerCase();
    if (!q) return props.items;
    return props.items.filter(
        (i) =>
            i.name.toLowerCase().includes(q) ||
            (i.description ?? '').toLowerCase().includes(q) ||
            (i.tags ?? []).some((t) => t.name.toLowerCase().includes(q)),
    );
});

const pageTitle = computed(() => props.parent?.name ?? 'Inventory');

const breadcrumbs = computed<BreadcrumbItemType[]>(() => {
    const base: BreadcrumbItemType[] = [{ title: 'Inventory', href: '/items' }];
    for (const item of props.breadcrumb) base.push({ title: item.name, href: `/items/${item.id}` });
    return base;
});

const createHref = computed(() => (props.parent ? `/items/create?parent=${props.parent.id}` : '/items/create'));

watch(
    () => props.parent?.id,
    () => (search.value = ''),
);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="pageTitle" />

        <template #topbar-actions>
            <Link :href="createHref" class="btn-primary">
                <Plus :size="14" />
                Add item
            </Link>
        </template>

        <div class="page">
            <div class="flex flex-wrap items-baseline justify-between gap-3 mb-5">
                <div>
                    <h2 style="margin: 0; font-size: 22px; font-weight: 600; letter-spacing: -0.015em">{{ pageTitle }}</h2>
                    <p v-if="parent?.description" class="mt-1 text-sm" style="color: var(--fg-muted)">{{ parent.description }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <button :class="['chip', view === 'list' ? 'active' : '']" type="button" @click="view = 'list'">
                        <List />
                        List
                    </button>
                    <button :class="['chip', view === 'grid' ? 'active' : '']" type="button" @click="view = 'grid'">
                        <Grid />
                        Grid
                    </button>
                </div>
            </div>

            <div class="filterbar" style="padding: 0; margin-bottom: 14px">
                <div class="search">
                    <Search :size="14" />
                    <input v-model="search" type="search" :placeholder="`Search ${items.length} item${items.length === 1 ? '' : 's'}`" />
                </div>
                <span class="section-label" style="margin-left: auto">{{ filtered.length }} shown</span>
            </div>

            <div v-if="filtered.length === 0" class="card card-pad" style="text-align: center; color: var(--fg-muted)">
                <p v-if="items.length === 0" style="margin: 0">
                    Nothing here yet.
                    <Link :href="createHref" style="color: var(--fg); font-weight: 500; text-decoration: underline; text-underline-offset: 3px">Add the first item</Link>.
                </p>
                <p v-else style="margin: 0">No items match your search.</p>
            </div>

            <table v-else-if="view === 'list'" class="table card" style="border-radius: var(--radius)">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Type</th>
                        <th>Tags</th>
                        <th class="num">Inside</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="item in filtered"
                        :key="item.id"
                        class="row-clickable"
                        @click="router.visit(`/items/${item.id}`)"
                    >
                        <td>
                            <div class="row-name">
                                <span class="row-thumb"><ItemThumbnail :item="item" size="sm" /></span>
                                <div>
                                    <div class="nm">{{ item.name }}</div>
                                    <div v-if="item.description" class="sub" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 320px">
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
                <Link v-for="item in filtered" :key="item.id" :href="`/items/${item.id}`" class="item-card">
                    <div class="thumb">
                        <ItemThumbnail :item="item" size="md" />
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

            <div v-if="parent" class="flex justify-end mt-6">
                <Link :href="`/items/${parent.id}/edit`" class="btn-ghost">
                    <Pencil :size="14" />
                    Edit {{ parent.name }}
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
