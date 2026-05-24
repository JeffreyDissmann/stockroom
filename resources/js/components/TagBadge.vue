<script setup lang="ts">
import type { TagSummary } from '@/types';
import { computed } from 'vue';

const props = defineProps<{
    tag: TagSummary;
}>();

const swatchStyle = computed(() => (props.tag.color ? { backgroundColor: props.tag.color } : undefined));
</script>

<template>
    <span
        class="inline-flex items-center gap-1.5 rounded-full border border-border bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground"
    >
        <span v-if="tag.color" class="size-2 rounded-full" :style="swatchStyle" />
        {{ tag.name }}
    </span>
</template>
