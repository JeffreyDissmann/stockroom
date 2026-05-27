import { ref } from 'vue';

// Shared open/close state for the global assistant slide-over (mirrors useCommandPalette).
const isOpen = ref(false);

export function useAssistant() {
    return {
        isOpen,
        open: () => (isOpen.value = true),
        close: () => (isOpen.value = false),
        toggle: () => (isOpen.value = !isOpen.value),
    };
}
