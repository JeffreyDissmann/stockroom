<script setup lang="ts">
import type { ItemTypeValue } from '@/types';
import { Box, Home, Package } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    type: ItemTypeValue;
    class?: string;
}>();

const component = computed(() => {
    switch (props.type) {
        case 'room':
            return Home;
        case 'container':
            return Box;
        case 'item':
        default:
            return Package;
    }
});
</script>

<template>
    <component :is="component" :class="props.class" />
</template>
