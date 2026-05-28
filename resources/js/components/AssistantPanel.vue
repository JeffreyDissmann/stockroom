<script setup lang="ts">
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { useAssistant } from '@/composables/useAssistant';
import { useAssistantChat } from '@/composables/useAssistantChat';
import { useAssistantImageAttach } from '@/composables/useAssistantImageAttach';
import { router } from '@inertiajs/vue3';
import { ImagePlus, Loader2, RefreshCw, SendHorizonal, X } from 'lucide-vue-next';
import { nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

const { isOpen, close, toggle } = useAssistant();
const { fileInput, attachedImage, attachedPreview, pickImage, onImageSelected, clearImage } = useAssistantImageAttach();
const { messages, conversationId, sending, error, rehydrate, startNewChat: resetChat, send: sendTurn } = useAssistantChat({
    onMessagesChanged: () => scrollToEnd(),
});

const input = ref('');
const listEl = ref<HTMLElement>();

async function scrollToEnd() {
    await nextTick();
    if (listEl.value) listEl.value.scrollTop = listEl.value.scrollHeight;
}

// Rehydrate the latest conversation the first time the panel opens.
watch(isOpen, (open) => {
    if (open) void rehydrate();
});

function startNewChat() {
    resetChat();
    input.value = '';
    clearImage();
}

async function send() {
    const text = input.value.trim();
    const file = attachedImage.value;
    const preview = attachedPreview.value;

    // Optimistically reset the input + attachment; the bubble keeps the preview.
    input.value = '';
    attachedImage.value = null;
    attachedPreview.value = null;
    if (fileInput.value) fileInput.value.value = '';

    await sendTurn(text, file ? { file, preview } : null, close);
}

// Item links inside a reply are plain <a> (from v-html); route them through
// Inertia for SPA navigation and close the panel instead of a full page load.
function onContentClick(e: MouseEvent) {
    const anchor = (e.target as HTMLElement | null)?.closest('a');
    const href = anchor?.getAttribute('href');
    if (anchor && href && href.startsWith('/items/')) {
        e.preventDefault();
        close();
        router.visit(href);
    }
}

// ⌘⇧A / Ctrl+Shift+A toggles the panel from anywhere (mirrors the palette's ⌘K).
function onShortcut(e: KeyboardEvent) {
    if ((e.metaKey || e.ctrlKey) && e.shiftKey && e.key.toLowerCase() === 'a') {
        e.preventDefault();
        toggle();
    }
}

onMounted(() => window.addEventListener('keydown', onShortcut));
onUnmounted(() => window.removeEventListener('keydown', onShortcut));
</script>

<template>
    <Sheet :open="isOpen" @update:open="(v) => !v && close()">
        <SheetContent side="right" class="flex w-full flex-col gap-0 p-0 sm:max-w-md">
            <SheetHeader class="flex-row items-center gap-1.5 space-y-0 border-b p-4 pr-12">
                <SheetTitle>{{ $t('assistant.title') }}</SheetTitle>
                <button
                    type="button"
                    class="assistant-reset"
                    :title="$t('assistant.new')"
                    :disabled="sending || (messages.length === 0 && conversationId === null)"
                    data-test="assistant-new"
                    @click="startNewChat()"
                >
                    <RefreshCw :size="14" />
                </button>
            </SheetHeader>

            <div ref="listEl" class="flex-1 space-y-3 overflow-y-auto p-4">
                <p v-if="messages.length === 0" class="text-sm" style="color: var(--fg-muted)">{{ $t('assistant.empty') }}</p>

                <div v-for="(m, i) in messages" :key="i" class="flex" :class="m.role === 'user' ? 'justify-end' : 'justify-start'">
                    <div
                        class="max-w-[85%] space-y-2 whitespace-pre-wrap rounded-lg px-3 py-2 text-sm"
                        :style="
                            m.role === 'user'
                                ? { background: 'var(--accent)', color: 'var(--accent-fg)' }
                                : { background: 'var(--bg-sunken)', color: 'var(--fg)' }
                        "
                    >
                        <img v-if="m.image && !m.imageFailed" :src="m.image" alt="" class="max-h-40 rounded-md" @error="m.imageFailed = true" />
                        <span v-else-if="m.imageFailed" class="inline-flex items-center gap-1 text-xs opacity-80">
                            <ImagePlus :size="12" /> {{ $t('assistant.image') }}
                        </span>
                        <!-- Assistant replies arrive as server-sanitised Markdown HTML; user text stays plain. -->
                        <div v-if="m.content && m.role === 'assistant'" class="assistant-md" v-html="m.content" @click="onContentClick" />
                        <span v-else-if="m.content">{{ m.content }}</span>
                    </div>
                </div>

                <div v-if="sending" class="flex items-center gap-2 text-sm" style="color: var(--fg-muted)">
                    <Loader2 :size="14" class="animate-spin" />
                    {{ $t('assistant.thinking') }}
                </div>

                <p v-if="error" class="text-sm" style="color: #dc2626">{{ error }}</p>
            </div>

            <div class="border-t p-3">
                <div v-if="attachedPreview" class="mb-2 flex items-center gap-2">
                    <div class="assistant-attach-preview">
                        <img :src="attachedPreview" alt="" />
                        <button type="button" class="assistant-attach-remove" :title="$t('assistant.remove_image')" @click="clearImage">
                            <X :size="12" />
                        </button>
                    </div>
                </div>

                <form class="flex items-center gap-2" @submit.prevent="send">
                    <input ref="fileInput" type="file" accept="image/*" class="hidden" data-test="assistant-image" @change="onImageSelected" />
                    <button
                        type="button"
                        class="assistant-reset"
                        :title="$t('assistant.attach')"
                        :disabled="sending"
                        data-test="assistant-attach"
                        @click="pickImage"
                    >
                        <ImagePlus :size="16" />
                    </button>
                    <input
                        v-model="input"
                        type="text"
                        class="field assistant-input flex-1"
                        :placeholder="$t('assistant.placeholder')"
                        :disabled="sending"
                        enterkeyhint="send"
                        data-test="assistant-input"
                    />
                    <button
                        type="submit"
                        class="btn-primary"
                        style="height: 32px"
                        :disabled="sending || (!input.trim() && !attachedImage)"
                        :title="$t('assistant.send')"
                    >
                        <SendHorizonal :size="14" />
                    </button>
                </form>
            </div>
        </SheetContent>
    </Sheet>
</template>

<style scoped>
.assistant-reset {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 24px;
    padding: 0;
    border: 0;
    border-radius: 6px;
    background: transparent;
    color: var(--fg-muted);
    cursor: pointer;
}
.assistant-reset:hover:not(:disabled) {
    background: var(--bg-hover);
    color: var(--fg);
}
.assistant-reset:disabled {
    opacity: 0.4;
    cursor: default;
}
.assistant-attach-preview {
    position: relative;
    width: 56px;
    height: 56px;
    border-radius: 8px;
    overflow: hidden;
    border: 1px solid var(--border);
}
.assistant-attach-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
/* iOS Safari auto-zooms inputs below 16px. Bump only on touch devices so the
   desktop input keeps its compact size. */
@media (pointer: coarse) {
    .assistant-input {
        font-size: 16px;
    }
}
.assistant-attach-remove {
    position: absolute;
    top: 2px;
    right: 2px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 16px;
    height: 16px;
    padding: 0;
    border: 0;
    border-radius: 999px;
    background: rgba(0, 0, 0, 0.6);
    color: #fff;
    cursor: pointer;
}

/* Rendered Markdown from the assistant (v-html is unscoped, so use :deep). */
.assistant-md {
    white-space: normal;
}
.assistant-md :deep(> :first-child) {
    margin-top: 0;
}
.assistant-md :deep(> :last-child) {
    margin-bottom: 0;
}
.assistant-md :deep(p) {
    margin: 0.4em 0;
}
.assistant-md :deep(ul),
.assistant-md :deep(ol) {
    margin: 0.4em 0;
    padding-left: 1.25em;
    list-style: revert;
}
.assistant-md :deep(li) {
    margin: 0.15em 0;
}
.assistant-md :deep(a) {
    text-decoration: underline;
}
.assistant-md :deep(strong) {
    font-weight: 600;
}
.assistant-md :deep(code) {
    background: var(--bg-elev);
    padding: 1px 4px;
    border-radius: 4px;
    font-size: 0.85em;
}
.assistant-md :deep(pre) {
    background: var(--bg-elev);
    padding: 8px 10px;
    border-radius: 6px;
    overflow-x: auto;
    margin: 0.4em 0;
}
.assistant-md :deep(pre code) {
    background: transparent;
    padding: 0;
}
.assistant-md :deep(h1),
.assistant-md :deep(h2),
.assistant-md :deep(h3) {
    font-weight: 600;
    font-size: 1em;
    margin: 0.5em 0 0.25em;
}
</style>
