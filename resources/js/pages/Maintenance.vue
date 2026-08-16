<script setup lang="ts">
import MarkMaintenanceDoneDialog from '@/components/MarkMaintenanceDoneDialog.vue';
import { useDateFormat } from '@/composables/useDateFormat';
import { trans } from '@/composables/useTranslations';
import AppLayout from '@/layouts/AppLayout.vue';
import { maintenance } from '@/routes';
import itemRoutes from '@/routes/items';
import type { BreadcrumbItemType, MaintenanceTaskRow } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { Check, ChevronRight } from '@lucide/vue';
import { ref } from 'vue';

interface GlobalTaskRow extends MaintenanceTaskRow {
    item: { id: number; name: string; location: string };
}

type Filter = 'all' | 'overdue' | 'due-soon';

const props = defineProps<{
    filter: Filter;
    counts: { all: number; overdue: number; due_soon: number };
    tasks: GlobalTaskRow[];
}>();

const breadcrumbs: BreadcrumbItemType[] = [{ title: trans('maintenance.page.title'), href: maintenance().url }];

const { formatDate: fmtDate } = useDateFormat();

const filters: { key: Filter; label: string; count: number }[] = [
    { key: 'all', label: trans('maintenance.filters.all'), count: props.counts.all },
    { key: 'overdue', label: trans('maintenance.filters.overdue'), count: props.counts.overdue },
    { key: 'due-soon', label: trans('maintenance.filters.due_soon'), count: props.counts.due_soon },
];

// "Mark done" works right from the overview — the weekly review shouldn't
// need a navigation per task. The dialog needs the task AND its item.
const doneDialogOpen = ref(false);
const doneTask = ref<GlobalTaskRow | null>(null);

function openMarkDone(task: GlobalTaskRow) {
    doneTask.value = task;
    doneDialogOpen.value = true;
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="$t('maintenance.page.title')" />

        <div class="page">
            <h2 class="m-0 mb-1 text-22 font-semibold tracking-display">{{ $t('maintenance.page.title') }}</h2>
            <p class="sub m-0 mb-5 text-13 text-fg-muted">
                {{ $t('maintenance.page.subtitle') }}
            </p>

            <div class="mb-4 flex flex-wrap gap-1.5">
                <Link
                    v-for="entry in filters"
                    :key="entry.key"
                    :href="entry.key === 'all' ? maintenance().url : maintenance({ query: { filter: entry.key } }).url"
                    :class="['chip', filter === entry.key ? 'active' : '']"
                    :data-test="`maintenance-filter-${entry.key}`"
                >
                    {{ entry.label }}
                    <span class="opacity-65">{{ entry.count }}</span>
                </Link>
            </div>

            <div v-if="tasks.length === 0" class="card card-pad text-center text-13 text-fg-muted">
                {{ $t('maintenance.page.empty') }}
            </div>

            <ul v-else class="mnt-list">
                <li v-for="task in tasks" :key="task.id" class="mnt-row" data-test="maintenance-global-row">
                    <div class="mnt-main">
                        <span class="mnt-title">{{ task.title }}</span>
                        <span class="mnt-summary">{{ task.schedule_summary }}</span>
                        <Link :href="itemRoutes.show(task.item.id).url" class="mnt-item-link">
                            <span>{{ task.item.name }}</span>
                            <template v-if="task.item.location">
                                <ChevronRight class="opacity-60" :size="11" />
                                <span class="mnt-item-location">{{ task.item.location }}</span>
                            </template>
                        </Link>
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
                    <button type="button" class="btn-pill" data-test="maintenance-global-done" @click="openMarkDone(task)">
                        <Check :size="14" />
                        {{ $t('maintenance.mark_done') }}
                    </button>
                </li>
            </ul>
        </div>

        <MarkMaintenanceDoneDialog v-if="doneTask" v-model:open="doneDialogOpen" :item="doneTask.item" :task="doneTask" />
    </AppLayout>
</template>

<style scoped>
/* Row anatomy (.mnt-list/.mnt-row/…) is global in app.css, shared with
   the item-page section. Scoped here: the item + location line and a
   wrap tweak for this page's badge column. */
.mnt-due {
    margin-left: auto;
}
.mnt-item-link {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 12px;
    color: var(--fg-subtle);
    text-decoration: none;
    width: fit-content;
}
.mnt-item-link:hover {
    color: var(--accent);
}
.mnt-item-location {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
</style>
