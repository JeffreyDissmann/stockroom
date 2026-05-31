/**
 * Stockroom bulk-selection store. Survives page navigation within an Inertia
 * partial reload (so paginating the items list keeps your selection intact),
 * but resets on a full reload — selection is per-session, never persisted.
 *
 * Pages call `useBulkSelection()` and get the same singleton; `Esc` exits
 * Select mode, `Cmd/Ctrl-A` selects every id currently visible (the page
 * passes that list explicitly so this composable never has to know about
 * pagination).
 */
import { computed, onMounted, onUnmounted, readonly, ref } from 'vue';

const isSelectMode = ref(false);
const selectedIds = ref<Set<number>>(new Set());

function toggleMode() {
    isSelectMode.value = !isSelectMode.value;
    if (!isSelectMode.value) {
        selectedIds.value = new Set();
    }
}

function exitMode() {
    isSelectMode.value = false;
    selectedIds.value = new Set();
}

function toggleId(id: number) {
    const next = new Set(selectedIds.value);
    if (next.has(id)) {
        next.delete(id);
    } else {
        next.add(id);
    }
    selectedIds.value = next;
}

function isSelected(id: number): boolean {
    return selectedIds.value.has(id);
}

function selectAll(ids: number[]) {
    selectedIds.value = new Set(ids);
}

function clear() {
    selectedIds.value = new Set();
}

/**
 * Composable returns the same singleton state across all pages that import
 * it, plus a count helper and a way to register the on-page id list (used
 * by the Cmd-A handler so we don't need a separate prop pipeline).
 */
export function useBulkSelection(visibleIds?: () => number[]) {
    const count = computed(() => selectedIds.value.size);
    const ids = computed(() => Array.from(selectedIds.value));

    function onKeydown(event: KeyboardEvent) {
        if (event.key === 'Escape' && isSelectMode.value) {
            exitMode();
            return;
        }

        // Cmd-A / Ctrl-A inside select mode only — outside it the browser's
        // native "select all text" remains untouched.
        const isCmdA = (event.metaKey || event.ctrlKey) && event.key === 'a';
        if (isCmdA && isSelectMode.value && visibleIds) {
            event.preventDefault();
            selectAll(visibleIds());
        }
    }

    onMounted(() => window.addEventListener('keydown', onKeydown));
    onUnmounted(() => window.removeEventListener('keydown', onKeydown));

    return {
        isSelectMode: readonly(isSelectMode),
        selectedIds: readonly(selectedIds),
        count,
        ids,
        toggleMode,
        exitMode,
        toggleId,
        isSelected,
        selectAll,
        clear,
    };
}
