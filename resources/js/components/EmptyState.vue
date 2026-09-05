<script setup lang="ts">
/**
 * The "nothing here yet" card. Every list in the app had its own copy of these
 * classes, and they had drifted into two sizes for no reason anyone could point
 * at — a container's contents rendered a size smaller than the inventory list
 * showing the same kind of message.
 */
</script>

<template>
    <div class="card card-pad text-center text-fg-muted" data-test="empty-state">
        <slot />
    </div>
</template>
