<script setup lang="ts">
import ActivityFeed from '@/components/ActivityFeed.vue';
import ItemThumbnail from '@/components/ItemThumbnail.vue';
import ItemTypeIcon from '@/components/ItemTypeIcon.vue';
import { useMaintenanceDue } from '@/composables/useMaintenanceDue';
import { trans } from '@/composables/useTranslations';
import AppLayout from '@/layouts/AppLayout.vue';
import { itemIconMap } from '@/lib/itemIcons';
// Aliased: `maintenance` would clash with the prop of the same name.
import { activity as activityRoute, maintenance as maintenanceRoute, search } from '@/routes';
import itemRoutes from '@/routes/items';
import tagRoutes from '@/routes/tags';
import type { ActivityRow, BreadcrumbItemType, ItemSummary, MaintenanceTaskRow, SharedData, TagSummary } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ChevronRight, Plus, Wrench } from 'lucide-vue-next';
import { computed } from 'vue';

interface RecentItem {
    id: number;
    name: string;
    created_at_human: string | null;
    type: ItemSummary['type'];
    thumb_url: string | null;
    icon: string | null;
    parent: { id: number; name: string; type: ItemSummary['type'] } | null;
}

interface DashboardTag extends TagSummary {
    items_count: number;
}

interface RoomRow {
    id: number;
    name: string;
    icon: string | null;
    count: number;
}

interface DueTaskRow extends MaintenanceTaskRow {
    item: { id: number; name: string };
}

const props = defineProps<{
    stats: { total: number; value: number; rooms: number; containers: number; items: number };
    recent: RecentItem[];
    tags: DashboardTag[];
    rooms: RoomRow[];
    activity: ActivityRow[];
    maintenance: { count: number; tasks: DueTaskRow[] };
}>();

const { dueBadge } = useMaintenanceDue();

const breadcrumbs: BreadcrumbItemType[] = [{ title: trans('nav.dashboard'), href: '/dashboard' }];

const page = usePage<SharedData>();
const currency = page.props.currency;
const firstName = computed(() => page.props.auth.user.name.split(' ')[0]);
const valueLabel = computed(() =>
    new Intl.NumberFormat(currency.locale, { style: 'currency', currency: currency.code, maximumFractionDigits: 0 }).format(props.stats.value),
);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="$t('nav.dashboard')" />

        <template #topbar-actions>
            <Link :href="itemRoutes.create().url" class="btn-primary">
                <Plus :size="14" />
                {{ $t('nav.add_item') }}
            </Link>
        </template>

        <div class="page">
            <div class="mb-5">
                <h2 style="margin: 0; font-size: 22px; font-weight: 600; letter-spacing: -0.015em">
                    {{ $t('dashboard.welcome', { name: firstName }) }}
                </h2>
                <p style="margin-top: 4px; color: var(--fg-muted); font-size: 13px">{{ $t('dashboard.subtitle') }}</p>
            </div>

            <section class="stats-strip mb-4">
                <Link :href="search({ query: { type: 'item' } }).url" class="stat-cell stat-cell-link">
                    <div class="lbl">{{ $t('dashboard.stats.items') }}</div>
                    <div class="val">{{ stats.items.toLocaleString() }}</div>
                    <div class="delta">{{ $t('dashboard.stats.items_hint') }}</div>
                </Link>
                <div class="stat-cell">
                    <div class="lbl">{{ $t('dashboard.stats.value') }}</div>
                    <div class="val">{{ valueLabel }}</div>
                    <div class="delta">{{ $t('dashboard.stats.value_hint') }}</div>
                </div>
                <Link :href="search({ query: { type: 'room' } }).url" class="stat-cell stat-cell-link">
                    <div class="lbl">{{ $t('dashboard.stats.rooms') }}</div>
                    <div class="val">{{ stats.rooms }}</div>
                    <div class="delta">{{ $t('dashboard.stats.rooms_hint') }}</div>
                </Link>
                <Link :href="search({ query: { type: 'container' } }).url" class="stat-cell stat-cell-link">
                    <div class="lbl">{{ $t('dashboard.stats.containers') }}</div>
                    <div class="val">{{ stats.containers }}</div>
                    <div class="delta">{{ $t('dashboard.stats.containers_hint') }}</div>
                </Link>
            </section>

            <!-- Tags: most-used first; click to open search filtered by that tag. -->
            <section class="card mb-4">
                <div v-if="tags.length === 0" class="card-pad" style="color: var(--fg-muted); font-size: 13px">{{ $t('dashboard.no_tags') }}</div>
                <div v-else class="card-pad flex items-center gap-2 overflow-x-auto">
                    <Link
                        v-for="tag in tags"
                        :key="tag.id"
                        :href="search({ query: { 'tags[]': tag.id } }).url"
                        class="tag-pill shrink-0 whitespace-nowrap"
                    >
                        <span v-if="tag.color" class="size-2 rounded-full" :style="{ backgroundColor: tag.color }" />
                        {{ tag.name }}
                        <span class="tag-pill-count mono">{{ tag.items_count }}</span>
                    </Link>
                    <Link :href="tagRoutes.index().url" class="tag-pill tag-pill-more shrink-0 whitespace-nowrap"
                        >{{ $t('common.more') }} <ChevronRight :size="12"
                    /></Link>
                </div>
            </section>

            <!-- Rooms: fullest first; click to open the room. -->
            <section class="card mb-4">
                <div v-if="rooms.length === 0" class="card-pad" style="color: var(--fg-muted); font-size: 13px">{{ $t('dashboard.no_rooms') }}</div>
                <div v-else class="card-pad flex items-center gap-2 overflow-x-auto">
                    <Link v-for="room in rooms" :key="room.id" :href="itemRoutes.show(room.id).url" class="tag-pill shrink-0 whitespace-nowrap">
                        <component :is="itemIconMap[room.icon]" v-if="room.icon && itemIconMap[room.icon]" class="size-3.5" />
                        <ItemTypeIcon v-else type="room" class="size-3.5" />
                        {{ room.name }}
                        <span class="tag-pill-count mono">{{ room.count }}</span>
                    </Link>
                    <Link :href="search({ query: { type: 'room' } }).url" class="tag-pill tag-pill-more shrink-0 whitespace-nowrap"
                        >{{ $t('common.more') }} <ChevronRight :size="12"
                    /></Link>
                </div>
            </section>

            <!-- Maintenance needing attention (overdue or inside its
                 reminder window). Deliberately absent when nothing is due —
                 an all-clear card would just be dashboard noise. -->
            <section v-if="maintenance.count > 0" class="card mb-4" data-test="dashboard-maintenance-card">
                <div class="card-head">
                    <h3 class="flex items-center gap-2">
                        <Wrench :size="14" style="color: var(--fg-muted)" /> {{ $t('dashboard.maintenance_due', { count: maintenance.count }) }}
                    </h3>
                    <Link :href="maintenanceRoute().url" class="meta dash-link">{{ $t('dashboard.view_all') }} <ChevronRight :size="12" /></Link>
                </div>
                <ul class="dash-mnt">
                    <li v-for="task in maintenance.tasks" :key="task.id">
                        <Link :href="itemRoutes.show(task.item.id).url" class="dash-mnt-row" data-test="dashboard-maintenance-row">
                            <span class="mnt-badge" :class="task.is_overdue ? 'is-overdue' : 'is-due-soon'">{{ dueBadge(task) }}</span>
                            <span class="dash-mnt-title">{{ task.title }}</span>
                            <span class="dash-mnt-item">{{ task.item.name }}</span>
                        </Link>
                    </li>
                </ul>
            </section>

            <div class="grid gap-4 lg:grid-cols-[1.4fr_1fr]">
                <section class="card">
                    <div class="card-head">
                        <h3>{{ $t('dashboard.recently_added') }}</h3>
                        <Link :href="search({ query: { sort: 'added' } }).url" class="meta dash-link"
                            >{{ $t('dashboard.view_all') }} <ChevronRight :size="12"
                        /></Link>
                    </div>
                    <div v-if="recent.length === 0" class="card-pad" style="text-align: center; color: var(--fg-muted)">
                        {{ $t('dashboard.nothing_yet') }}
                        <Link :href="itemRoutes.create().url" style="color: var(--fg); font-weight: 500; text-decoration: underline">{{
                            $t('dashboard.add_first')
                        }}</Link
                        >.
                    </div>
                    <table v-else class="table">
                        <thead>
                            <tr>
                                <th>{{ $t('dashboard.col_item') }}</th>
                                <th>{{ $t('dashboard.col_inside') }}</th>
                                <th class="num">{{ $t('dashboard.col_added') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="r in recent" :key="r.id" class="row-clickable" @click="$inertia.visit(`/items/${r.id}`)">
                                <td>
                                    <div class="row-name">
                                        <span class="row-thumb"
                                            ><ItemThumbnail :item="{ name: r.name, type: r.type, thumb_url: r.thumb_url, icon: r.icon }" size="sm"
                                        /></span>
                                        <div>
                                            <div class="nm">{{ r.name }}</div>
                                            <div class="sub">{{ r.type.label }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <Link
                                        v-if="r.parent"
                                        :href="itemRoutes.show(r.parent.id).url"
                                        class="flex items-center gap-1.5"
                                        style="font-size: 12.5px; color: var(--fg-muted)"
                                    >
                                        <ItemTypeIcon :type="r.parent.type.value" class="size-3.5" />
                                        {{ r.parent.name }}
                                    </Link>
                                    <span v-else style="color: var(--fg-subtle); font-size: 12.5px">{{ $t('common.top_level') }}</span>
                                </td>
                                <td class="num mono" style="color: var(--fg-subtle); font-size: 12px">{{ r.created_at_human }}</td>
                            </tr>
                        </tbody>
                    </table>
                </section>

                <section class="card">
                    <div class="card-head">
                        <h3>{{ $t('dashboard.recent_activity') }}</h3>
                        <Link :href="activityRoute().url" class="meta dash-link">{{ $t('dashboard.view_all') }} <ChevronRight :size="12" /></Link>
                    </div>
                    <ActivityFeed v-if="activity.length" :rows="activity" flat />
                    <div v-else class="card-pad" style="text-align: center; color: var(--fg-muted)">{{ $t('dashboard.no_activity') }}</div>
                </section>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.dash-link {
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
/* Maintenance-due rows: badge | task | item, whole row links to the item. */
.dash-mnt {
    list-style: none;
    padding: 0;
    margin: 0;
}
.dash-mnt > li + li .dash-mnt-row {
    border-top: 1px solid var(--border);
}
.dash-mnt-row {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 9px 16px;
    font-size: 13px;
    color: inherit;
    text-decoration: none;
}
.dash-mnt-row:hover {
    background: var(--bg-hover);
}
.dash-mnt-title {
    font-weight: 500;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.dash-mnt-item {
    margin-left: auto;
    flex-shrink: 0;
    color: var(--fg-subtle);
    font-size: 12.5px;
}
.stat-cell-link {
    text-decoration: none;
    color: inherit;
    cursor: pointer;
    transition: background 0.12s;
}
.stat-cell-link:hover {
    background: var(--bg-hover);
}
.tag-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 3px 10px;
    border-radius: 999px;
    border: 1px solid var(--border);
    background: var(--bg-elev);
    color: var(--fg-muted);
    font-size: 12px;
    font-weight: 500;
    text-decoration: none;
    transition:
        border-color 0.12s,
        color 0.12s,
        background 0.12s;
}
.tag-pill:hover {
    border-color: var(--border-strong);
    color: var(--fg);
    background: var(--bg-hover);
}
.tag-pill-count {
    font-size: 11px;
    color: var(--fg-subtle);
}
.tag-pill-more {
    gap: 2px;
    color: var(--fg);
    font-weight: 600;
}
</style>
