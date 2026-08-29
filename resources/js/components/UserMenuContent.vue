<script setup lang="ts">
import UserInfo from '@/components/UserInfo.vue';
import { DropdownMenuGroup, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator } from '@/components/ui/dropdown-menu';
import { useAppVersion } from '@/composables/useAppVersion';
import { activity, logout } from '@/routes';
import customFields from '@/routes/custom-fields';
import profile from '@/routes/profile';
import type { User } from '@/types';
import { Link } from '@inertiajs/vue3';
import { Activity as ActivityIcon, LogOut, Settings, Warehouse } from '@lucide/vue';

interface Props {
    user: User;
}

defineProps<Props>();

const { show: showVersion, label: versionLabel } = useAppVersion();
</script>

<template>
    <DropdownMenuLabel class="p-0 font-normal">
        <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
            <UserInfo :user="user" :show-email="true" />
        </div>
    </DropdownMenuLabel>
    <DropdownMenuSeparator />
    <!-- Moved out of the top bar, which had grown to eight destinations. Both
         are places you consult rather than act on, and they sit next to
         Settings because that is what Household effectively is. -->
    <DropdownMenuGroup>
        <DropdownMenuItem :as-child="true">
            <Link class="block w-full" :href="activity().url" as="button">
                <ActivityIcon class="mr-2 h-4 w-4" />
                {{ $t('nav.activity') }}
            </Link>
        </DropdownMenuItem>
        <DropdownMenuItem :as-child="true">
            <Link class="block w-full" :href="customFields.index().url" as="button">
                <Warehouse class="mr-2 h-4 w-4" />
                {{ $t('nav.household') }}
            </Link>
        </DropdownMenuItem>
        <DropdownMenuItem :as-child="true">
            <Link class="block w-full" :href="profile.edit().url" as="button">
                <Settings class="mr-2 h-4 w-4" />
                {{ $t('nav.settings') }}
            </Link>
        </DropdownMenuItem>
    </DropdownMenuGroup>
    <DropdownMenuSeparator />
    <DropdownMenuItem :as-child="true">
        <Link class="block w-full" method="post" :href="logout().url" as="button">
            <LogOut class="mr-2 h-4 w-4" />
            {{ $t('nav.log_out') }}
        </Link>
    </DropdownMenuItem>
    <!-- Running build version — empty in dev (no APP_VERSION build arg). -->
    <template v-if="showVersion">
        <DropdownMenuSeparator />
        <div class="px-2 py-1.5 text-xs text-fg-subtle" data-test="user-menu-version">
            {{ versionLabel }}
        </div>
    </template>
</template>
