<script setup lang="ts">
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useAssistant } from '@/composables/useAssistant';
import { trans } from '@/composables/useTranslations';
import { activity, dashboard, logout, search } from '@/routes';
import customFields from '@/routes/custom-fields';
import backup from '@/routes/household/backup';
import householdImport from '@/routes/household/import';
import items from '@/routes/items';
import profile from '@/routes/profile';
import tags from '@/routes/tags';
import type { SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { Activity as ActivityIcon, Boxes, Database, Download, LayoutGrid, LogOut, MoreHorizontal, Plus, Search, Settings, SlidersHorizontal, Sparkles, Tag as TagIcon } from 'lucide-vue-next';
import { computed } from 'vue';

const { open: openAssistant } = useAssistant();

const tabs = [
    { label: trans('nav.dashboard'), href: dashboard().url, icon: LayoutGrid, matches: (u: string) => u.startsWith('/dashboard') },
    { label: trans('nav.items'), href: items.index().url, icon: Boxes, matches: (u: string) => u === '/items' || (u.startsWith('/items') && !u.includes('/create')) },
    { label: trans('common.add'), href: items.create().url, icon: Plus, matches: (u: string) => u.startsWith('/items/create') },
    { label: trans('nav.search'), href: search().url, icon: Search, matches: (u: string) => u.startsWith('/search') },
];

const householdLinks = [
    { label: trans('household.nav.custom_fields'), href: customFields.index().url, icon: SlidersHorizontal },
    { label: trans('household.nav.backup'), href: backup.index().url, icon: Database },
    { label: trans('household.nav.import'), href: householdImport.index().url, icon: Download },
];

const page = usePage<SharedData>();
const aiEnabled = page.props.features.ai;
const moreActive = computed(() => /^\/(tags|activity|household|settings)/.test(page.url));
</script>

<template>
    <nav class="bottom-tabs" :aria-label="$t('nav.primary')">
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
                <button type="button" :class="moreActive ? 'active' : ''" :aria-label="$t('common.more')">
                    <MoreHorizontal />
                    {{ $t('common.more') }}
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent side="top" align="end" class="w-56">
                <DropdownMenuItem v-if="aiEnabled" data-test="open-assistant-mobile" @click="openAssistant()">
                    <Sparkles class="mr-2 h-4 w-4" />
                    {{ $t('nav.assistant') }}
                </DropdownMenuItem>
                <DropdownMenuSeparator v-if="aiEnabled" />
                <DropdownMenuItem as-child>
                    <Link class="flex w-full items-center" :href="tags.index().url">
                        <TagIcon class="mr-2 h-4 w-4" />
                        {{ $t('nav.tags') }}
                    </Link>
                </DropdownMenuItem>
                <DropdownMenuItem as-child>
                    <Link class="flex w-full items-center" :href="activity().url">
                        <ActivityIcon class="mr-2 h-4 w-4" />
                        {{ $t('nav.activity') }}
                    </Link>
                </DropdownMenuItem>

                <DropdownMenuSeparator />
                <DropdownMenuLabel>{{ $t('nav.household') }}</DropdownMenuLabel>
                <DropdownMenuItem v-for="link in householdLinks" :key="link.href" as-child>
                    <Link class="flex w-full items-center" :href="link.href">
                        <component :is="link.icon" class="mr-2 h-4 w-4" />
                        {{ link.label }}
                    </Link>
                </DropdownMenuItem>

                <DropdownMenuSeparator />
                <DropdownMenuItem as-child>
                    <Link class="flex w-full items-center" :href="profile.edit().url">
                        <Settings class="mr-2 h-4 w-4" />
                        {{ $t('nav.settings') }}
                    </Link>
                </DropdownMenuItem>
                <DropdownMenuItem as-child>
                    <Link class="flex w-full items-center" method="post" :href="logout().url" as="button">
                        <LogOut class="mr-2 h-4 w-4" />
                        {{ $t('nav.log_out') }}
                    </Link>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    </nav>
</template>
