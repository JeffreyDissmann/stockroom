<script setup lang="ts">
defineProps<{
    // Accepts null as well as undefined: callers pass both Inertia's
    // form.errors entries (undefined when clear) and their own refs holding
    // a nullable message, and v-show treats the two identically.
    message?: string | null;
}>();
</script>

<template>
    <div v-show="message">
        <p class="text-sm text-red-600 dark:text-red-500">
            {{ message }}
        </p>
    </div>
</template>
