<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import type { ItemSummary } from '@/types';
import { router, usePage } from '@inertiajs/vue3';
import { watchDebounced } from '@vueuse/core';
import { Check, CornerUpRight } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface MoveTarget {
    id: number;
    name: string;
    path: string;
}

const props = defineProps<{ item: ItemSummary }>();

const open = ref(false);
const query = ref('');
const includeAll = ref(false);
const targets = ref<MoveTarget[]>([]);
const loading = ref(false);
const processing = ref(false);
const selectedId = ref<number | null>(props.item.parent_id ?? null);

const page = usePage();
const error = computed(() => (page.props.errors as Record<string, string> | undefined)?.parent_id);

// Block the submit until the user picks somewhere different from where it lives now.
const unchanged = computed(() => selectedId.value === (props.item.parent_id ?? null));

async function fetchTargets(): Promise<void> {
    loading.value = true;
    try {
        const params = new URLSearchParams({ q: query.value });
        if (includeAll.value) {
            params.set('all', '1');
        }
        const response = await fetch(`/items/${props.item.id}/move-targets?${params}`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        targets.value = (await response.json()).targets ?? [];
    } finally {
        loading.value = false;
    }
}

watch(open, (isOpen) => {
    if (isOpen) {
        query.value = '';
        includeAll.value = false;
        selectedId.value = props.item.parent_id ?? null;
        fetchTargets();
    }
});

watchDebounced(query, () => fetchTargets(), { debounce: 250 });
watch(includeAll, () => fetchTargets());

function move() {
    processing.value = true;
    router.patch(
        `/items/${props.item.id}/move`,
        { parent_id: selectedId.value },
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
            <button type="button" class="btn-pill" data-test="move-item">
                <CornerUpRight :size="14" />
                Move
            </button>
        </DialogTrigger>
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Move "{{ item.name }}"</DialogTitle>
                <DialogDescription>Search for where this item should live. Its contents move with it.</DialogDescription>
            </DialogHeader>

            <input
                v-model="query"
                type="search"
                class="field"
                style="width: 100%"
                placeholder="Search rooms and containers…"
                autofocus
                data-test="move-search"
            />

            <ul class="move-list">
                <li>
                    <button type="button" class="move-row" :class="selectedId === null ? 'is-selected' : ''" @click="selectedId = null">
                        <span class="grow">— Top level —</span>
                        <Check v-if="selectedId === null" :size="14" />
                    </button>
                </li>
                <li v-for="target in targets" :key="target.id">
                    <button
                        type="button"
                        class="move-row"
                        :class="selectedId === target.id ? 'is-selected' : ''"
                        data-test="move-target"
                        @click="selectedId = target.id"
                    >
                        <span class="move-name">{{ target.name }}</span>
                        <span v-if="target.path" class="move-path truncate">{{ target.path }}</span>
                        <Check v-if="selectedId === target.id" :size="14" class="ml-auto shrink-0" />
                    </button>
                </li>
                <li v-if="!loading && targets.length === 0 && query" class="move-empty">No matching {{ includeAll ? 'items' : 'locations' }}.</li>
            </ul>

            <label class="flex items-center gap-2 text-sm" style="color: var(--fg-muted)">
                <input v-model="includeAll" type="checkbox" data-test="move-include-all" />
                Search all items, not just rooms &amp; containers
            </label>

            <InputError :message="error" />

            <DialogFooter>
                <DialogClose as-child>
                    <button type="button" class="btn-ghost">Cancel</button>
                </DialogClose>
                <button type="button" class="btn-primary" :disabled="processing || unchanged" @click="move">
                    <CornerUpRight :size="14" />
                    Move here
                </button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

<style scoped>
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
.move-row .grow {
    flex: 1;
    min-width: 0;
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
</style>
