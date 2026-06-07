<script setup lang="ts">
import InputError from '@/components/InputError.vue';
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
import paperlessLinksRoutes from '@/routes/items/paperless-links';
import paperlessDocumentsRoutes from '@/routes/paperless/documents';
import type { ItemSummary, SharedData } from '@/types';
import { router, usePage } from '@inertiajs/vue3';
import { watchDebounced } from '@vueuse/core';
import { Check, FileText } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface DocumentResult {
    id: number;
    title: string;
}

const props = defineProps<{
    item: ItemSummary;
}>();

// Free-text search is admin-only (Paperless per-user permissions aren't
// mirrored, so search over the service token stays with household admins);
// ids and pasted URLs work for every member.
const isAdmin = usePage<SharedData>().props.auth.user.is_admin;

const open = ref(false);
const query = ref('');
const results = ref<DocumentResult[]>([]);
const searchState = ref<'idle' | 'searching' | 'done' | 'error'>('idle');
const processing = ref(false);
const selectedId = ref<number | null>(null);

const page = usePage();
const error = computed(() => (page.props.errors as Record<string, string> | undefined)?.document);

// Mirrors PaperlessLinker::parseDocumentReference server-side: a bare id or
// any /documents/{id} URL links directly, skipping the search.
const directDocumentId = computed<number | null>(() => {
    const raw = query.value.trim();
    if (/^\d+$/.test(raw)) return Number(raw);
    const match = raw.match(/\/documents\/(\d+)\/?/);
    return match ? Number(match[1]) : null;
});

watch(open, (isOpen) => {
    if (isOpen) {
        query.value = '';
        results.value = [];
        searchState.value = 'idle';
        selectedId.value = null;
    }
});

watchDebounced(
    query,
    () => {
        results.value = [];
        searchState.value = 'idle';
        selectedId.value = directDocumentId.value;

        if (!isAdmin || directDocumentId.value !== null || query.value.trim().length < 2) return;

        searchDocuments();
    },
    { debounce: 250 },
);

async function searchDocuments(): Promise<void> {
    searchState.value = 'searching';
    try {
        const response = await fetch(paperlessDocumentsRoutes.search({ query: { q: query.value.trim() } }).url, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        if (!response.ok) throw new Error(String(response.status));
        results.value = ((await response.json()) as { documents: DocumentResult[] }).documents;
        searchState.value = 'done';
    } catch {
        // Covers both an unreachable Paperless (502) and a network failure —
        // the list says so instead of silently rendering empty.
        searchState.value = 'error';
    }
}

function link() {
    if (selectedId.value === null || processing.value) return;
    processing.value = true;
    router.post(
        paperlessLinksRoutes.store(props.item.id).url,
        { document: String(selectedId.value) },
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
            <button type="button" class="btn-pill" data-test="paperless-add">
                <FileText :size="14" />
                {{ $t('items.paperless.add_trigger') }}
            </button>
        </DialogTrigger>
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ $t('items.paperless.add_title', { name: item.name }) }}</DialogTitle>
                <DialogDescription>{{ $t(isAdmin ? 'items.paperless.hint_search' : 'items.paperless.hint_id') }}</DialogDescription>
            </DialogHeader>

            <input
                v-model="query"
                type="search"
                class="field"
                style="width: 100%"
                :placeholder="$t(isAdmin ? 'items.paperless.add_placeholder_search' : 'items.paperless.add_placeholder_id')"
                autofocus
                data-test="paperless-add-input"
            />

            <ul class="move-list">
                <li v-if="directDocumentId !== null">
                    <button
                        type="button"
                        class="move-row"
                        :class="selectedId === directDocumentId ? 'is-selected' : ''"
                        data-test="paperless-add-direct"
                        @click="selectedId = directDocumentId"
                    >
                        <span class="move-name">{{ $t('items.paperless.link_document', { id: directDocumentId }) }}</span>
                        <Check v-if="selectedId === directDocumentId" :size="14" class="ml-auto shrink-0" />
                    </button>
                </li>
                <li v-for="doc in results" :key="doc.id">
                    <button
                        type="button"
                        class="move-row"
                        :class="selectedId === doc.id ? 'is-selected' : ''"
                        :data-test="`paperless-add-result-${doc.id}`"
                        @click="selectedId = doc.id"
                    >
                        <span class="move-name truncate">{{ doc.title }}</span>
                        <span class="move-path">#{{ doc.id }}</span>
                        <Check v-if="selectedId === doc.id" :size="14" class="ml-auto shrink-0" />
                    </button>
                </li>
                <li v-if="directDocumentId === null && results.length === 0" class="move-empty">
                    {{
                        searchState === 'error'
                            ? $t('items.paperless.search_unreachable')
                            : searchState === 'done'
                              ? $t('items.paperless.search_empty')
                              : $t(isAdmin ? 'items.paperless.hint_search' : 'items.paperless.hint_id')
                    }}
                </li>
            </ul>

            <InputError :message="error" />

            <DialogFooter>
                <DialogClose as-child>
                    <button type="button" class="btn-ghost">{{ $t('common.cancel') }}</button>
                </DialogClose>
                <button
                    type="button"
                    class="btn-primary"
                    :disabled="processing || selectedId === null"
                    data-test="paperless-add-submit"
                    @click="link"
                >
                    <FileText :size="14" />
                    {{ $t('items.paperless.add_trigger') }}
                </button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

<style scoped>
/* Mirrors LinkRelatedItemDialog (which mirrors MoveItemDialog) — all three
   are a search-and-pick list, and styling them the same way keeps the
   muscle memory consistent. */
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
    color: var(--fg-subtle);
    font-size: 12px;
    flex-shrink: 0;
}
.move-empty {
    padding: 12px;
    text-align: center;
    color: var(--fg-muted);
    font-size: 13px;
}
.truncate {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    flex: 1;
    min-width: 0;
}
</style>
