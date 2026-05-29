<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import itemRoutes from '@/routes/items';
import relatedItems from '@/routes/items/related-items';
import type { ItemSummary } from '@/types';
import { router, usePage } from '@inertiajs/vue3';
import { watchDebounced } from '@vueuse/core';
import { Check, Link2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface RelatedTarget {
    id: number;
    name: string;
    path: string;
}

const props = defineProps<{
    item: ItemSummary;
    // Tailwind class on the inline trigger button — lets the parent hide it
    // on a given breakpoint so the action can also live behind the mobile
    // More dropdown via openDialog() below.
    triggerClass?: string;
}>();

const open = ref(false);
const query = ref('');
const targets = ref<RelatedTarget[]>([]);
const loading = ref(false);
const processing = ref(false);
const selectedId = ref<number | null>(null);

const page = usePage();
const error = computed(() => (page.props.errors as Record<string, string> | undefined)?.related_item_id);

defineExpose({
    openDialog: () => {
        open.value = true;
    },
});

async function fetchTargets(): Promise<void> {
    loading.value = true;
    try {
        const response = await fetch(itemRoutes.relatedItemTargets(props.item.id, { query: { q: query.value } }).url, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        targets.value = (await response.json()).targets ?? [];
    } finally {
        loading.value = false;
    }
}

// Open: just reset state — don't pre-load every item in the household. The
// candidate list can be hundreds for a real inventory; user types a few
// letters to filter down. Empty list state below prompts for input.
watch(open, (isOpen) => {
    if (isOpen) {
        query.value = '';
        targets.value = [];
        selectedId.value = null;
    }
});

watchDebounced(
    query,
    () => {
        if (query.value.trim() === '') {
            targets.value = [];
            return;
        }
        fetchTargets();
    },
    { debounce: 250 },
);

function link() {
    if (selectedId.value === null || processing.value) return;
    processing.value = true;
    router.post(
        relatedItems.store(props.item.id).url,
        { related_item_id: selectedId.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                open.value = false;
            },
            onFinish: () => {
                processing.value = false;
            },
        },
    );
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <button type="button" :class="['btn-pill', triggerClass]" data-test="link-related">
                <Link2 :size="14" />
                {{ $t('items.related.trigger') }}
            </button>
        </DialogTrigger>
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ $t('items.related.title', { name: item.name }) }}</DialogTitle>
                <DialogDescription>{{ $t('items.related.description') }}</DialogDescription>
            </DialogHeader>

            <input
                v-model="query"
                type="search"
                class="field"
                style="width: 100%"
                :placeholder="$t('items.related.search')"
                autofocus
                data-test="link-related-search"
            />

            <ul class="move-list">
                <li v-for="target in targets" :key="target.id">
                    <button
                        type="button"
                        class="move-row"
                        :class="selectedId === target.id ? 'is-selected' : ''"
                        data-test="link-related-target"
                        @click="selectedId = target.id"
                    >
                        <span class="move-name">{{ target.name }}</span>
                        <span v-if="target.path" class="move-path truncate">{{ target.path }}</span>
                        <Check v-if="selectedId === target.id" :size="14" class="ml-auto shrink-0" />
                    </button>
                </li>
                <li v-if="!loading && targets.length === 0" class="move-empty">
                    {{ query.trim() === '' ? $t('items.related.type_to_search') : $t('items.related.no_match') }}
                </li>
            </ul>

            <InputError :message="error" />

            <DialogFooter>
                <DialogClose as-child>
                    <button type="button" class="btn-ghost">{{ $t('common.cancel') }}</button>
                </DialogClose>
                <button type="button" class="btn-primary" :disabled="processing || selectedId === null" data-test="link-related-submit" @click="link">
                    <Link2 :size="14" />
                    {{ $t('items.related.submit') }}
                </button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

<style scoped>
/* Mirrors MoveItemDialog's styling — both dialogs are a search-and-pick
   list, and styling them the same way keeps the muscle memory consistent
   for users who already know the Move dialog. */
.move-list {
    max-height: 280px;
    overflow-y: auto;
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
}
.move-row {
    display: flex;
    align-items: center;
    gap: 8px;
    width: 100%;
    text-align: left;
    padding: 8px 10px;
    font-size: 13px;
    background: transparent;
    border: 0;
    cursor: pointer;
    color: inherit;
}
.move-row:hover {
    background: var(--bg-sunken);
}
.move-row.is-selected {
    background: var(--bg-sunken);
    font-weight: 500;
}
.move-list li + li .move-row {
    border-top: 1px solid var(--border);
}
.move-name {
    font-weight: 500;
    white-space: nowrap;
}
.move-path {
    flex: 1;
    min-width: 0;
    color: var(--fg-subtle);
    font-size: 12px;
}
.move-empty {
    padding: 12px;
    text-align: center;
    color: var(--fg-muted);
    font-size: 13px;
}
.truncate { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
</style>
