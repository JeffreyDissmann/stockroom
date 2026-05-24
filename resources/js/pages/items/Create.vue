<script setup lang="ts">
import ItemForm from '@/components/ItemForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, ItemSummary, ItemTypeDescriptor, TagSummary } from '@/types';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    parent: ItemSummary | null;
    items: ItemSummary[];
    tags: TagSummary[];
    types: ItemTypeDescriptor[];
}>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => {
    const base: BreadcrumbItem[] = [{ title: 'Inventory', href: '/items' }];
    if (props.parent) {
        base.push({ title: props.parent.name, href: `/items/${props.parent.id}` });
    }
    base.push({ title: 'New item', href: '/items/create' });
    return base;
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Add item" />

        <div class="mx-auto flex w-full max-w-2xl flex-col gap-6 p-4 md:p-6">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Add item</h1>
                <p v-if="parent" class="mt-1 text-sm text-muted-foreground">
                    Inside <span class="font-medium">{{ parent.name }}</span>
                </p>
            </div>

            <ItemForm mode="create" :parent="parent" :items="items" :tags="tags" :types="types" />
        </div>
    </AppLayout>
</template>
