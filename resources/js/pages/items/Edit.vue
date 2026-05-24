<script setup lang="ts">
import ItemForm from '@/components/ItemForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItemType, ItemSummary, ItemTypeDescriptor, TagSummary } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { X } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    item: ItemSummary;
    tags: TagSummary[];
    types: ItemTypeDescriptor[];
}>();

const breadcrumbs = computed<BreadcrumbItemType[]>(() => [
    { title: 'Inventory', href: '/items' },
    { title: props.item.name, href: `/items/${props.item.id}` },
    { title: 'Edit', href: `/items/${props.item.id}/edit` },
]);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="`Edit ${item.name}`" />

        <template #topbar-actions>
            <Link :href="`/items/${item.id}`" class="btn-ghost">
                <X :size="14" />
                Cancel
            </Link>
        </template>

        <div class="page">
            <h2 style="margin: 0 0 20px; font-size: 22px; font-weight: 600; letter-spacing: -0.015em">Edit {{ item.name }}</h2>

            <ItemForm mode="edit" :item="item" :items="[]" :tags="tags" :types="types" />
        </div>
    </AppLayout>
</template>
