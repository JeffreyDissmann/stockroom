<script setup lang="ts">
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import type { ImageSearchResult } from '@/types';
import { router } from '@inertiajs/vue3';
import { watchDebounced } from '@vueuse/core';
import { Check, ImagePlus } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const props = defineProps<{ itemId: number; itemName?: string }>();

const open = ref(false);
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
        const params = new URLSearchParams();
        if (q !== undefined) {
            params.set('q', q);
        }
        const response = await fetch(`/items/${props.itemId}/image-search?${params}`, {
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
        `/items/${props.itemId}/images/from-search`,
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
            <button type="button" class="btn-pill" data-test="image-search">
                <ImagePlus :size="14" />
                Find image
            </button>
        </DialogTrigger>
        <DialogContent class="sm:max-w-2xl">
            <DialogHeader>
                <DialogTitle>Find an image</DialogTitle>
                <DialogDescription>Pick one or more images{{ itemName ? ` for "${itemName}"` : '' }}. They're downloaded and attached to the item.</DialogDescription>
            </DialogHeader>

            <input
                v-model="query"
                type="search"
                class="field"
                style="width: 100%"
                placeholder="Search the web for images…"
                data-test="image-search-query"
            />

            <div class="img-results">
                <p v-if="loading" class="img-results-note">Searching…</p>
                <p v-else-if="failed" class="img-results-note">Image search is unavailable right now.</p>
                <p v-else-if="results.length === 0" class="img-results-note">No images found — try a different search.</p>
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
                    <button type="button" class="btn-ghost">Cancel</button>
                </DialogClose>
                <button type="button" class="btn-primary" :disabled="!canAttach" data-test="image-search-attach" @click="attach">
                    <ImagePlus :size="14" />
                    Attach{{ selected.length ? ` ${selected.length}` : '' }}
                </button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
