<script setup lang="ts">
import ItemTypeIcon from '@/components/ItemTypeIcon.vue';
import TagBadge from '@/components/TagBadge.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, ItemSummary } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronRight, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    item: ItemSummary;
    breadcrumb: ItemSummary[];
    children: ItemSummary[];
}>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => {
    const base: BreadcrumbItem[] = [{ title: 'Inventory', href: '/items' }];
    for (const item of props.breadcrumb) {
        base.push({ title: item.name, href: `/items/${item.id}` });
    }
    base.push({ title: props.item.name, href: `/items/${props.item.id}` });
    return base;
});

function destroyItem() {
    if (!confirm(`Delete "${props.item.name}"? Any items inside will become top-level.`)) {
        return;
    }
    router.delete(`/items/${props.item.id}`);
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="item.name" />

        <div class="flex flex-col gap-6 p-4 md:p-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="flex items-start gap-4">
                    <div class="flex size-12 shrink-0 items-center justify-center rounded-lg bg-muted text-muted-foreground">
                        <ItemTypeIcon :type="item.type.value" class="size-6" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs uppercase tracking-wide text-muted-foreground">{{ item.type.label }}</p>
                        <h1 class="text-2xl font-semibold tracking-tight">{{ item.name }}</h1>
                        <p v-if="item.description" class="mt-2 max-w-prose text-sm text-muted-foreground">{{ item.description }}</p>
                        <div v-if="item.tags?.length" class="mt-3 flex flex-wrap gap-1">
                            <TagBadge v-for="tag in item.tags" :key="tag.id" :tag="tag" />
                        </div>
                    </div>
                </div>
                <div class="flex gap-2">
                    <Link :href="`/items/${item.id}/edit`">
                        <Button variant="outline" size="sm">
                            <Pencil class="mr-1 size-4" />
                            Edit
                        </Button>
                    </Link>
                    <Button variant="ghost" size="sm" class="text-destructive hover:text-destructive" @click="destroyItem">
                        <Trash2 class="mr-1 size-4" />
                        Delete
                    </Button>
                </div>
            </div>

            <section>
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-sm font-medium uppercase tracking-wide text-muted-foreground">Contents</h2>
                    <Link :href="`/items/create?parent=${item.id}`">
                        <Button size="sm" variant="outline">
                            <Plus class="mr-1 size-4" />
                            Add child
                        </Button>
                    </Link>
                </div>

                <div v-if="children.length === 0" class="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
                    Nothing inside this {{ item.type.label.toLowerCase() }} yet.
                </div>

                <ul v-else class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <li v-for="child in children" :key="child.id" class="group rounded-lg border bg-card shadow-sm transition hover:shadow">
                        <Link :href="`/items/${child.id}`" class="flex items-start gap-3 p-4">
                            <div class="flex size-10 shrink-0 items-center justify-center rounded-md bg-muted text-muted-foreground">
                                <ItemTypeIcon :type="child.type.value" class="size-5" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center justify-between gap-2">
                                    <p class="truncate font-medium">{{ child.name }}</p>
                                    <ChevronRight class="size-4 shrink-0 text-muted-foreground transition group-hover:translate-x-0.5" />
                                </div>
                                <p class="mt-0.5 text-xs uppercase tracking-wide text-muted-foreground">
                                    {{ child.type.label }}
                                    <span v-if="(child.children_count ?? 0) > 0">· {{ child.children_count }} inside</span>
                                </p>
                                <p v-if="child.description" class="mt-1 line-clamp-2 text-sm text-muted-foreground">{{ child.description }}</p>
                                <div v-if="child.tags?.length" class="mt-2 flex flex-wrap gap-1">
                                    <TagBadge v-for="tag in child.tags" :key="tag.id" :tag="tag" />
                                </div>
                            </div>
                        </Link>
                    </li>
                </ul>
            </section>
        </div>
    </AppLayout>
</template>
