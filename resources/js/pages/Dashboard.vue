<script setup lang="ts">
import ItemTypeIcon from '@/components/ItemTypeIcon.vue';
import TagBadge from '@/components/TagBadge.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItemType, ItemSummary, TagSummary } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ChevronRight, Home, Plus } from 'lucide-vue-next';

interface RecentItem {
    id: number;
    name: string;
    created_at_human: string | null;
    type: ItemSummary['type'];
    parent: { id: number; name: string; type: ItemSummary['type'] } | null;
}

interface DashboardTag extends TagSummary {
    items_count: number;
}

defineProps<{
    stats: { total: number; rooms: number; containers: number; items: number };
    recent: RecentItem[];
    rooms: ItemSummary[];
    tags: DashboardTag[];
}>();

const breadcrumbs: BreadcrumbItemType[] = [{ title: 'Dashboard', href: '/dashboard' }];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Dashboard" />

        <template #topbar-actions>
            <Link href="/items/create" class="btn-primary">
                <Plus :size="14" />
                Add item
            </Link>
        </template>

        <div class="page">
            <div class="mb-5">
                <h2 style="margin: 0; font-size: 22px; font-weight: 600; letter-spacing: -0.015em">Welcome back</h2>
                <p style="margin-top: 4px; color: var(--fg-muted); font-size: 13px">A snapshot of your stockroom.</p>
            </div>

            <section class="stats-strip mb-4">
                <div class="stat-cell">
                    <div class="lbl">Items</div>
                    <div class="val">{{ stats.total.toLocaleString() }}</div>
                    <div class="delta">Tracked across all rooms</div>
                </div>
                <div class="stat-cell">
                    <div class="lbl">Rooms</div>
                    <div class="val">{{ stats.rooms }}</div>
                    <div class="delta">Top-level spaces</div>
                </div>
                <div class="stat-cell">
                    <div class="lbl">Containers</div>
                    <div class="val">{{ stats.containers }}</div>
                    <div class="delta">Boxes, drawers, shelves</div>
                </div>
                <div class="stat-cell">
                    <div class="lbl">Loose items</div>
                    <div class="val">{{ stats.items }}</div>
                    <div class="delta">Leaf-level things</div>
                </div>
            </section>

            <div class="grid gap-4 lg:grid-cols-[1.5fr_1fr]">
                <section class="card">
                    <div class="card-head">
                        <h3>Recently added</h3>
                        <Link href="/items" class="meta" style="display: inline-flex; align-items: center; gap: 4px">
                            View all
                            <ChevronRight :size="12" />
                        </Link>
                    </div>
                    <div v-if="recent.length === 0" class="card-pad" style="text-align: center; color: var(--fg-muted)">
                        Nothing yet.
                        <Link href="/items/create" style="color: var(--fg); font-weight: 500; text-decoration: underline">Add the first item</Link>.
                    </div>
                    <table v-else class="table">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Inside</th>
                                <th class="num">Added</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="r in recent" :key="r.id" class="row-clickable" @click="$inertia.visit(`/items/${r.id}`)">
                                <td>
                                    <div class="row-name">
                                        <span class="row-thumb"><ItemTypeIcon :type="r.type.value" class="size-4" /></span>
                                        <div>
                                            <div class="nm">{{ r.name }}</div>
                                            <div class="sub">{{ r.type.label }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <Link v-if="r.parent" :href="`/items/${r.parent.id}`" class="flex items-center gap-1.5" style="font-size: 12.5px; color: var(--fg-muted)">
                                        <ItemTypeIcon :type="r.parent.type.value" class="size-3.5" />
                                        {{ r.parent.name }}
                                    </Link>
                                    <span v-else style="color: var(--fg-subtle); font-size: 12.5px">Top level</span>
                                </td>
                                <td class="num mono" style="color: var(--fg-subtle); font-size: 12px">{{ r.created_at_human }}</td>
                            </tr>
                        </tbody>
                    </table>
                </section>

                <section class="flex flex-col gap-4">
                    <div class="card">
                        <div class="card-head">
                            <h3>Rooms</h3>
                            <Link href="/items" class="meta" style="display: inline-flex; align-items: center; gap: 4px">
                                Browse
                                <ChevronRight :size="12" />
                            </Link>
                        </div>
                        <div v-if="rooms.length === 0" class="card-pad" style="text-align: center; color: var(--fg-muted)">
                            No rooms yet.
                        </div>
                        <div v-else>
                            <Link v-for="room in rooms" :key="room.id" :href="`/items/${room.id}`" class="act-row">
                                <span class="row-thumb"><Home class="size-4" /></span>
                                <div class="body">
                                    <div class="t" style="font-weight: 500">{{ room.name }}</div>
                                    <div class="when mono">{{ room.children_count ?? 0 }} inside</div>
                                </div>
                                <ChevronRight :size="14" style="color: var(--fg-subtle); align-self: center" />
                            </Link>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-head">
                            <h3>Top tags</h3>
                            <Link href="/tags" class="meta" style="display: inline-flex; align-items: center; gap: 4px">
                                Manage
                                <ChevronRight :size="12" />
                            </Link>
                        </div>
                        <div v-if="tags.length === 0" class="card-pad" style="text-align: center; color: var(--fg-muted)">
                            No tags yet.
                        </div>
                        <div v-else class="card-pad flex flex-wrap gap-1.5">
                            <TagBadge v-for="tag in tags" :key="tag.id" :tag="tag" />
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
