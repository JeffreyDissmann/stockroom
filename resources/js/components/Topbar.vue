<script setup lang="ts">
import type { BreadcrumbItemType } from '@/types';
import { Link } from '@inertiajs/vue3';
import { ChevronRight } from 'lucide-vue-next';

defineProps<{
    breadcrumbs?: BreadcrumbItemType[];
}>();
</script>

<template>
    <div class="topbar">
        <nav class="crumb" :aria-label="$t('common.breadcrumb')">
            <template v-for="(crumb, i) in breadcrumbs ?? []" :key="crumb.href">
                <ChevronRight v-if="i > 0" class="sep" :size="12" />
                <Link v-if="i < (breadcrumbs ?? []).length - 1" :href="crumb.href">{{ crumb.title }}</Link>
                <span v-else class="current">{{ crumb.title }}</span>
            </template>
        </nav>
        <div class="grow" />
        <div class="topbar-actions">
            <slot name="actions" />
        </div>
    </div>
</template>
