<script setup lang="ts">
/**
 * The "Saved." confirmation that fades in beside a form's submit button.
 *
 * The three settings forms each carried this block verbatim, all using a raw
 * `text-neutral-600` that ignores the design tokens and sits at poor contrast
 * against the dark theme. It now uses `text-pos`, the token the household
 * preferences form was already using for the same message.
 */
import { TransitionRoot } from '@headlessui/vue';

withDefaults(defineProps<{ show: boolean; message?: string }>(), { message: undefined });
</script>

<template>
    <TransitionRoot :show="show" enter="transition ease-in-out" enter-from="opacity-0" leave="transition ease-in-out" leave-to="opacity-0">
        <p class="m-0 text-sm text-pos" data-test="saved-indicator">{{ message ?? $t('common.saved') }}</p>
    </TransitionRoot>
</template>
