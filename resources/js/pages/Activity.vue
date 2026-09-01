<script setup lang="ts">
import ActivityFeed from '@/components/ActivityFeed.vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import { trans } from '@/composables/useTranslations';
import AppLayout from '@/layouts/AppLayout.vue';
import type { ActivityRow, BreadcrumbItemType, Paginated } from '@/types';
import { Head } from '@inertiajs/vue3';

defineProps<{ activities: Paginated<ActivityRow> }>();

const breadcrumbs: BreadcrumbItemType[] = [{ title: trans('activity.title'), href: '/activity' }];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="$t('activity.title')" />

        <div class="page">
            <PageHeader :title="$t('activity.title')" :description="$t('activity.subtitle')" />

            <EmptyState v-if="activities.data.length === 0">{{ $t('activity.empty') }}</EmptyState>

            <template v-else>
                <ActivityFeed :rows="activities.data" />

                <Pagination :links="activities.links" />
            </template>
        </div>
    </AppLayout>
</template>
