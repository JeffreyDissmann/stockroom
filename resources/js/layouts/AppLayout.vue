<script setup lang="ts">
import AssistantPanel from '@/components/AssistantPanel.vue';
import BottomTabs from '@/components/BottomTabs.vue';
import CommandPalette from '@/components/CommandPalette.vue';
import Topbar from '@/components/Topbar.vue';
import TopNav from '@/components/TopNav.vue';
import { useAssistant } from '@/composables/useAssistant';
import type { BreadcrumbItemType, SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { Sparkles } from 'lucide-vue-next';

const aiEnabled = usePage<SharedData>().props.features.ai;
const { open: openAssistant } = useAssistant();

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
        <button
            v-if="aiEnabled"
            type="button"
            class="assistant-fab md:hidden"
            :title="$t('nav.assistant')"
            :aria-label="$t('nav.assistant')"
            data-test="open-assistant-fab"
            @click="openAssistant()"
        >
            <Sparkles :size="22" />
        </button>
    </div>
</template>

<style scoped>
/* Mobile-only floating shortcut to the assistant. Sits above the bottom tabs. */
.assistant-fab {
    position: fixed;
    right: 16px;
    bottom: calc(env(safe-area-inset-bottom, 0px) + 76px);
    width: 48px;
    height: 48px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0;
    border: 0;
    border-radius: 999px;
    background: var(--accent);
    color: var(--accent-fg);
    box-shadow: 0 6px 16px rgba(0, 0, 0, 0.22);
    cursor: pointer;
    z-index: 40;
}
.assistant-fab:active {
    transform: translateY(1px);
}
</style>
