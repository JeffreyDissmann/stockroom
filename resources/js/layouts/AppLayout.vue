<script setup lang="ts">
import BottomTabs from '@/components/BottomTabs.vue';
import Topbar from '@/components/Topbar.vue';
import TopNav from '@/components/TopNav.vue';
import type { BreadcrumbItemType } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItemType[];
    }>(),
    { breadcrumbs: () => [] },
);
</script>

<template>
    <div class="app">
        <TopNav />
        <main class="app-main">
            <Topbar :breadcrumbs="breadcrumbs">
                <template #actions>
                    <slot name="topbar-actions" />
                </template>
            </Topbar>
            <div class="main-scroll">
                <slot />
            </div>
            <BottomTabs />
        </main>
    </div>
</template>
