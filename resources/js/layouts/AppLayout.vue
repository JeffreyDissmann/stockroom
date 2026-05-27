<script setup lang="ts">
import AssistantPanel from '@/components/AssistantPanel.vue';
import BottomTabs from '@/components/BottomTabs.vue';
import CommandPalette from '@/components/CommandPalette.vue';
import Topbar from '@/components/Topbar.vue';
import TopNav from '@/components/TopNav.vue';
import type { BreadcrumbItemType, SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';

const aiEnabled = usePage<SharedData>().props.features.ai;

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
        <CommandPalette />
        <AssistantPanel v-if="aiEnabled" />
    </div>
</template>
