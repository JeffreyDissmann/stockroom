<script setup lang="ts">
import ItemCollection from '@/components/ItemCollection.vue';
import ItemViewToggle from '@/components/ItemViewToggle.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItemType, ItemSummary, ItemViewMode } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { Pencil, Plus, Search } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    parent: ItemSummary | null;
    breadcrumb: ItemSummary[];
    items: ItemSummary[];
}>();

const view = ref<ItemViewMode>('grid');
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
                <ItemViewToggle v-model="view" />
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

            <ItemCollection v-else :items="filtered" :view="view" />

            <div v-if="parent" class="flex justify-end mt-6">
                <Link :href="`/items/${parent.id}/edit`" class="btn-ghost">
                    <Pencil :size="14" />
                    Edit {{ parent.name }}
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
