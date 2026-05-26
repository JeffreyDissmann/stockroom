<script setup lang="ts">
import { useSwipe } from '@vueuse/core';
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps<{ thumbs: string[]; alt: string }>();

const index = ref(0);
const el = ref<HTMLElement | null>(null);

function go(target: number): void {
    const len = props.thumbs.length;
    index.value = ((target % len) + len) % len;
}

// Arrows/dots live inside a card-wide <Link>, so stop the click from navigating.
function step(delta: number, event: Event): void {
    event.preventDefault();
    event.stopPropagation();
    go(index.value + delta);
}

function select(i: number, event: Event): void {
    event.preventDefault();
    event.stopPropagation();
    go(i);
}

useSwipe(el, {
    onSwipeEnd(_event, direction) {
        if (direction === 'left') go(index.value + 1);
        else if (direction === 'right') go(index.value - 1);
    },
});
</script>

<template>
    <div ref="el" class="cc">
        <img :src="thumbs[index]" :alt="alt" loading="lazy" class="cc-img" />
        <button type="button" class="cc-nav cc-prev" aria-label="Previous image" @click="step(-1, $event)">
            <ChevronLeft :size="16" />
        </button>
        <button type="button" class="cc-nav cc-next" aria-label="Next image" @click="step(1, $event)">
            <ChevronRight :size="16" />
        </button>
        <div class="cc-dots">
            <button
                v-for="(thumb, i) in thumbs"
                :key="i"
                type="button"
                class="cc-dot"
                :class="{ 'is-active': i === index }"
                :aria-label="`Image ${i + 1}`"
                @click="select(i, $event)"
            />
        </div>
    </div>
</template>

<style scoped>
.cc {
    position: relative;
    width: 100%;
    height: 100%;
}
.cc-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}
.cc-nav {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    display: grid;
    place-items: center;
    width: 26px;
    height: 26px;
    border: 0;
    border-radius: 999px;
    background: rgba(0, 0, 0, 0.45);
    color: #fff;
    cursor: pointer;
    opacity: 0;
    transition: opacity 0.12s;
}
.cc-prev {
    left: 6px;
}
.cc-next {
    right: 6px;
}
.cc:hover .cc-nav {
    opacity: 1;
}
.cc-nav:hover {
    background: rgba(0, 0, 0, 0.65);
}
/* Touch devices swipe instead of using arrows. */
@media (hover: none) {
    .cc-nav {
        display: none;
    }
}
/* Dots sit on a dark pill so they read clearly on light or dark images. */
.cc-dots {
    position: absolute;
    bottom: 6px;
    left: 50%;
    transform: translateX(-50%);
    display: flex;
    gap: 5px;
    padding: 4px 7px;
    border-radius: 999px;
    background: rgba(0, 0, 0, 0.4);
}
.cc-dot {
    width: 6px;
    height: 6px;
    border-radius: 999px;
    border: 0;
    padding: 0;
    background: rgba(255, 255, 255, 0.45);
    cursor: pointer;
}
.cc-dot.is-active {
    background: #fff;
}
</style>
