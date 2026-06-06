<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { useAssistant } from '@/composables/useAssistant';
import { useCommandPalette } from '@/composables/useCommandPalette';
import { trans } from '@/composables/useTranslations';
import { maintenance } from '@/routes';
import type { SharedData, User } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { Activity as ActivityIcon, Boxes, LayoutGrid, Search, Sparkles, Tag as TagIcon, Warehouse, Wrench } from 'lucide-vue-next';
import { computed } from 'vue';

const { open } = useCommandPalette();
const { open: openAssistant } = useAssistant();

interface NavLink {
    label: string;
    href: string;
    icon: typeof Boxes;
    matches: (url: string) => boolean;
}

const primary: NavLink[] = [
    { label: trans('nav.dashboard'), href: '/dashboard', icon: LayoutGrid, matches: (u) => u.startsWith('/dashboard') },
    { label: trans('nav.inventory'), href: '/items', icon: Boxes, matches: (u) => u.startsWith('/items') },
    { label: trans('nav.search'), href: '/search', icon: Search, matches: (u) => u.startsWith('/search') },
    { label: trans('nav.tags'), href: '/tags', icon: TagIcon, matches: (u) => u.startsWith('/tags') },
];

const secondary: NavLink[] = [
    { label: trans('nav.maintenance'), href: maintenance().url, icon: Wrench, matches: (u) => u.startsWith('/maintenance') },
    { label: trans('nav.activity'), href: '/activity', icon: ActivityIcon, matches: (u) => u.startsWith('/activity') },
    { label: trans('nav.household'), href: '/household/custom-fields', icon: Warehouse, matches: (u) => u.startsWith('/household') },
];

const page = usePage<SharedData>();
const user = computed<User | null>(() => page.props.auth?.user ?? null);
const aiEnabled = page.props.features.ai;

function initials(name: string): string {
    return name
        .split(/\s+/)
        .map((p) => p[0] ?? '')
        .slice(0, 2)
        .join('')
        .toUpperCase();
}
</script>

<template>
    <header class="topnav">
        <Link href="/" class="topnav-logo">
            <AppLogoIcon class-name="size-3.5" />
        </Link>
        <!-- Wordmark drops first when the row gets tight (the logo already
             names the app). Below `lg` (1024px) it hides; the logo + the
             nav icons carry the brand. -->
        <span class="topnav-house hidden lg:inline">Stockroom</span>
        <Link
            v-for="link in primary"
            :key="link.href"
            :href="link.href"
            :class="['topnav-item', link.matches(page.url) ? 'active' : '']"
            :title="link.label"
        >
            <component :is="link.icon" />
            <!-- Below `xl` (1280px) we show icons only and rely on the
                 `title` attribute for the tooltip. The labels eat the most
                 horizontal space on a row this dense, so they're first to
                 collapse before anything moves into a menu. -->
            <span class="hidden lg:inline">{{ link.label }}</span>
        </Link>
        <div class="topnav-spacer" />
        <button type="button" class="topnav-search" :title="$t('nav.search')" data-test="open-search" @click="open()">
            <Search :size="14" />
            <span class="hidden lg:inline">{{ $t('nav.search') }}</span>
            <kbd class="hidden lg:inline-flex">⌘K</kbd>
        </button>
        <button v-if="aiEnabled" type="button" class="topnav-item" :title="$t('nav.assistant')" data-test="open-assistant" @click="openAssistant()">
            <Sparkles :size="16" />
            <span class="hidden lg:inline">{{ $t('nav.assistant') }}</span>
            <kbd class="hidden lg:inline-flex">⌘⇧A</kbd>
        </button>
        <Link
            v-for="link in secondary"
            :key="link.href"
            :href="link.href"
            :class="['topnav-item', link.matches(page.url) ? 'active' : '']"
            :title="link.label"
        >
            <component :is="link.icon" />
            <span class="hidden lg:inline">{{ link.label }}</span>
        </Link>
        <DropdownMenu v-if="user">
            <DropdownMenuTrigger as-child>
                <button type="button" class="av" :title="user.name" data-test="user-menu">{{ initials(user.name) }}</button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" class="w-56">
                <UserMenuContent :user="user" />
            </DropdownMenuContent>
        </DropdownMenu>
    </header>
</template>

<style scoped>
.topnav-search {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 5px 10px;
    margin-right: 4px;
    border: 1px solid var(--border);
    border-radius: 999px;
    background: var(--bg-elev);
    color: var(--fg-muted);
    font-size: 12.5px;
    cursor: pointer;
}
.topnav-search:hover {
    border-color: var(--border-strong);
    color: var(--fg);
}
.topnav-search kbd,
.topnav-item kbd {
    font-size: 10.5px;
    border: 1px solid var(--border);
    border-radius: 4px;
    padding: 1px 5px;
    color: var(--fg-subtle);
    font-family: inherit;
}
.av {
    margin-left: 8px;
    width: 26px;
    height: 26px;
    border: 0;
    padding: 0;
    cursor: pointer;
    border-radius: 999px;
    background: var(--accent);
    color: var(--accent-fg);
    display: grid;
    place-items: center;
    font-size: 11px;
    font-weight: 600;
}
</style>
