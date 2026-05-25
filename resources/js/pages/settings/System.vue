<script setup lang="ts">
import BackupRestore from '@/components/settings/BackupRestore.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';

const breadcrumbItems: BreadcrumbItem[] = [{ title: 'System', href: '/settings/system' }];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="System" />

        <SettingsLayout>
            <div class="space-y-12">
                <HeadingSmall title="System" description="Maintenance and data tools for the whole household." />

                <!-- Each subfeature is a self-contained section; add more below over time. -->
                <BackupRestore />
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
