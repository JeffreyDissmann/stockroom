<script setup lang="ts">
import ItemCollection from '@/components/ItemCollection.vue';
import ItemViewToggle from '@/components/ItemViewToggle.vue';
import TagFilter from '@/components/TagFilter.vue';
import { trans } from '@/composables/useTranslations';
import AppLayout from '@/layouts/AppLayout.vue';
import { search } from '@/routes';
import type { BreadcrumbItemType, ItemSummary, ItemTypeValue, ItemViewMode, TagSummary } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { Search as SearchIcon } from 'lucide-vue-next';
import { ref, watch } from 'vue';

interface Paginated<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    total: number;
    from: number | null;
    to: number | null;
}

const props = defineProps<{
    query: string;
    filters: { type: ItemTypeValue | null; tags: number[]; sort: 'relevance' | 'name' | 'added' | 'edited' };
    items: Paginated<ItemSummary>;
    tags: TagSummary[];
    types: { value: ItemTypeValue; label: string }[];
}>();

const breadcrumbs: BreadcrumbItemType[] = [{ title: trans('nav.search'), href: search().url }];

const term = ref(props.query);
const view = ref<ItemViewMode>('list');

function apply(overrides: Record<string, string | number | number[] | null>) {
    const params: Record<string, string | number | number[]> = {};
    const merged = { q: term.value, type: props.filters.type, tags: props.filters.tags, sort: props.filters.sort, ...overrides };
    for (const [key, value] of Object.entries(merged)) {
        if (Array.isArray(value)) {
            if (value.length > 0) params[key] = value;
        } else if (value !== null && value !== '' && !(key === 'sort' && value === 'relevance')) {
            params[key] = value as string | number;
        }
    }
    router.get(search().url, params, { preserveState: true, preserveScroll: true, replace: true });
}

// Auto-search a short moment after the user stops typing.
let debounce: ReturnType<typeof setTimeout> | undefined;
watch(term, () => {
    clearTimeout(debounce);
    debounce = setTimeout(() => apply({ q: term.value }), 400);
});

// Search right away when the field is cleared (the native ✕) or Enter is hit.
function searchNow() {
    clearTimeout(debounce);
    apply({ q: term.value });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="$t('nav.search')" />

        <div class="page">
            <form class="filterbar searchbar" style="padding: 0; margin-bottom: 14px" @submit.prevent>
                <div class="search" style="flex: 1">
                    <SearchIcon :size="14" />
                    <input v-model="term" type="search" :placeholder="$t('search.placeholder')" autofocus @search="searchNow" />
                </div>
            </form>

            <div class="flex flex-wrap items-center gap-2 mb-4">
                <div class="flex items-center gap-1">
                    <button type="button" :class="['chip', filters.type === null ? 'active' : '']" @click="apply({ type: null })">{{ $t('common.all') }}</button>
                    <button
                        v-for="t in types"
                        :key="t.value"
                        type="button"
                        :class="['chip', filters.type === t.value ? 'active' : '']"
                        @click="apply({ type: t.value })"
                    >
                        {{ t.label }}
                    </button>
                </div>

                <TagFilter :tags="tags" :model-value="filters.tags" @update:model-value="(value) => apply({ tags: value })" />

                <select class="field" style="max-width: 150px" :value="filters.sort" @change="apply({ sort: ($event.target as HTMLSelectElement).value })">
                    <option value="relevance">{{ $t('search.sort.relevance') }}</option>
                    <option value="name">{{ $t('search.sort.name') }}</option>
                    <option value="added">{{ $t('search.sort.added') }}</option>
                    <option value="edited">{{ $t('search.sort.edited') }}</option>
                </select>

                <div class="flex items-center gap-2" style="margin-left: auto">
                    <span class="section-label">{{ $tChoice('search.results', items.total) }}</span>
                    <ItemViewToggle v-model="view" />
                </div>
            </div>

            <div v-if="items.data.length === 0" class="card card-pad" style="text-align: center; color: var(--fg-muted)">
                <p v-if="query === '' && filters.type === null && filters.tags.length === 0" style="margin: 0">{{ $t('search.empty_prompt') }}</p>
                <p v-else style="margin: 0">{{ $t('search.no_match') }}</p>
            </div>

            <template v-else>
                <ItemCollection :items="items.data" :view="view" />

                <nav v-if="items.links.length > 3" class="flex flex-wrap gap-1 mt-6 justify-center">
                    <component
                        :is="link.url ? Link : 'span'"
                        v-for="(link, i) in items.links"
                        :key="i"
                        :href="link.url ?? undefined"
                        :preserve-scroll="true"
                        :class="['chip', link.active ? 'active' : '', !link.url ? 'opacity-40 pointer-events-none' : '']"
                    >
                        <span v-html="link.label" />
                    </component>
                </nav>
            </template>
        </div>
    </AppLayout>
</template>
