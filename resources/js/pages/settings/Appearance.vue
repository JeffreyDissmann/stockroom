<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

import AppearanceTabs from '@/components/AppearanceTabs.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { trans } from '@/composables/useTranslations';
import { type BreadcrumbItem } from '@/types';

import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: trans('settings.appearance.title'),
        href: '/settings/appearance',
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="$t('settings.appearance.title')" />

        <SettingsLayout>
            <div class="space-y-6">
                <HeadingSmall :title="$t('settings.appearance.title')" :description="$t('settings.appearance.description')" />
                <AppearanceTabs />
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
