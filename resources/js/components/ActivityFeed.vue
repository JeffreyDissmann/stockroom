<script setup lang="ts">
import { useDateFormat } from '@/composables/useDateFormat';
import { trans, transChoice } from '@/composables/useTranslations';
import type { ActivityRow } from '@/types';
import { Link } from '@inertiajs/vue3';

// `flat` drops the card wrapper so the feed can sit inside another card.
withDefaults(defineProps<{ rows: ActivityRow[]; showSubject?: boolean; flat?: boolean }>(), { showSubject: true, flat: false });

const { formatDate } = useDateFormat();

// A move is an update whose sole change is the item's location.
function isMove(row: ActivityRow): boolean {
    return row.event === 'updated' && row.changes.length === 1 && row.changes[0].field === 'location';
}

function isImageAdd(row: ActivityRow): boolean {
    return row.event === 'image_added';
}

function isLink(row: ActivityRow): boolean {
    return row.event === 'link_added' || row.event === 'link_removed';
}

function isMaintenance(row: ActivityRow): boolean {
    return row.event.startsWith('maintenance_');
}

function verb(row: ActivityRow): string {
    switch (row.event) {
        case 'created':
        case 'image_added':
            return trans('activity.verbs.added');
        case 'deleted':
            return trans('activity.verbs.deleted');
        case 'link_added':
            return trans('activity.verbs.linked');
        case 'link_removed':
            return trans('activity.verbs.unlinked');
        case 'maintenance_task_added':
            return trans('activity.verbs.maintenance_scheduled');
        case 'maintenance_completed':
            return trans('activity.verbs.maintenance_completed');
        case 'maintenance_skipped':
            return trans('activity.verbs.maintenance_skipped');
        case 'maintenance_logged':
            return trans('activity.verbs.maintenance_logged');
        case 'maintenance_task_deleted':
            return trans('activity.verbs.maintenance_task_deleted');
        case 'maintenance_entry_deleted':
            return trans('activity.verbs.maintenance_entry_deleted');
        default:
            return isMove(row) ? trans('activity.verbs.moved') : trans('activity.verbs.updated');
    }
}

function when(iso: string | null): string {
    if (!iso) return '';
    const diff = Date.now() - new Date(iso).getTime();
    const mins = Math.round(diff / 60000);
    if (mins < 1) return trans('activity.time.just_now');
    if (mins < 60) return transChoice('activity.time.minutes', mins);
    const hrs = Math.round(mins / 60);
    if (hrs < 24) return transChoice('activity.time.hours', hrs);
    const days = Math.round(hrs / 24);
    if (days < 30) return transChoice('activity.time.days', days);
    return formatDate(iso);
}
</script>

<template>
    <ul :class="['divide-y', { card: !flat }]">
        <li v-for="row in rows" :key="row.id" class="flex gap-3 px-4 py-3">
            <span :class="['act-dot', `act-${row.event}`]" />
            <div class="min-w-0 flex-1">
                <div class="text-sm">
                    <span class="font-medium" :class="`act-text-${row.event}`">{{ verb(row) }}</span>

                    <template v-if="isImageAdd(row)">
                        <span class="mx-1 font-medium">{{ $tChoice('activity.images_count', row.count) }}</span>
                        <template v-if="showSubject">
                            <span style="color: var(--fg-subtle)">{{ $t('activity.words.to') }}</span>
                            <component
                                :is="row.subject_url ? Link : 'span'"
                                :href="row.subject_url ?? undefined"
                                class="ml-1 font-medium"
                                :class="row.subject_url ? 'hover:underline' : ''"
                                >{{ row.subject_label ?? $t('activity.words.unknown') }}</component
                            >
                        </template>
                    </template>

                    <template v-else-if="isLink(row)">
                        <!-- "Linked with X" / "Unlinked from X". `mx-2` (not
                             mx-1) so the connectors don't visually crowd the
                             verb — Vue strips the inter-span whitespace, so
                             the only horizontal gap is whatever margin we set
                             here. The same bump applies to the words.* spans
                             everywhere else in this feed; tested visually. -->
                        <span class="mx-2" style="color: var(--fg-subtle)">{{
                            row.event === 'link_added' ? $t('activity.words.with') : $t('activity.words.from')
                        }}</span>
                        <component
                            :is="row.related_url ? Link : 'span'"
                            :href="row.related_url ?? undefined"
                            class="font-medium"
                            :class="row.related_url ? 'hover:underline' : ''"
                            >{{ row.related_label ?? $t('activity.words.unknown') }}</component
                        >
                        <template v-if="showSubject">
                            <span class="mx-2" style="color: var(--fg-subtle)">{{ $t('activity.words.on') }}</span>
                            <component
                                :is="row.subject_url ? Link : 'span'"
                                :href="row.subject_url ?? undefined"
                                class="font-medium"
                                :class="row.subject_url ? 'hover:underline' : ''"
                                >{{ row.subject_label ?? $t('activity.words.unknown') }}</component
                            >
                        </template>
                    </template>

                    <template v-else-if="isMaintenance(row)">
                        <!-- "Completed «Descale» on Coffee maker" — verb,
                             then what the event was about, then the item
                             when the feed isn't already item-scoped. -->
                        <span class="ml-1 font-medium">{{ row.task_title ?? $t('activity.words.unknown') }}</span>
                        <template v-if="showSubject">
                            <span class="mx-2" style="color: var(--fg-subtle)">{{ $t('activity.words.on') }}</span>
                            <component
                                :is="row.subject_url ? Link : 'span'"
                                :href="row.subject_url ?? undefined"
                                class="font-medium"
                                :class="row.subject_url ? 'hover:underline' : ''"
                                >{{ row.subject_label ?? $t('activity.words.unknown') }}</component
                            >
                        </template>
                    </template>

                    <template v-else>
                        <template v-if="showSubject">
                            <span v-if="!isMove(row)" class="mx-1" style="color: var(--fg-subtle)">{{ row.subject_type }}</span>
                            <component
                                :is="row.subject_url ? Link : 'span'"
                                :href="row.subject_url ?? undefined"
                                class="font-medium"
                                :class="[isMove(row) ? 'mx-1' : '', row.subject_url ? 'hover:underline' : '']"
                                >{{ row.subject_label ?? $t('activity.words.unknown') }}</component
                            >
                        </template>
                        <template v-if="isMove(row)">
                            <span :class="showSubject ? '' : 'ml-1'" style="color: var(--fg-subtle)">{{ $t('activity.words.from') }}</span>
                            <span class="mx-1 font-medium">{{ row.changes[0].from }}</span>
                            <span style="color: var(--fg-subtle)">{{ $t('activity.words.to') }}</span>
                            <span class="ml-1 font-medium">{{ row.changes[0].to }}</span>
                        </template>
                    </template>
                </div>
                <ul v-if="row.event === 'updated' && !isMove(row) && row.changes.length" class="mt-1 space-y-0.5">
                    <li v-for="(change, i) in row.changes" :key="i" class="text-xs" style="color: var(--fg-subtle)">
                        <span class="mono">{{ change.field }}</span
                        >:
                        <span class="line-through">{{ change.from ?? '—' }}</span>
                        →
                        <span style="color: var(--fg-muted)">{{ change.to ?? '—' }}</span>
                    </li>
                </ul>
            </div>
            <div class="shrink-0 text-xs" style="color: var(--fg-subtle)">
                {{ when(row.at) }}<template v-if="row.causer"> · {{ row.causer }}</template>
            </div>
        </li>
    </ul>
</template>

<style scoped>
.act-dot {
    margin-top: 5px;
    width: 8px;
    height: 8px;
    border-radius: 999px;
    flex-shrink: 0;
    background: var(--fg-subtle);
}
.act-created,
.act-image_added,
.act-maintenance_completed,
.act-maintenance_logged {
    background: #16a34a;
}
.act-updated,
.act-link_added,
.act-link_removed,
.act-maintenance_task_added,
.act-maintenance_skipped {
    background: #d97706;
}
.act-deleted,
.act-maintenance_task_deleted,
.act-maintenance_entry_deleted {
    background: #dc2626;
}
.act-text-created,
.act-text-image_added,
.act-text-maintenance_completed,
.act-text-maintenance_logged {
    color: #16a34a;
}
.act-text-deleted,
.act-text-maintenance_task_deleted,
.act-text-maintenance_entry_deleted {
    color: #dc2626;
}
.act-text-link_added,
.act-text-link_removed,
.act-text-maintenance_task_added,
.act-text-maintenance_skipped {
    color: #d97706;
}
.dark .act-created,
.dark .act-image_added,
.dark .act-maintenance_completed,
.dark .act-maintenance_logged {
    background: #4ade80;
}
.dark .act-updated,
.dark .act-link_added,
.dark .act-link_removed,
.dark .act-maintenance_task_added,
.dark .act-maintenance_skipped {
    background: #fbbf24;
}
.dark .act-deleted,
.dark .act-maintenance_task_deleted,
.dark .act-maintenance_entry_deleted {
    background: #f87171;
}
.dark .act-text-created,
.dark .act-text-image_added,
.dark .act-text-maintenance_completed,
.dark .act-text-maintenance_logged {
    color: #4ade80;
}
.dark .act-text-deleted,
.dark .act-text-maintenance_task_deleted,
.dark .act-text-maintenance_entry_deleted {
    color: #f87171;
}
.dark .act-text-link_added,
.dark .act-text-link_removed,
.dark .act-text-maintenance_task_added,
.dark .act-text-maintenance_skipped {
    color: #fbbf24;
}
.divide-y > li + li {
    border-top: 1px solid var(--border);
}
</style>
