<script setup lang="ts">
import ItemForm from '@/components/ItemForm.vue';
import { trans } from '@/composables/useTranslations';
import AppLayout from '@/layouts/AppLayout.vue';
import itemRoutes from '@/routes/items';
import type {
    BreadcrumbItemType,
    CustomFieldDefinition,
    HomeAssistantLinkSummary,
    ItemSummary,
    ItemTypeDescriptor,
    PaperlessLinkSummary,
    TagSummary,
} from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { X } from '@lucide/vue';
import { computed } from 'vue';

const props = defineProps<{
    item: ItemSummary;
    tags: TagSummary[];
    types: ItemTypeDescriptor[];
    customFields: CustomFieldDefinition[];
    batteryTypes: string[];
    lockedTagIds: number[];
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
            <!-- Centered column matching the form's 720px cap — without it
                 the form hugs the left edge of the 1400px-wide .page. -->
            <div class="mx-auto max-w-180">
                <h2 class="m-0 mb-5 text-22 font-semibold tracking-display">
                    {{ $t('items.edit_title', { name: item.name }) }}
                </h2>

                <ItemForm
                    mode="edit"
                    :item="item"
                    :items="[]"
                    :tags="tags"
                    :types="types"
                    :custom-fields="customFields"
                    :battery-types="batteryTypes"
                    :locked-tag-ids="lockedTagIds"
                    :paperless-links="paperlessLinks"
                    :home-assistant-link="homeAssistantLink"
                />
            </div>
        </div>
    </AppLayout>
</template>
