<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({ inheritAttrs: false });

defineProps<{ className?: HTMLAttributes['class'] }>();
</script>

<template>
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" :class="className" v-bind="$attrs" aria-hidden="true">
        <rect x="3.5" y="3.5" width="13" height="5.5" rx="1" fill="currentColor" />
        <rect x="7.5" y="9.5" width="13" height="5" rx="1" fill="currentColor" opacity=".55" />
        <rect x="3.5" y="15" width="13" height="5.5" rx="1" fill="currentColor" />
    </svg>
</template>
