<script setup lang="ts">
import ActivityFeed from '@/components/ActivityFeed.vue';
import { trans } from '@/composables/useTranslations';
import AppLayout from '@/layouts/AppLayout.vue';
import type { ActivityRow, BreadcrumbItemType } from '@/types';
import { Head, Link } from '@inertiajs/vue3';

interface Paginated<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
}

defineProps<{ activities: Paginated<ActivityRow> }>();

const breadcrumbs: BreadcrumbItemType[] = [{ title: trans('activity.title'), href: '/activity' }];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="$t('activity.title')" />

        <div class="page">
            <h2 class="m-0 mb-1 text-22 font-semibold tracking-display">{{ $t('activity.title') }}</h2>
            <p class="sub m-0 mb-5 text-13 text-fg-muted">
                {{ $t('activity.subtitle') }}
            </p>

            <div v-if="activities.data.length === 0" class="card card-pad text-center text-fg-muted">
                {{ $t('activity.empty') }}
            </div>

            <template v-else>
                <ActivityFeed :rows="activities.data" />

                <nav v-if="activities.links.length > 3" class="mt-6 flex flex-wrap justify-center gap-1">
                    <component
                        :is="link.url ? Link : 'span'"
                        v-for="(link, i) in activities.links"
                        :key="i"
                        :href="link.url ?? undefined"
                        :class="['chip', link.active ? 'active' : '', !link.url ? 'pointer-events-none opacity-40' : '']"
                    >
                        <span v-html="link.label" />
                    </component>
                </nav>
            </template>
        </div>
    </AppLayout>
</template>
