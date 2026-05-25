<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItemType } from '@/types';
import { Head, Link } from '@inertiajs/vue3';

interface ActivityChange {
    field: string;
    from: string | null;
    to: string | null;
}

interface ActivityRow {
    id: number;
    event: 'created' | 'updated' | 'deleted' | string;
    subject_type: string;
    subject_label: string | null;
    subject_url: string | null;
    causer: string | null;
    changes: ActivityChange[];
    at: string | null;
}

interface Paginated<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
}

defineProps<{ activities: Paginated<ActivityRow> }>();

const breadcrumbs: BreadcrumbItemType[] = [{ title: 'Activity', href: '/activity' }];

// A move is an update whose sole change is the item's location.
function isMove(row: ActivityRow): boolean {
    return row.event === 'updated' && row.changes.length === 1 && row.changes[0].field === 'location';
}

function verb(row: ActivityRow): string {
    switch (row.event) {
        case 'created':
            return 'Added';
        case 'deleted':
            return 'Deleted';
        default:
            return isMove(row) ? 'Moved' : 'Updated';
    }
}

function when(iso: string | null): string {
    if (!iso) return '';
    const diff = Date.now() - new Date(iso).getTime();
    const mins = Math.round(diff / 60000);
    if (mins < 1) return 'just now';
    if (mins < 60) return `${mins}m ago`;
    const hrs = Math.round(mins / 60);
    if (hrs < 24) return `${hrs}h ago`;
    const days = Math.round(hrs / 24);
    if (days < 30) return `${days}d ago`;
    return new Date(iso).toLocaleDateString();
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Activity" />

        <div class="page">
            <h2 style="margin: 0 0 4px; font-size: 22px; font-weight: 600; letter-spacing: -0.015em">Activity</h2>
            <p class="sub" style="color: var(--fg-muted); font-size: 13px; margin: 0 0 20px">
                A log of changes made to items, tags and custom fields.
            </p>

            <div v-if="activities.data.length === 0" class="card card-pad" style="text-align: center; color: var(--fg-muted)">
                No activity yet.
            </div>

            <ul v-else class="card divide-y">
                <li v-for="row in activities.data" :key="row.id" class="flex gap-3 px-4 py-3">
                    <span :class="['act-dot', `act-${row.event}`]" />
                    <div class="min-w-0 flex-1">
                        <div class="text-sm">
                            <span class="font-medium" :class="`act-text-${row.event}`">{{ verb(row) }}</span>
                            <span v-if="!isMove(row)" class="mx-1" style="color: var(--fg-subtle)">{{ row.subject_type.toLowerCase() }}</span>
                            <component
                                :is="row.subject_url ? Link : 'span'"
                                :href="row.subject_url ?? undefined"
                                class="font-medium"
                                :class="[isMove(row) ? 'mx-1' : '', row.subject_url ? 'hover:underline' : '']"
                            >{{ row.subject_label ?? 'unknown' }}</component>
                            <template v-if="isMove(row)">
                                <span style="color: var(--fg-subtle)">from</span>
                                <span class="mx-1 font-medium">{{ row.changes[0].from }}</span>
                                <span style="color: var(--fg-subtle)">to</span>
                                <span class="ml-1 font-medium">{{ row.changes[0].to }}</span>
                            </template>
                        </div>
                        <ul v-if="row.event === 'updated' && !isMove(row) && row.changes.length" class="mt-1 space-y-0.5">
                            <li v-for="(change, i) in row.changes" :key="i" class="text-xs" style="color: var(--fg-subtle)">
                                <span class="mono">{{ change.field }}</span>:
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

            <nav v-if="activities.links.length > 3" class="flex flex-wrap justify-center gap-1 mt-6">
                <component
                    :is="link.url ? Link : 'span'"
                    v-for="(link, i) in activities.links"
                    :key="i"
                    :href="link.url ?? undefined"
                    :class="['chip', link.active ? 'active' : '', !link.url ? 'pointer-events-none opacity-40' : '']"
                >
                    <span v-html="link.label" />
                </component>
            </nav>
        </div>
    </AppLayout>
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
.act-created {
    background: #16a34a;
}
.act-updated {
    background: #d97706;
}
.act-deleted {
    background: #dc2626;
}
.act-text-created {
    color: #16a34a;
}
.act-text-deleted {
    color: #dc2626;
}
.dark .act-created {
    background: #4ade80;
}
.dark .act-updated {
    background: #fbbf24;
}
.dark .act-deleted {
    background: #f87171;
}
.dark .act-text-created {
    color: #4ade80;
}
.dark .act-text-deleted {
    color: #f87171;
}
.divide-y > li + li {
    border-top: 1px solid var(--border);
}
</style>
