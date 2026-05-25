<script setup lang="ts">
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Link, usePage } from '@inertiajs/vue3';
import { Boxes, Database, Download, LayoutGrid, LogOut, MoreHorizontal, Plus, Search, Settings, SlidersHorizontal, Tag as TagIcon } from 'lucide-vue-next';
import { computed } from 'vue';

const tabs = [
    { label: 'Dashboard', href: '/dashboard', icon: LayoutGrid, matches: (u: string) => u.startsWith('/dashboard') },
    { label: 'Items', href: '/items', icon: Boxes, matches: (u: string) => u === '/items' || (u.startsWith('/items') && !u.includes('/create')) },
    { label: 'Add', href: '/items/create', icon: Plus, matches: (u: string) => u.startsWith('/items/create') },
    { label: 'Search', href: '/search', icon: Search, matches: (u: string) => u.startsWith('/search') },
];

const householdLinks = [
    { label: 'Custom fields', href: '/household/custom-fields', icon: SlidersHorizontal },
    { label: 'Backup & restore', href: '/household/backup', icon: Database },
    { label: 'Import from Homebox', href: '/household/import', icon: Download },
];

const page = usePage();
const moreActive = computed(() => /^\/(tags|household|settings)/.test(page.url));
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

        <DropdownMenu>
            <DropdownMenuTrigger as-child>
                <button type="button" :class="moreActive ? 'active' : ''" aria-label="More">
                    <MoreHorizontal />
                    More
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent side="top" align="end" class="w-56">
                <DropdownMenuItem as-child>
                    <Link class="flex w-full items-center" href="/tags">
                        <TagIcon class="mr-2 h-4 w-4" />
                        Tags
                    </Link>
                </DropdownMenuItem>

                <DropdownMenuSeparator />
                <DropdownMenuLabel>Household</DropdownMenuLabel>
                <DropdownMenuItem v-for="link in householdLinks" :key="link.href" as-child>
                    <Link class="flex w-full items-center" :href="link.href">
                        <component :is="link.icon" class="mr-2 h-4 w-4" />
                        {{ link.label }}
                    </Link>
                </DropdownMenuItem>

                <DropdownMenuSeparator />
                <DropdownMenuItem as-child>
                    <Link class="flex w-full items-center" href="/settings/profile">
                        <Settings class="mr-2 h-4 w-4" />
                        Settings
                    </Link>
                </DropdownMenuItem>
                <DropdownMenuItem as-child>
                    <Link class="flex w-full items-center" method="post" :href="route('logout')" as="button">
                        <LogOut class="mr-2 h-4 w-4" />
                        Log out
                    </Link>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    </nav>
</template>
