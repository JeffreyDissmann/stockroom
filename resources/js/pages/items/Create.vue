<script setup lang="ts">
import ItemForm from '@/components/ItemForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItemType, CustomFieldDefinition, ItemSummary, ItemTypeDescriptor, TagSummary } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { X } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    parent: ItemSummary | null;
    items: ItemSummary[];
    tags: TagSummary[];
    types: ItemTypeDescriptor[];
    customFields: CustomFieldDefinition[];
}>();

const breadcrumbs = computed<BreadcrumbItemType[]>(() => {
    const base: BreadcrumbItemType[] = [{ title: 'Inventory', href: '/items' }];
    if (props.parent) base.push({ title: props.parent.name, href: `/items/${props.parent.id}` });
    base.push({ title: 'New item', href: '/items/create' });
    return base;
});

const cancelHref = computed(() => (props.parent ? `/items/${props.parent.id}` : '/items'));
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Add item" />

        <template #topbar-actions>
            <Link :href="cancelHref" class="btn-ghost">
                <X :size="14" />
                Cancel
            </Link>
        </template>

        <div class="page">
            <div class="mb-5">
                <h2 style="margin: 0; font-size: 22px; font-weight: 600; letter-spacing: -0.015em">Add item</h2>
                <p v-if="parent" style="margin-top: 4px; color: var(--fg-muted); font-size: 13px">
                    Inside <span style="color: var(--fg); font-weight: 500">{{ parent.name }}</span>
                </p>
            </div>

            <ItemForm mode="create" :parent="parent" :items="items" :tags="tags" :types="types" :custom-fields="customFields" />
        </div>
    </AppLayout>
</template>
