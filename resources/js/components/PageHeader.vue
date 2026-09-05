<script setup lang="ts">
/**
 * The title-and-subtitle block every top-level page opens with. Was copied into
 * four pages verbatim, where one had already lost the `sub` class, and was
 * missing altogether from Search — the only page that went straight into its
 * filter bar with nothing naming it.
 *
 * The spacing lives on the wrapper so a header without a subtitle keeps the
 * same gap below it as one with.
 */
defineProps<{ title: string; description?: string }>();
</script>

<template>
    <div class="mb-5">
        <h2 class="m-0 text-22 font-semibold tracking-display">{{ title }}</h2>
        <p v-if="description" class="sub m-0 mt-1 text-13 text-fg-muted">{{ description }}</p>
    </div>
</template>
