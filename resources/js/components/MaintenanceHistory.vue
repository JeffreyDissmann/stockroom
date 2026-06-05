<script setup lang="ts">
import AdHocEntryDialog from '@/components/AdHocEntryDialog.vue';
import { useCurrency } from '@/composables/useCurrency';
import { useDateFormat } from '@/composables/useDateFormat';
import { trans } from '@/composables/useTranslations';
import maintenanceEntryRoutes from '@/routes/items/maintenance-entries';
import type { ItemSummary, MaintenanceEntryRow } from '@/types';
import { router } from '@inertiajs/vue3';
import { Trash2 } from 'lucide-vue-next';

/**
 * The maintenance/repair history of an item: task completions and ad-hoc
 * entries, newest first. Sits beside the Activity feed on wide screens —
 * both are append-mostly audit trails of the item.
 */
const props = defineProps<{
    item: ItemSummary;
    entries: MaintenanceEntryRow[];
}>();

const { format: fmtMoney } = useCurrency();
const { formatDate: fmtDate } = useDateFormat();

function deleteEntry(entry: MaintenanceEntryRow) {
    if (!confirm(trans('maintenance.delete_entry_confirm'))) return;
    router.delete(maintenanceEntryRoutes.destroy([props.item.id, entry.id]).url, { preserveScroll: true });
}
</script>

<template>
    <section data-test="maintenance-history-section">
        <div class="mb-3 flex items-center justify-between gap-3">
            <h3 class="section-label" style="margin: 0">{{ $t('maintenance.history_title') }}</h3>
            <AdHocEntryDialog :item="item" />
        </div>

        <p v-if="entries.length === 0" class="mnt-history-empty">{{ $t('maintenance.history_empty') }}</p>
        <ul v-else class="mnt-history card">
            <li v-for="entry in entries" :key="entry.id" class="mnt-history-row" data-test="maintenance-entry-row">
                <span class="mnt-history-date">{{ fmtDate(entry.completed_at) }}</span>
                <span class="mnt-history-main">
                    <!-- Task completions: bold title + muted notes. Ad-hoc
                         entries: the notes ARE the title — no generic
                         prefix in front of them. -->
                    <template v-if="entry.task_title">
                        <span class="mnt-history-task">{{ entry.task_title }}</span>
                        <span v-if="entry.notes" class="mnt-history-notes">{{ entry.notes }}</span>
                    </template>
                    <span v-else class="mnt-history-task">{{ entry.notes ?? $t('maintenance.one_time') }}</span>
                </span>
                <span v-if="fmtMoney(entry.cost)" class="mnt-history-cost mono">{{ fmtMoney(entry.cost) }}</span>
                <span v-if="entry.performed_by_name" class="mnt-history-by">
                    {{ $t('maintenance.by_name', { name: entry.performed_by_name }) }}
                </span>
                <button
                    type="button"
                    class="btn-ghost mnt-history-delete"
                    data-test="maintenance-entry-delete"
                    :aria-label="$t('common.delete')"
                    @click="deleteEntry(entry)"
                >
                    <Trash2 :size="13" />
                </button>
            </li>
        </ul>
    </section>
</template>

<style scoped>
.mnt-history-empty {
    margin: 0;
    padding: 16px;
    text-align: center;
    color: var(--fg-muted);
    font-size: 13px;
    border: 1px dashed var(--border);
    border-radius: var(--radius-sm);
}
/* Rows live in a .card so the block mirrors the Activity feed next to it. */
.mnt-history {
    list-style: none;
    padding: 0;
    margin: 0;
    display: flex;
    flex-direction: column;
}
.mnt-history-row {
    display: flex;
    align-items: baseline;
    gap: 10px;
    padding: 10px 14px;
    font-size: 13px;
    border-top: 1px solid var(--border);
}
.mnt-history-row:first-child {
    border-top: 0;
}
.mnt-history-date {
    flex-shrink: 0;
    width: 86px;
    color: var(--fg-subtle);
    font-size: 12px;
}
.mnt-history-main {
    flex: 1;
    min-width: 0;
    display: flex;
    gap: 8px;
    align-items: baseline;
    flex-wrap: wrap;
}
.mnt-history-task {
    font-weight: 500;
}
.mnt-history-notes {
    color: var(--fg-muted);
}
.mnt-history-cost {
    flex-shrink: 0;
    color: var(--fg-muted);
    font-size: 12.5px;
}
.mnt-history-by {
    flex-shrink: 0;
    color: var(--fg-subtle);
    font-size: 12px;
}
.mnt-history-delete {
    padding: 2px 5px;
    color: var(--fg-subtle);
    align-self: center;
}
.mnt-history-delete:hover {
    color: var(--neg);
}
.mono {
    font-family: var(--font-mono, monospace);
}
</style>
