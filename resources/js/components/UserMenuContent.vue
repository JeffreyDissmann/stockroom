<script setup lang="ts">
import UserInfo from '@/components/UserInfo.vue';
import { DropdownMenuGroup, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator } from '@/components/ui/dropdown-menu';
import { useAppVersion } from '@/composables/useAppVersion';
import { logout } from '@/routes';
import profile from '@/routes/profile';
import type { User } from '@/types';
import { Link } from '@inertiajs/vue3';
import { LogOut, Settings } from 'lucide-vue-next';

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
    <DropdownMenuGroup>
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
        <div class="px-2 py-1.5 text-xs" style="color: var(--fg-subtle)" data-test="user-menu-version">
            {{ versionLabel }}
        </div>
    </template>
</template>
