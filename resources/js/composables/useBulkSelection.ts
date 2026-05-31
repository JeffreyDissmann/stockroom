/**
 * Stockroom bulk-selection store. Survives page navigation within an Inertia
 * partial reload (so paginating the items list keeps your selection intact),
 * but resets on a full reload — selection is per-session, never persisted.
 *
 * Pages call `useBulkSelection(visibleIds)` and get the same singleton; `Esc`
 * exits Select mode, `Cmd/Ctrl-A` selects every id currently visible. The
 * page passes its visible-id list as a getter so this composable never has
 * to know about pagination.
 */
import { computed, readonly, ref } from 'vue';

const isSelectMode = ref(false);
const selectedIds = ref<Set<number>>(new Set());

// Pages register their visible-id list here. Only the most recent
// registration wins — useful because the navigation flow is "page A leaves,
// page B mounts and re-registers". `Cmd-A` reads through this getter at
// keypress time so the latest registration is always what gets selected.
let visibleIdsProvider: (() => number[]) | null = null;

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

// Singleton keydown listener — wired exactly once for the lifetime of the
// SPA, NOT per component. The action bar, the row collection, the toggle
// button and the page itself all call useBulkSelection(); a per-call
// addEventListener registration would dispatch Esc/Cmd-A four times.
let listenerWired = false;

function wireKeydownListenerOnce() {
    if (listenerWired || typeof window === 'undefined') return;
    listenerWired = true;

    window.addEventListener('keydown', (event: KeyboardEvent) => {
        if (event.key === 'Escape' && isSelectMode.value) {
            exitMode();
            return;
        }

        // Cmd-A / Ctrl-A inside select mode only — outside it the browser's
        // native "select all text" stays intact.
        const isCmdA = (event.metaKey || event.ctrlKey) && event.key === 'a';
        if (isCmdA && isSelectMode.value && visibleIdsProvider) {
            event.preventDefault();
            selectAll(visibleIdsProvider());
        }
    });
}

export function useBulkSelection(visibleIds?: () => number[]) {
    if (visibleIds) {
        visibleIdsProvider = visibleIds;
    }
    wireKeydownListenerOnce();

    const count = computed(() => selectedIds.value.size);
    const ids = computed(() => Array.from(selectedIds.value));

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
