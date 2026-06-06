<script setup lang="ts">
import MaintenanceTaskDialog from '@/components/MaintenanceTaskDialog.vue';
import MarkMaintenanceDoneDialog from '@/components/MarkMaintenanceDoneDialog.vue';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { useDateFormat } from '@/composables/useDateFormat';
import { trans } from '@/composables/useTranslations';
import maintenanceTaskRoutes from '@/routes/items/maintenance-tasks';
import type { ItemSummary, MaintenanceTaskRow, SharedData } from '@/types';
import { router, usePage } from '@inertiajs/vue3';
import { Check, FastForward, MoreVertical, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

/**
 * The maintenance schedules of an item, full width. The history lives in
 * its own MaintenanceHistory component (paired with the Activity feed).
 */
const props = defineProps<{
    item: ItemSummary;
    tasks: MaintenanceTaskRow[];
}>();

const page = usePage<SharedData>();
const { formatDate: fmtDate } = useDateFormat();

// Stale-page guard errors from complete/skip (ValidationException key
// 'task') — shown as a banner above the list since they belong to no form.
const taskError = computed(() => (page.props.errors as Record<string, string> | undefined)?.task);

// One dialog instance each, re-targeted per task card.
const taskDialogOpen = ref(false);
const editingTask = ref<MaintenanceTaskRow | null>(null);
const doneDialogOpen = ref(false);
const doneTask = ref<MaintenanceTaskRow | null>(null);

function openCreate() {
    editingTask.value = null;
    taskDialogOpen.value = true;
}

function openEdit(task: MaintenanceTaskRow) {
    editingTask.value = task;
    taskDialogOpen.value = true;
}

function openMarkDone(task: MaintenanceTaskRow) {
    doneTask.value = task;
    doneDialogOpen.value = true;
}

function skipTask(task: MaintenanceTaskRow) {
    router.post(maintenanceTaskRoutes.skip([props.item.id, task.id]).url, {}, { preserveScroll: true });
}

function deleteTask(task: MaintenanceTaskRow) {
    if (!confirm(trans('maintenance.delete_task_confirm', { title: task.title }))) return;
    router.delete(maintenanceTaskRoutes.destroy([props.item.id, task.id]).url, { preserveScroll: true });
}
</script>

<template>
    <section class="mt-8" data-test="maintenance-section">
        <div class="mb-3 flex items-center justify-between gap-3">
            <h3 class="section-label" style="margin: 0">{{ $t('maintenance.section_title') }}</h3>
            <button type="button" class="btn-pill" data-test="maintenance-task-add" @click="openCreate">
                <Plus :size="14" />
                {{ $t('maintenance.add_task') }}
            </button>
        </div>

        <p v-if="taskError" class="mnt-error" role="alert">{{ taskError }}</p>

        <div v-if="tasks.length === 0" class="card card-pad" style="text-align: center; color: var(--fg-muted); font-size: 13px">
            {{ $t('maintenance.empty') }}
        </div>

        <ul v-else class="mnt-list">
            <li v-for="task in tasks" :key="task.id" class="mnt-row" data-test="maintenance-task-row">
                <div class="mnt-main">
                    <span class="mnt-title">{{ task.title }}</span>
                    <span class="mnt-summary">{{ task.schedule_summary }}</span>
                    <span v-if="task.last_completed_at" class="mnt-last">
                        {{ $t('maintenance.last_done', { date: fmtDate(task.last_completed_at) }) }}
                    </span>
                </div>
                <div class="mnt-due">
                    <span
                        class="mnt-badge"
                        :class="{ 'is-overdue': task.is_overdue, 'is-due-soon': task.is_due_soon }"
                        data-test="maintenance-due-badge"
                    >
                        {{ task.due_label }}
                    </span>
                    <span v-if="task.next_due_at" class="mnt-date">{{ fmtDate(task.next_due_at) }}</span>
                </div>
                <div class="mnt-actions">
                    <button type="button" class="btn-pill" data-test="maintenance-mark-done" @click="openMarkDone(task)">
                        <Check :size="14" />
                        {{ $t('maintenance.mark_done') }}
                    </button>
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <button
                                type="button"
                                class="btn-ghost"
                                style="padding: 5px 6px"
                                data-test="maintenance-task-menu"
                                :aria-label="$t('common.more')"
                            >
                                <MoreVertical :size="15" />
                            </button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem data-test="maintenance-task-edit" @click="openEdit(task)">
                                <Pencil class="mr-2 h-4 w-4" />
                                {{ $t('common.edit') }}
                            </DropdownMenuItem>
                            <DropdownMenuItem v-if="task.can_skip" data-test="maintenance-task-skip" @click="skipTask(task)">
                                <FastForward class="mr-2 h-4 w-4" />
                                {{ $t('maintenance.skip') }}
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                                data-test="maintenance-task-delete"
                                class="text-destructive focus:text-destructive"
                                @click="deleteTask(task)"
                            >
                                <Trash2 class="mr-2 h-4 w-4" />
                                {{ $t('maintenance.delete_task') }}
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </li>
        </ul>

        <MaintenanceTaskDialog v-model:open="taskDialogOpen" :item="item" :task="editingTask" />
        <MarkMaintenanceDoneDialog v-model:open="doneDialogOpen" :item="item" :task="doneTask" />
    </section>
</template>

<style scoped>
.mnt-error {
    margin: 0 0 12px;
    padding: 8px 12px;
    border-radius: var(--radius-sm);
    font-size: 13px;
    color: var(--neg);
    background: color-mix(in srgb, var(--neg) 10%, transparent);
}
/* .mnt-list/.mnt-row/.mnt-badge etc. are global (app.css) — shared with
   the /maintenance page and the dashboard card. Only this section's own
   pieces stay scoped. */
.mnt-last {
    font-size: 12px;
    color: var(--fg-subtle);
}
.mnt-actions {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-shrink: 0;
    /* When the row wraps, actions right-align on their own line. */
    margin-left: auto;
}
</style>
