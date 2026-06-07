<script setup lang="ts">
import BulkActionBar from '@/components/BulkActionBar.vue';
import BulkSelectToggle from '@/components/BulkSelectToggle.vue';
import ItemCollection from '@/components/ItemCollection.vue';
import ItemViewToggle from '@/components/ItemViewToggle.vue';
import { useBulkSelection } from '@/composables/useBulkSelection';
import { trans } from '@/composables/useTranslations';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItemType, ItemSummary, ItemViewMode, TagSummary } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { Pencil, Plus, Search } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    parent: ItemSummary | null;
    breadcrumb: ItemSummary[];
    items: ItemSummary[];
    // Tags are sent for the bulk-tag dialog. Controller passes the full
    // list since the picker shows them all (paginating tags is overkill).
    tags?: TagSummary[];
}>();

const bulk = useBulkSelection(() => props.items.map((i) => i.id));

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

const pageTitle = computed(() => props.parent?.name ?? trans('items.inventory'));

const breadcrumbs = computed<BreadcrumbItemType[]>(() => {
    const base: BreadcrumbItemType[] = [{ title: trans('items.inventory'), href: '/items' }];
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
                {{ $t('nav.add_item') }}
            </Link>
        </template>

        <div class="page">
            <div class="mb-5 flex flex-wrap items-baseline justify-between gap-3">
                <div>
                    <h2 style="margin: 0; font-size: 22px; font-weight: 600; letter-spacing: -0.015em">{{ pageTitle }}</h2>
                    <p v-if="parent?.description" class="mt-1 text-sm" style="color: var(--fg-muted)">{{ parent.description }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <BulkSelectToggle />
                    <ItemViewToggle v-model="view" />
                </div>
            </div>

            <div class="filterbar" style="padding: 0; margin-bottom: 14px">
                <div class="search">
                    <Search :size="14" />
                    <input v-model="search" type="search" :placeholder="$tChoice('items.index.search', items.length)" />
                </div>
                <span class="section-label" style="margin-left: auto">{{ $t('items.index.shown', { count: filtered.length }) }}</span>
            </div>

            <div v-if="filtered.length === 0" class="card card-pad" style="text-align: center; color: var(--fg-muted)">
                <p v-if="items.length === 0" style="margin: 0">
                    {{ $t('items.index.empty') }}
                    <Link :href="createHref" style="color: var(--fg); font-weight: 500; text-decoration: underline; text-underline-offset: 3px">{{
                        $t('items.index.add_first')
                    }}</Link
                    >.
                </p>
                <p v-else style="margin: 0">{{ $t('items.index.no_match') }}</p>
            </div>

            <ItemCollection v-else :items="filtered" :view="view" selectable />

            <div v-if="parent" class="mt-6 flex justify-end">
                <Link :href="`/items/${parent.id}/edit`" class="btn-ghost">
                    <Pencil :size="14" />
                    {{ $t('items.edit_title', { name: parent.name }) }}
                </Link>
            </div>
        </div>

        <BulkActionBar v-if="bulk.isSelectMode.value" :tags="tags ?? []" />
    </AppLayout>
</template>
