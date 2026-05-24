<script setup lang="ts">
import ItemForm from '@/components/ItemForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, ItemSummary, ItemTypeDescriptor, TagSummary } from '@/types';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    item: ItemSummary;
    tags: TagSummary[];
    types: ItemTypeDescriptor[];
}>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Inventory', href: '/items' },
    { title: props.item.name, href: `/items/${props.item.id}` },
    { title: 'Edit', href: `/items/${props.item.id}/edit` },
]);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="`Edit ${item.name}`" />

        <div class="mx-auto flex w-full max-w-2xl flex-col gap-6 p-4 md:p-6">
            <h1 class="text-2xl font-semibold tracking-tight">Edit {{ item.name }}</h1>

            <ItemForm mode="edit" :item="item" :items="[]" :tags="tags" :types="types" />
        </div>
    </AppLayout>
</template>
