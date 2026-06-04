<script setup lang="ts">
import ItemForm from '@/components/ItemForm.vue';
import { trans } from '@/composables/useTranslations';
import AppLayout from '@/layouts/AppLayout.vue';
import itemRoutes from '@/routes/items';
import type { BreadcrumbItemType, CustomFieldDefinition, ItemSummary, ItemTypeDescriptor, TagSummary } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { X } from 'lucide-vue-next';
import { computed } from 'vue';

interface PaperlessLinkSummary {
    document_id: number;
    url: string;
}

interface HomeAssistantLinkSummary {
    entity_id: string;
    friendly_name: string | null;
    url: string | null;
}

const props = defineProps<{
    item: ItemSummary;
    tags: TagSummary[];
    types: ItemTypeDescriptor[];
    customFields: CustomFieldDefinition[];
    paperlessLinks: PaperlessLinkSummary[];
    homeAssistantLink: HomeAssistantLinkSummary | null;
}>();

const breadcrumbs = computed<BreadcrumbItemType[]>(() => [
    { title: trans('items.inventory'), href: itemRoutes.index().url },
    { title: props.item.name, href: itemRoutes.show(props.item.id).url },
    { title: trans('items.edit_breadcrumb'), href: itemRoutes.edit(props.item.id).url },
]);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="$t('items.edit_title', { name: item.name })" />

        <template #topbar-actions>
            <Link :href="itemRoutes.show(item.id).url" class="btn-ghost">
                <X :size="14" />
                {{ $t('common.cancel') }}
            </Link>
        </template>

        <div class="page">
            <h2 style="margin: 0 0 20px; font-size: 22px; font-weight: 600; letter-spacing: -0.015em">{{ $t('items.edit_title', { name: item.name }) }}</h2>

            <ItemForm
                mode="edit"
                :item="item"
                :items="[]"
                :tags="tags"
                :types="types"
                :custom-fields="customFields"
                :paperless-links="paperlessLinks"
                :home-assistant-link="homeAssistantLink"
            />
        </div>
    </AppLayout>
</template>
