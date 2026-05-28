<script setup lang="ts">
import language from '@/routes/language';
import { router } from '@inertiajs/vue3';
import { Languages } from 'lucide-vue-next';

const props = defineProps<{ locale: string; locales: Record<string, string> }>();

function choose(code: string) {
    if (code === props.locale) {
        return;
    }
    // The redirect-back re-runs shared props, so the whole app re-renders in the new language.
    router.patch(language.update().url, { locale: code }, { preserveScroll: true });
}
</script>

<template>
    <div class="inline-flex gap-1 rounded-lg bg-neutral-100 p-1 dark:bg-neutral-800">
        <button
            v-for="(label, code) in locales"
            :key="code"
            type="button"
            @click="choose(code)"
            :class="[
                'flex items-center rounded-md px-3.5 py-1.5 transition-colors',
                locale === code
                    ? 'bg-white shadow-sm dark:bg-neutral-700 dark:text-neutral-100'
                    : 'text-neutral-500 hover:bg-neutral-200/60 hover:text-black dark:text-neutral-400 dark:hover:bg-neutral-700/60',
            ]"
        >
            <Languages class="-ml-1 h-4 w-4" />
            <span class="ml-1.5 text-sm">{{ label }}</span>
        </button>
    </div>
</template>
