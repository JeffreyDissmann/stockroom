<script setup lang="ts">
import BackupRestore from '@/components/household/BackupRestore.vue';
import DangerZone from '@/components/household/DangerZone.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import HouseholdLayout from '@/layouts/household/Layout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';

const breadcrumbItems: BreadcrumbItem[] = [{ title: 'Backup & restore', href: '/household/backup' }];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Backup & restore" />

        <HouseholdLayout>
            <BackupRestore />
            <DangerZone />
        </HouseholdLayout>
    </AppLayout>
</template>
