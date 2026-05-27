<script setup lang="ts">
import BackupRestore from '@/components/household/BackupRestore.vue';
import DangerZone from '@/components/household/DangerZone.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import HouseholdLayout from '@/layouts/household/Layout.vue';
import { trans } from '@/composables/useTranslations';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';

const breadcrumbItems: BreadcrumbItem[] = [{ title: trans('household.nav.backup'), href: '/household/backup' }];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="$t('household.nav.backup')" />

        <HouseholdLayout>
            <BackupRestore />
            <DangerZone />
        </HouseholdLayout>
    </AppLayout>
</template>
