<script setup lang="ts">
/**
 * Sticky bottom-of-screen action bar that appears when ≥1 item is
 * selected via the bulk-select store.
 *
 * Operations:
 *   - Delete: confirm dialog, POST to /items/bulk.
 *   - Move: opens BulkMoveDialog (picker), POST to /items/bulk.
 *   - Add tag / Remove tag: opens BulkTagDialog (chip picker), POST.
 *
 * The undo toast for Move is rendered here too — after the redirect
 * lands a `flash.bulk_result` carrying `previous: { [itemId]: parentId }`,
 * a 6s toast offers Undo which POSTs a reverse move composed from that
 * map.
 */
import BulkMoveDialog from '@/components/BulkMoveDialog.vue';
import BulkTagDialog from '@/components/BulkTagDialog.vue';
import { trans } from '@/composables/useTranslations';
import { useBulkSelection } from '@/composables/useBulkSelection';
import type { SharedData, TagSummary } from '@/types';
import { router, usePage } from '@inertiajs/vue3';
import { ArrowLeftRight, Tag, Trash2, X } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';

defineProps<{ tags: TagSummary[] }>();

const bulk = useBulkSelection();
const page = usePage<SharedData & { flash: { bulk_result?: BulkResult } }>();

interface BulkResult {
    action: 'delete' | 'move' | 'attach-tag' | 'detach-tag';
    count: number;
    parent_id?: number | null;
    previous?: Record<number, number | null>;
    tag_id?: number;
}

const moveOpen = ref(false);
const tagOpen = ref<null | 'attach' | 'detach'>(null);

// Undo toast state. Surfaces after a Move; sticks around 6s.
const undoMap = ref<Record<number, number | null> | null>(null);
let undoTimer: ReturnType<typeof setTimeout> | undefined;

watch(
    () => page.props.flash?.bulk_result,
    (result) => {
        if (result?.action === 'move' && result.previous) {
            undoMap.value = result.previous;
            clearTimeout(undoTimer);
            undoTimer = setTimeout(() => (undoMap.value = null), 6000);
        }
    },
);

// Tear down the pending timer if the bar unmounts mid-flight (e.g. the
// user exits Select mode while the toast is still on screen) — otherwise
// the setTimeout callback fires on a torn-down component and tries to
// write to a stale ref.
onBeforeUnmount(() => clearTimeout(undoTimer));

const count = computed(() => bulk.count.value);

function confirmDelete() {
    if (!confirm(trans('items.bulk.delete_confirm', { count: count.value }))) return;
    router.post(
        '/items/bulk',
        { action: 'delete', ids: bulk.ids.value },
        {
            preserveScroll: true,
            onSuccess: () => bulk.exitMode(),
        },
    );
}

function applyMove(parentId: number | null) {
    if (!confirm(trans('items.bulk.move_confirm', { count: count.value }))) return;
    router.post(
        '/items/bulk',
        { action: 'move', ids: bulk.ids.value, parent_id: parentId },
        {
            preserveScroll: true,
            onSuccess: () => {
                moveOpen.value = false;
                bulk.exitMode();
            },
        },
    );
}

function applyTag(direction: 'attach' | 'detach', tagId: number) {
    router.post(
        '/items/bulk',
        { action: direction === 'attach' ? 'attach-tag' : 'detach-tag', ids: bulk.ids.value, tag_id: tagId },
        {
            preserveScroll: true,
            onSuccess: () => {
                tagOpen.value = null;
                bulk.exitMode();
            },
        },
    );
}

function performUndo() {
    if (!undoMap.value) return;
    // The reverse op is a series of single moves rather than a bulk move,
    // because each item may need a different parent_id. Issued sequentially
    // through Inertia so they all roll up into one Inertia history entry.
    const entries = Object.entries(undoMap.value);
    let idx = 0;
    const next = () => {
        if (idx >= entries.length) {
            undoMap.value = null;
            return;
        }
        const [idStr, parentId] = entries[idx++];
        router.patch(
            `/items/${idStr}/move`,
            { parent_id: parentId },
            { preserveScroll: true, onFinish: next },
        );
    };
    next();
}
</script>

<template>
    <div v-if="count > 0" class="bulk-bar" data-test="bulk-action-bar">
        <div class="bulk-bar__count">
            <span data-test="bulk-count">{{ $tChoice('items.bulk.count', count) }}</span>
        </div>
        <div class="bulk-bar__actions">
            <button type="button" class="btn-pill" data-test="bulk-move" @click="moveOpen = true">
                <ArrowLeftRight :size="14" />
                {{ $t('items.bulk.move') }}
            </button>
            <button type="button" class="btn-pill" data-test="bulk-attach-tag" @click="tagOpen = 'attach'">
                <Tag :size="14" />
                {{ $t('items.bulk.attach_tag') }}
            </button>
            <button type="button" class="btn-pill" data-test="bulk-detach-tag" @click="tagOpen = 'detach'">
                <Tag :size="14" />
                {{ $t('items.bulk.detach_tag') }}
            </button>
            <button type="button" class="btn-pill bulk-bar__danger" data-test="bulk-delete" @click="confirmDelete">
                <Trash2 :size="14" />
                {{ $t('items.bulk.delete') }}
            </button>
            <button type="button" class="btn-ghost" data-test="bulk-clear" @click="bulk.exitMode">
                <X :size="14" />
                <span class="sr-only">{{ $t('common.cancel') }}</span>
            </button>
        </div>
    </div>

    <BulkMoveDialog
        v-if="moveOpen"
        :count="count"
        :excluding-id="bulk.ids.value[0]"
        @move="applyMove"
        @close="moveOpen = false"
    />
    <BulkTagDialog
        v-if="tagOpen"
        :tags="tags"
        :direction="tagOpen"
        :count="count"
        @apply="(id) => applyTag(tagOpen!, id)"
        @close="tagOpen = null"
    />

    <Transition name="undo">
        <div v-if="undoMap" class="undo-toast" data-test="bulk-undo-toast">
            <span>{{ $tChoice('items.bulk.moved_count', Object.keys(undoMap).length) }}</span>
            <button type="button" class="undo-toast__btn" data-test="bulk-undo" @click="performUndo">
                {{ $t('items.bulk.undo') }}
            </button>
        </div>
    </Transition>
</template>

<style scoped>
/* Rectangular rounded card rather than a full pill, because the pill
   shape only reads well when the bar fits one row — and on narrow
   viewports it always wraps. Letting it look like a card on wrap is
   cleaner than a multi-row "round blob". */
.bulk-bar {
    position: fixed;
    bottom: 16px;
    left: 50%;
    transform: translateX(-50%);
    z-index: 30;
    display: flex;
    gap: 12px;
    align-items: center;
    padding: 10px 14px;
    border-radius: var(--radius-lg);
    background: var(--bg-elev);
    border: 1px solid var(--border);
    box-shadow: var(--shadow-lg);
    max-width: calc(100vw - 32px);
    flex-wrap: wrap;
    justify-content: center;
}
.bulk-bar__count {
    font-size: 13px;
    color: var(--fg);
    padding-right: 8px;
    border-right: 1px solid var(--border);
    /* Don't shrink below content width — when the actions wrap below,
       the count stays a stable header rather than getting visually
       merged into the first action. */
    flex-shrink: 0;
}
.bulk-bar__actions {
    display: flex;
    gap: 8px;
    align-items: center;
    flex-wrap: wrap;
    justify-content: center;
}

/* Mobile: full-width edge-to-edge bar so the action buttons get a real
   row of horizontal space instead of wrapping into a near-square blob.
   The pill-bottom rounding stays so it still reads as floating, not
   docked. */
@media (max-width: 640px) {
    .bulk-bar {
        left: 12px;
        right: 12px;
        transform: none;
        max-width: none;
        padding: 12px;
    }
    .bulk-bar__count {
        width: 100%;
        padding-right: 0;
        padding-bottom: 8px;
        border-right: 0;
        border-bottom: 1px solid var(--border);
        text-align: center;
    }
    .bulk-bar__actions {
        width: 100%;
        justify-content: space-between;
    }
}
.bulk-bar__danger {
    color: var(--neg);
}
.bulk-bar__danger:hover {
    background: color-mix(in srgb, var(--neg) 10%, transparent);
}

.undo-toast {
    position: fixed;
    bottom: 84px;
    left: 50%;
    transform: translateX(-50%);
    z-index: 40;
    display: inline-flex;
    align-items: center;
    gap: 12px;
    padding: 8px 16px;
    border-radius: 999px;
    background: var(--fg);
    color: var(--bg);
    font-size: 13px;
    box-shadow: var(--shadow-md);
}
.undo-toast__btn {
    background: transparent;
    border: 0;
    color: var(--bg);
    font-weight: 600;
    cursor: pointer;
    text-decoration: underline;
    text-underline-offset: 3px;
}
.undo-enter-from,
.undo-leave-to {
    opacity: 0;
    transform: translate(-50%, 10px);
}
.undo-enter-active,
.undo-leave-active {
    transition: opacity 0.2s ease, transform 0.2s ease;
}
</style>
