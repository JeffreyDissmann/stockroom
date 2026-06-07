<script setup lang="ts">
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import itemRoutes from '@/routes/items';
import itemImages from '@/routes/items/images';
import type { ImageSearchResult } from '@/types';
import { router } from '@inertiajs/vue3';
import { watchDebounced } from '@vueuse/core';
import { Check, ImagePlus } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    itemId: number;
    itemName?: string;
    autoOpen?: boolean;
    // Tailwind class on the inline trigger button — lets the parent hide it
    // on a given breakpoint (e.g. `hidden md:inline-flex`) while still being
    // able to open the dialog programmatically via the exposed openDialog().
    triggerClass?: string;
}>();

const open = ref(false);

// Lets a parent open the dialog from outside (e.g. a mobile More-menu item)
// without having to model the dialog's open state at parent level.
defineExpose({
    openDialog: () => {
        open.value = true;
    },
});

// `autoOpen` is set by the parent when it knows the user just landed here to
// pick a photo — currently after creating a box via /items/{item}/box, which
// redirects with ?focus=images. The dialog's existing trigger button is
// still the normal way in; this just removes the extra click in that one
// natural flow.
watch(
    () => props.autoOpen,
    (yes) => {
        if (yes) {
            open.value = true;
        }
    },
    { immediate: true },
);
const query = ref('');
const results = ref<ImageSearchResult[]>([]);
const selected = ref<string[]>([]);
const loading = ref(false);
const attaching = ref(false);
const failed = ref(false);

// Track the last query we fetched so the programmatic prefill doesn't trigger a
// second (quota-wasting) search via the watcher below.
let lastQuery: string | null = null;

const canAttach = computed(() => selected.value.length > 0 && !attaching.value);

async function fetchResults(q?: string): Promise<void> {
    loading.value = true;
    failed.value = false;
    try {
        const url = itemRoutes.imageSearch(props.itemId, q !== undefined ? { query: { q } } : undefined).url;
        const response = await fetch(url, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        if (!response.ok) {
            failed.value = true;
            results.value = [];
            return;
        }
        const data = await response.json();
        if (q === undefined) {
            query.value = data.query ?? '';
        }
        lastQuery = q ?? data.query ?? '';
        results.value = data.results ?? [];
    } catch {
        failed.value = true;
        results.value = [];
    } finally {
        loading.value = false;
    }
}

watch(open, (isOpen) => {
    if (isOpen) {
        query.value = '';
        selected.value = [];
        results.value = [];
        lastQuery = null;
        fetchResults(); // no q → server distills a default and echoes it back
    }
});

watchDebounced(
    query,
    (q) => {
        if (open.value && q !== lastQuery) {
            fetchResults(q);
        }
    },
    { debounce: 350 },
);

function toggle(url: string): void {
    selected.value = selected.value.includes(url) ? selected.value.filter((u) => u !== url) : [...selected.value, url];
}

function attach(): void {
    attaching.value = true;
    router.post(
        itemImages.fromSearch(props.itemId).url,
        { urls: selected.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                open.value = false;
            },
            onFinish: () => {
                attaching.value = false;
            },
        },
    );
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <button type="button" :class="['btn-pill', triggerClass]" data-test="image-search">
                <ImagePlus :size="14" />
                {{ $t('items.image_search.trigger') }}
            </button>
        </DialogTrigger>
        <DialogContent class="sm:max-w-2xl">
            <DialogHeader>
                <DialogTitle>{{ $t('items.image_search.title') }}</DialogTitle>
                <DialogDescription>{{
                    itemName ? $t('items.image_search.description_named', { name: itemName }) : $t('items.image_search.description')
                }}</DialogDescription>
            </DialogHeader>

            <input
                v-model="query"
                type="search"
                class="field"
                style="width: 100%"
                :placeholder="$t('items.image_search.search')"
                data-test="image-search-query"
            />

            <div class="img-results">
                <p v-if="loading" class="img-results-note">{{ $t('items.image_search.searching') }}</p>
                <p v-else-if="failed" class="img-results-note">{{ $t('items.image_search.unavailable') }}</p>
                <p v-else-if="results.length === 0" class="img-results-note">{{ $t('items.image_search.none') }}</p>
                <div v-else class="img-grid">
                    <button
                        v-for="result in results"
                        :key="result.image_url"
                        type="button"
                        class="img-card is-selectable"
                        :class="{ 'is-selected': selected.includes(result.image_url) }"
                        :title="result.title"
                        data-test="image-result"
                        @click="toggle(result.image_url)"
                    >
                        <img :src="result.thumb_url" :alt="result.title" loading="lazy" />
                        <span v-if="selected.includes(result.image_url)" class="img-check"><Check :size="13" /></span>
                    </button>
                </div>
            </div>

            <DialogFooter>
                <DialogClose as-child>
                    <button type="button" class="btn-ghost">{{ $t('common.cancel') }}</button>
                </DialogClose>
                <button type="button" class="btn-primary" :disabled="!canAttach" data-test="image-search-attach" @click="attach">
                    <ImagePlus :size="14" />
                    {{ $t('items.image_search.attach') }}{{ selected.length ? ` ${selected.length}` : '' }}
                </button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
