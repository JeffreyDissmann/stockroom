<script setup lang="ts">
import ItemTypeIcon from '@/components/ItemTypeIcon.vue';
import TagBadge from '@/components/TagBadge.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, ItemSummary } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ChevronRight, Pencil, Plus } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    parent: ItemSummary | null;
    breadcrumb: ItemSummary[];
    items: ItemSummary[];
}>();

const pageTitle = computed(() => props.parent?.name ?? 'Inventory');

const breadcrumbs = computed<BreadcrumbItem[]>(() => {
    const base: BreadcrumbItem[] = [{ title: 'Inventory', href: '/items' }];
    for (const item of props.breadcrumb) {
        base.push({ title: item.name, href: `/items/${item.id}` });
    }
    return base;
});

const createHref = computed(() => (props.parent ? `/items/create?parent=${props.parent.id}` : '/items/create'));
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="pageTitle" />

        <div class="flex flex-col gap-6 p-4 md:p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">{{ pageTitle }}</h1>
                    <p v-if="parent?.description" class="mt-1 text-sm text-muted-foreground">{{ parent.description }}</p>
                </div>
                <div class="flex gap-2">
                    <Link v-if="parent" :href="`/items/${parent.id}`">
                        <Button variant="outline" size="sm">Open</Button>
                    </Link>
                    <Link :href="createHref">
                        <Button size="sm">
                            <Plus class="mr-1 size-4" />
                            Add item
                        </Button>
                    </Link>
                </div>
            </div>

            <div v-if="items.length === 0" class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground">
                Nothing here yet.
                <Link :href="createHref" class="font-medium text-primary underline-offset-4 hover:underline">Add the first item</Link>.
            </div>

            <ul v-else class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <li v-for="item in items" :key="item.id" class="group rounded-lg border bg-card shadow-sm transition hover:shadow">
                    <Link :href="`/items/${item.id}`" class="flex items-start gap-3 p-4">
                        <div class="flex size-10 shrink-0 items-center justify-center rounded-md bg-muted text-muted-foreground">
                            <ItemTypeIcon :type="item.type.value" class="size-5" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center justify-between gap-2">
                                <p class="truncate font-medium">{{ item.name }}</p>
                                <ChevronRight class="size-4 shrink-0 text-muted-foreground transition group-hover:translate-x-0.5" />
                            </div>
                            <p class="mt-0.5 text-xs uppercase tracking-wide text-muted-foreground">
                                {{ item.type.label }}
                                <span v-if="(item.children_count ?? 0) > 0">· {{ item.children_count }} inside</span>
                            </p>
                            <p v-if="item.description" class="mt-1 line-clamp-2 text-sm text-muted-foreground">{{ item.description }}</p>
                            <div v-if="item.tags?.length" class="mt-2 flex flex-wrap gap-1">
                                <TagBadge v-for="tag in item.tags" :key="tag.id" :tag="tag" />
                            </div>
                        </div>
                    </Link>
                </li>
            </ul>

            <div v-if="parent" class="flex justify-end">
                <Link :href="`/items/${parent.id}/edit`">
                    <Button variant="ghost" size="sm">
                        <Pencil class="mr-1 size-4" />
                        Edit {{ parent.name }}
                    </Button>
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
