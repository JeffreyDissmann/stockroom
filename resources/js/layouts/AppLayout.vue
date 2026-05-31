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
import { computed } from 'vue';

const page = usePage<SharedData>();
const aiEnabled = page.props.features.ai;
const { open: openAssistant } = useAssistant();

// Mobile assistant FAB only surfaces on the pages where you're actually
// browsing items — Dashboard (the recent-activity feed) and Inventory
// (the tree + per-item Show). Item create / edit forms already have
// their own AI affordance (the photo-analyze button), and a floating
// shortcut on top of a long form just covers fields. Settings /
// Household / Search / Tags get nothing here either; the assistant is
// still one tap away via the bottom-tabs "More" menu.
const showAssistantFab = computed(() => {
    if (!aiEnabled) return false;
    const url = page.url.split('?')[0]; // ignore query string for the route check
    if (url.startsWith('/dashboard') || url.startsWith('/search')) return true;
    if (!url.startsWith('/items')) return false;
    // `/items`, `/items/123` → show; `/items/create` and `/items/123/edit` → hide.
    return !url.endsWith('/create') && !url.endsWith('/edit');
});

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
            v-if="showAssistantFab"
            type="button"
            class="assistant-fab inline-flex items-center justify-center md:hidden"
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
/*
 * Mobile-only floating shortcut to the assistant. Sits above the bottom tabs.
 *
 * We do NOT set `display` here — that's left to the Tailwind utility classes
 * on the element (`inline-flex md:hidden`). Setting `display` in a scoped
 * <style> block would create a `.assistant-fab[data-v-xxx]` rule that beats
 * Tailwind's `.md\:hidden` on specificity, leaking the button onto desktop.
 */
.assistant-fab {
    position: fixed;
    right: 16px;
    bottom: calc(env(safe-area-inset-bottom, 0px) + 76px);
    width: 48px;
    height: 48px;
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
