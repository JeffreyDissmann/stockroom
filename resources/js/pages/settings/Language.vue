<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import LanguageTabs from '@/components/LanguageTabs.vue';
import { trans } from '@/composables/useTranslations';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';

defineProps<{ locale: string; locales: Record<string, string> }>();

const breadcrumbItems: BreadcrumbItem[] = [{ title: trans('settings.language.title'), href: '/settings/language' }];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="$t('settings.language.title')" />

        <SettingsLayout>
            <div class="space-y-6">
                <HeadingSmall :title="$t('settings.language.title')" :description="$t('settings.language.description')" />
                <LanguageTabs :locale="locale" :locales="locales" />
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
