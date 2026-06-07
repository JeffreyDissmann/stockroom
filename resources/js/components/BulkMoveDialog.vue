<script setup lang="ts">
/**
 * Move-target picker for the bulk action bar. Mirrors MoveItemDialog's
 * search-as-you-type pattern but isn't scoped to a specific item, so it
 * never excludes anything and never offers "include all items" — bulk
 * targets are always rooms or containers.
 */
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { trans } from '@/composables/useTranslations';
import { router } from '@inertiajs/vue3';
import { watchDebounced } from '@vueuse/core';
import { Check, CornerUpRight } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const props = defineProps<{
    count: number;
    // Any item id from the current selection — used to filter the
    // server-side picker so we don't offer "move into yourself". One id
    // is enough for v1; descendant-exclusion of the others is a follow-up.
    excludingId: number;
}>();

const emit = defineEmits<{
    move: [parentId: number | null];
    close: [];
}>();

interface Target {
    id: number;
    name: string;
    path: string;
}

const open = ref(true);
const query = ref('');
const targets = ref<Target[]>([]);
const loading = ref(false);
const selectedId = ref<number | null>(null);

async function fetchTargets() {
    loading.value = true;
    try {
        // Piggy-back on the existing items.move-targets endpoint scoped
        // to one selected item — server already filters to rooms +
        // containers and excludes the item + its descendants.
        const url = `/items/${props.excludingId}/move-targets?q=${encodeURIComponent(query.value)}`;
        const response = await fetch(url, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        if (!response.ok) {
            targets.value = [];
            return;
        }
        targets.value = (await response.json()).targets ?? [];
    } finally {
        loading.value = false;
    }
}

watch(open, (isOpen) => {
    if (!isOpen) emit('close');
});

watchDebounced(query, () => fetchTargets(), { debounce: 250 });

fetchTargets();

function submit() {
    emit('move', selectedId.value);
}

// `void router` so unused-imports doesn't strip the auto-import.
void router;
void props;
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ $t('items.bulk.move_title') }}</DialogTitle>
                <DialogDescription>{{ $tChoice('items.bulk.move_description', count) }}</DialogDescription>
            </DialogHeader>

            <div class="form-row">
                <input v-model="query" type="search" class="field" :placeholder="trans('items.move.search')" autofocus data-test="bulk-move-search" />
            </div>

            <ul class="bulk-move-list" data-test="bulk-move-list">
                <li>
                    <button
                        type="button"
                        class="bulk-move-option"
                        :class="{ 'bulk-move-option--on': selectedId === null }"
                        data-test="bulk-move-top"
                        @click="selectedId = null"
                    >
                        <Check v-if="selectedId === null" :size="14" />
                        <span>{{ $t('items.form.top_level') }}</span>
                    </button>
                </li>
                <li v-if="loading" class="bulk-move-status">{{ $t('common.loading') }}</li>
                <li v-else-if="targets.length === 0" class="bulk-move-status">{{ $t('items.move.no_match_locations') }}</li>
                <li v-for="target in targets" v-else :key="target.id">
                    <button
                        type="button"
                        class="bulk-move-option"
                        :class="{ 'bulk-move-option--on': selectedId === target.id }"
                        :data-test="`bulk-move-option-${target.id}`"
                        @click="selectedId = target.id"
                    >
                        <Check v-if="selectedId === target.id" :size="14" />
                        <span
                            >{{ target.name }}<span v-if="target.path" class="bulk-move-path"> · {{ target.path }}</span></span
                        >
                    </button>
                </li>
            </ul>

            <DialogFooter>
                <DialogClose as-child>
                    <button type="button" class="btn-ghost">{{ $t('common.cancel') }}</button>
                </DialogClose>
                <button type="button" class="btn-primary" data-test="bulk-move-submit" @click="submit">
                    <CornerUpRight :size="14" />
                    {{ $t('items.move.submit') }}
                </button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

<style scoped>
.bulk-move-list {
    list-style: none;
    margin: 0;
    padding: 0;
    max-height: 280px;
    overflow-y: auto;
    border: 1px solid var(--border);
    border-radius: var(--radius);
}
.bulk-move-option {
    width: 100%;
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    background: transparent;
    border: 0;
    cursor: pointer;
    font-size: 13px;
    color: var(--fg);
    text-align: left;
}
.bulk-move-option:hover {
    background: var(--bg-hover);
}
.bulk-move-option--on {
    background: color-mix(in srgb, var(--accent) 8%, transparent);
}
.bulk-move-path {
    color: var(--fg-muted);
    margin-left: 4px;
}
.bulk-move-status {
    padding: 12px;
    text-align: center;
    color: var(--fg-muted);
    font-size: 13px;
}
</style>
