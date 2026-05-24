<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import type { SharedData, User } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { Boxes, LayoutGrid, Settings, Tag as TagIcon } from 'lucide-vue-next';
import { computed } from 'vue';

interface NavLink {
    label: string;
    href: string;
    icon: typeof Boxes;
    matches: (url: string) => boolean;
}

const primary: NavLink[] = [
    { label: 'Dashboard', href: '/dashboard', icon: LayoutGrid, matches: (u) => u.startsWith('/dashboard') },
    { label: 'Inventory', href: '/items', icon: Boxes, matches: (u) => u.startsWith('/items') },
    { label: 'Tags', href: '/tags', icon: TagIcon, matches: (u) => u.startsWith('/tags') },
];

const secondary: NavLink[] = [
    { label: 'Settings', href: '/settings/profile', icon: Settings, matches: (u) => u.startsWith('/settings') },
];

const page = usePage<SharedData>();
const user = computed<User | null>(() => page.props.auth?.user ?? null);

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
        <span class="topnav-house">Stockroom</span>
        <Link
            v-for="link in primary"
            :key="link.href"
            :href="link.href"
            :class="['topnav-item', link.matches(page.url) ? 'active' : '']"
        >
            <component :is="link.icon" />
            <span>{{ link.label }}</span>
        </Link>
        <div class="topnav-spacer" />
        <Link
            v-for="link in secondary"
            :key="link.href"
            :href="link.href"
            :class="['topnav-item', link.matches(page.url) ? 'active' : '']"
        >
            <component :is="link.icon" />
            <span>{{ link.label }}</span>
        </Link>
        <span v-if="user" class="av" :title="user.name">{{ initials(user.name) }}</span>
    </header>
</template>

<style scoped>
.av {
    margin-left: 8px;
    width: 26px;
    height: 26px;
    border-radius: 999px;
    background: var(--accent);
    color: var(--accent-fg);
    display: grid;
    place-items: center;
    font-size: 11px;
    font-weight: 600;
}
</style>
