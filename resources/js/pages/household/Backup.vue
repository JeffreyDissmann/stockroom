<script setup lang="ts">
import BackupRestore from '@/components/household/BackupRestore.vue';
import DangerZone from '@/components/household/DangerZone.vue';
import HomeboxImport from '@/components/household/HomeboxImport.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import HouseholdLayout from '@/layouts/household/Layout.vue';
import { trans } from '@/composables/useTranslations';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';

interface HomeboxStatus {
    state: 'running' | 'done' | 'failed';
    done?: number;
    total?: number;
    entities?: number;
    images?: number;
    imagesSkipped?: number;
    created?: number;
    updated?: number;
    error?: string;
}

defineProps<{ status: HomeboxStatus | null }>();

const breadcrumbItems: BreadcrumbItem[] = [{ title: trans('household.nav.backup'), href: '/household/backup' }];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="$t('household.nav.backup')" />

        <HouseholdLayout>
            <BackupRestore />
            <HomeboxImport :status="status" />
            <DangerZone />
        </HouseholdLayout>
    </AppLayout>
</template>
