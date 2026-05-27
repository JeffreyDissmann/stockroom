<script setup lang="ts">
import ItemForm from '@/components/ItemForm.vue';
import { trans } from '@/composables/useTranslations';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItemType, CustomFieldDefinition, ItemSummary, ItemTypeDescriptor, TagSummary } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { X } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    item: ItemSummary;
    tags: TagSummary[];
    types: ItemTypeDescriptor[];
    customFields: CustomFieldDefinition[];
}>();

const breadcrumbs = computed<BreadcrumbItemType[]>(() => [
    { title: trans('items.inventory'), href: '/items' },
    { title: props.item.name, href: `/items/${props.item.id}` },
    { title: trans('items.edit_breadcrumb'), href: `/items/${props.item.id}/edit` },
]);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="$t('items.edit_title', { name: item.name })" />

        <template #topbar-actions>
            <Link :href="`/items/${item.id}`" class="btn-ghost">
                <X :size="14" />
                {{ $t('common.cancel') }}
            </Link>
        </template>

        <div class="page">
            <h2 style="margin: 0 0 20px; font-size: 22px; font-weight: 600; letter-spacing: -0.015em">{{ $t('items.edit_title', { name: item.name }) }}</h2>

            <ItemForm mode="edit" :item="item" :items="[]" :tags="tags" :types="types" :custom-fields="customFields" />
        </div>
    </AppLayout>
</template>
