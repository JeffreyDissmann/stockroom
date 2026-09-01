<script setup lang="ts">
/**
 * Paginator links for a Laravel length-aware paginator.
 *
 * Extracted from Activity.vue and Search.vue, which held the same markup twice
 * and had already drifted: one preserved scroll position across pages and the
 * other jumped back to the top. Preserving it is the behaviour kept — being
 * thrown to the top of the page after clicking "next" at the bottom of a long
 * list is the more jarring of the two.
 */
import type { PaginationLink } from '@/types';
import { Link } from '@inertiajs/vue3';

defineProps<{ links: PaginationLink[] }>();
</script>

<template>
    <!-- Three links means previous/1/next — a single page, so nothing to show. -->
    <nav v-if="links.length > 3" class="mt-6 flex flex-wrap justify-center gap-1" data-test="pagination">
        <component
            :is="link.url ? Link : 'span'"
            v-for="(link, i) in links"
            :key="i"
            :href="link.url ?? undefined"
            :preserve-scroll="true"
            :class="['chip', link.active ? 'active' : '', !link.url ? 'pointer-events-none opacity-40' : '']"
        >
            <!-- Laravel renders these labels itself, entities and all. -->
            <span v-html="link.label" />
        </component>
    </nav>
</template>
