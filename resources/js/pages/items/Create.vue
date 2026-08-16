<script setup lang="ts">
import ItemForm from '@/components/ItemForm.vue';
import { trans } from '@/composables/useTranslations';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItemType, CustomFieldDefinition, ItemSummary, ItemTypeDescriptor, TagSummary } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { X } from '@lucide/vue';
import { computed } from 'vue';

const props = defineProps<{
    parent: ItemSummary | null;
    items: ItemSummary[];
    tags: TagSummary[];
    types: ItemTypeDescriptor[];
    customFields: CustomFieldDefinition[];
    batteryTypes: string[];
}>();

const breadcrumbs = computed<BreadcrumbItemType[]>(() => {
    const base: BreadcrumbItemType[] = [{ title: trans('items.inventory'), href: '/items' }];
    if (props.parent) base.push({ title: props.parent.name, href: `/items/${props.parent.id}` });
    base.push({ title: trans('items.new_item'), href: '/items/create' });
    return base;
});

const cancelHref = computed(() => (props.parent ? `/items/${props.parent.id}` : '/items'));
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="$t('items.add_title')" />

        <template #topbar-actions>
            <Link :href="cancelHref" class="btn-ghost">
                <X :size="14" />
                {{ $t('common.cancel') }}
            </Link>
        </template>

        <div class="page">
            <!-- Centered column matching the form's 720px cap — same as
                 Edit.vue, so create and edit share their layout. -->
            <div style="max-width: 720px; margin: 0 auto">
                <div class="mb-5">
                    <h2 class="m-0 text-22 font-semibold tracking-display">{{ $t('items.add_title') }}</h2>
                    <p class="mt-1 text-13 text-fg-muted" v-if="parent">
                        {{ $t('items.inside') }} <span class="font-medium text-fg">{{ parent.name }}</span>
                    </p>
                </div>

                <ItemForm
                    mode="create"
                    :parent="parent"
                    :items="items"
                    :tags="tags"
                    :types="types"
                    :custom-fields="customFields"
                    :battery-types="batteryTypes"
                />
            </div>
        </div>
    </AppLayout>
</template>
