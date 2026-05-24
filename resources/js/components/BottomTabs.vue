<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Boxes, LayoutGrid, Plus, Settings, Tag as TagIcon } from 'lucide-vue-next';

const tabs = [
    { label: 'Dashboard', href: '/dashboard', icon: LayoutGrid, matches: (u: string) => u.startsWith('/dashboard') },
    { label: 'Items', href: '/items', icon: Boxes, matches: (u: string) => u === '/items' || (u.startsWith('/items') && !u.includes('/create')) },
    { label: 'Add', href: '/items/create', icon: Plus, matches: (u: string) => u.startsWith('/items/create') },
    { label: 'Tags', href: '/tags', icon: TagIcon, matches: (u: string) => u.startsWith('/tags') },
    { label: 'More', href: '/settings/profile', icon: Settings, matches: (u: string) => u.startsWith('/settings') },
];

const page = usePage();
</script>

<template>
    <nav class="bottom-tabs" aria-label="Primary">
        <Link
            v-for="tab in tabs"
            :key="tab.href"
            :href="tab.href"
            :class="tab.matches(page.url) ? 'active' : ''"
        >
            <component :is="tab.icon" />
            {{ tab.label }}
        </Link>
    </nav>
</template>
