<script setup lang="ts">
import ItemTypeIcon from '@/components/ItemTypeIcon.vue';
import type { ItemSummary } from '@/types';
import { computed } from 'vue';

type Size = 'sm' | 'md' | 'lg';

const props = withDefaults(
    defineProps<{
        item: Pick<ItemSummary, 'name' | 'type' | 'thumb_url'>;
        size?: Size;
    }>(),
    { size: 'md' },
);

const iconClass = computed(() => {
    switch (props.size) {
        case 'sm':
            return 'size-4';
        case 'lg':
            return 'size-1/4';
        default:
            return 'size-2/5';
    }
});
</script>

<template>
    <template v-if="item.thumb_url">
        <img :src="item.thumb_url" :alt="item.name" loading="lazy" class="thumb-img" />
    </template>
    <ItemTypeIcon v-else :type="item.type.value" :class="iconClass" />
</template>

<style scoped>
.thumb-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}
</style>
