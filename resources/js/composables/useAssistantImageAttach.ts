import { ref } from 'vue';

/**
 * State + handlers for the assistant panel's image attachment slot. Reads the
 * chosen file as a data URL so the preview is self-contained (no object-URL
 * revoke lifecycle to manage). Pair with a hidden <input ref="fileInput">.
 */
export function useAssistantImageAttach() {
    const fileInput = ref<HTMLInputElement>();
    const attachedImage = ref<File | null>(null);
    const attachedPreview = ref<string | null>(null);

    function pickImage() {
        fileInput.value?.click();
    }

    function onImageSelected(event: Event) {
        const file = (event.target as HTMLInputElement).files?.[0] ?? null;
        attachedImage.value = file;
        attachedPreview.value = null;
        if (file) {
            const reader = new FileReader();
            reader.onload = () => {
                attachedPreview.value = typeof reader.result === 'string' ? reader.result : null;
            };
            reader.readAsDataURL(file);
        }
    }

    function clearImage() {
        attachedImage.value = null;
        attachedPreview.value = null;
        if (fileInput.value) fileInput.value.value = '';
    }

    return { fileInput, attachedImage, attachedPreview, pickImage, onImageSelected, clearImage };
}
