<script setup lang="ts">
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { useAssistant } from '@/composables/useAssistant';
import { trans } from '@/composables/useTranslations';
import { ImagePlus, Loader2, RefreshCw, SendHorizonal, X } from 'lucide-vue-next';
import { nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

interface ChatMessage {
    role: 'user' | 'assistant';
    content: string;
    image?: string;
    imageFailed?: boolean;
}

// Marks that the user reset to a fresh thread but hasn't sent a message yet.
// Persisted so the clean slate survives a page reload (the server otherwise
// rehydrates the previous thread, which is still the user's "latest").
const FRESH_THREAD_KEY = 'assistant:fresh';

const { isOpen, close, toggle } = useAssistant();

const messages = ref<ChatMessage[]>([]);
const conversationId = ref<string | null>(null);
const input = ref('');
const sending = ref(false);
const error = ref<string | null>(null);
const listEl = ref<HTMLElement>();
const fileInput = ref<HTMLInputElement>();
const attachedImage = ref<File | null>(null);
const attachedPreview = ref<string | null>(null);
let loaded = false;

function pickImage() {
    fileInput.value?.click();
}

function onImageSelected(event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;
    attachedImage.value = file;
    attachedPreview.value = null;
    // A data URL keeps the preview self-contained (no object-URL revoke lifecycle).
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

function readXsrfToken(): string {
    const match = document.cookie.split('; ').find((c) => c.startsWith('XSRF-TOKEN='));
    return match ? decodeURIComponent(match.split('=')[1]) : '';
}

async function scrollToEnd() {
    await nextTick();
    if (listEl.value) listEl.value.scrollTop = listEl.value.scrollHeight;
}

// Rehydrate the latest conversation the first time the panel opens.
watch(isOpen, async (open) => {
    if (!open || loaded) return;
    loaded = true;
    // Honour a pending reset: stay empty instead of rehydrating the old thread.
    if (localStorage.getItem(FRESH_THREAD_KEY) === '1') return;
    try {
        const res = await fetch('/assistant/conversation', { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        if (res.ok) {
            const data = await res.json();
            conversationId.value = data.conversation_id ?? null;
            messages.value = data.messages ?? [];
            scrollToEnd();
        }
    } catch {
        // Non-fatal: start with an empty thread.
    }
});

// Start a fresh thread: dropping the conversation id makes the next message
// begin a new conversation. Previous threads stay saved in the database.
function startNewChat() {
    if (sending.value) return;
    messages.value = [];
    conversationId.value = null;
    error.value = null;
    input.value = '';
    clearImage();
    localStorage.setItem(FRESH_THREAD_KEY, '1');
}

async function send() {
    const text = input.value.trim();
    const image = attachedImage.value;
    if ((text === '' && !image) || sending.value) return;

    messages.value.push({ role: 'user', content: text, image: attachedPreview.value ?? undefined });
    input.value = '';
    error.value = null;
    sending.value = true;
    scrollToEnd();

    // Build the request body: multipart when a photo is attached, JSON otherwise.
    let body: BodyInit;
    const headers: Record<string, string> = { Accept: 'application/json', 'X-XSRF-TOKEN': readXsrfToken() };
    if (image) {
        const form = new FormData();
        form.append('message', text);
        if (conversationId.value) form.append('conversation_id', conversationId.value);
        form.append('image', image);
        body = form; // browser sets the multipart Content-Type with boundary
    } else {
        headers['Content-Type'] = 'application/json';
        body = JSON.stringify({ message: text, conversation_id: conversationId.value });
    }
    // Detach from the input without revoking previewUrl (the bubble still uses it).
    attachedImage.value = null;
    attachedPreview.value = null;
    if (fileInput.value) fileInput.value.value = '';

    const controller = new AbortController();
    // Analysing a photo runs an extra vision call, so allow longer.
    const timeout = window.setTimeout(() => controller.abort(), image ? 240_000 : 130_000);

    try {
        const res = await fetch('/assistant/messages', {
            method: 'POST',
            headers,
            credentials: 'same-origin',
            body,
            signal: controller.signal,
        });

        if (!res.ok) throw new Error(String(res.status));

        const data = await res.json();
        conversationId.value = data.conversation_id ?? conversationId.value;
        // A real thread now exists, so the reload should rehydrate it again.
        localStorage.removeItem(FRESH_THREAD_KEY);
        messages.value.push({ role: 'assistant', content: data.reply ?? '' });
    } catch {
        error.value = trans('assistant.error');
    } finally {
        window.clearTimeout(timeout);
        sending.value = false;
        scrollToEnd();
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
                        <div v-if="m.content && m.role === 'assistant'" class="assistant-md" v-html="m.content" />
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
                        class="field flex-1"
                        :placeholder="$t('assistant.placeholder')"
                        :disabled="sending"
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
