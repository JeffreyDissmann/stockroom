import { ref } from 'vue';

// Module-level singleton so any component (nav triggers, the palette itself)
// shares one open/close state.
const isOpen = ref(false);

export function useCommandPalette() {
    return {
        isOpen,
        open: () => (isOpen.value = true),
        close: () => (isOpen.value = false),
        toggle: () => (isOpen.value = !isOpen.value),
    };
}
