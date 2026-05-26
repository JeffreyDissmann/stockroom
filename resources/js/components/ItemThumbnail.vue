<script setup lang="ts">
import ItemTypeIcon from '@/components/ItemTypeIcon.vue';
import { itemIconMap } from '@/lib/itemIcons';
import type { ItemSummary } from '@/types';
import { computed } from 'vue';

type Size = 'sm' | 'md' | 'lg';

const props = withDefaults(
    defineProps<{
        item: Pick<ItemSummary, 'name' | 'type' | 'thumb_url' | 'icon'>;
        size?: Size;
    }>(),
    { size: 'md' },
);

// Rooms and containers rarely have a photo — show a chosen icon, else initials.
const isPlace = computed(() => props.item.type.value === 'room' || props.item.type.value === 'container');
const chosenIcon = computed(() => (props.item.icon ? (itemIconMap[props.item.icon] ?? null) : null));

const initials = computed(() => {
    const words = (props.item.name ?? '').trim().split(/\s+/).filter(Boolean);
    return (words.slice(0, 2).map((w) => [...w][0]).join('') || '?').toUpperCase();
});

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

const tileClass = computed(() => ({ sm: 'tile-sm', md: 'tile-md', lg: 'tile-lg' })[props.size]);
</script>

<template>
    <img v-if="item.thumb_url" :src="item.thumb_url" :alt="item.name" loading="lazy" class="thumb-img" />
    <div v-else-if="isPlace" class="thumb-tile" :class="tileClass">
        <component :is="chosenIcon" v-if="chosenIcon" :class="iconClass" />
        <span v-else>{{ initials }}</span>
    </div>
    <ItemTypeIcon v-else :type="item.type.value" :class="iconClass" />
</template>

<style scoped>
.thumb-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}
.thumb-tile {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--bg-sunken);
    color: var(--fg-muted);
    font-weight: 600;
    line-height: 1;
    letter-spacing: -0.01em;
}
.tile-sm {
    font-size: 13px;
}
.tile-md {
    font-size: 18px;
}
.tile-lg {
    font-size: 30px;
}
</style>
