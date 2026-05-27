<script setup lang="ts">
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { useAssistant } from '@/composables/useAssistant';
import { trans } from '@/composables/useTranslations';
import { Loader2, SendHorizonal } from 'lucide-vue-next';
import { nextTick, ref, watch } from 'vue';

interface ChatMessage {
    role: 'user' | 'assistant';
    content: string;
}

const { isOpen, close } = useAssistant();

const messages = ref<ChatMessage[]>([]);
const conversationId = ref<string | null>(null);
const input = ref('');
const sending = ref(false);
const error = ref<string | null>(null);
const listEl = ref<HTMLElement>();
let loaded = false;

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

async function send() {
    const text = input.value.trim();
    if (text === '' || sending.value) return;

    messages.value.push({ role: 'user', content: text });
    input.value = '';
    error.value = null;
    sending.value = true;
    scrollToEnd();

    const controller = new AbortController();
    const timeout = window.setTimeout(() => controller.abort(), 130_000);

    try {
        const res = await fetch('/assistant/messages', {
            method: 'POST',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-XSRF-TOKEN': readXsrfToken() },
            credentials: 'same-origin',
            body: JSON.stringify({ message: text, conversation_id: conversationId.value }),
            signal: controller.signal,
        });

        if (!res.ok) throw new Error(String(res.status));

        const data = await res.json();
        conversationId.value = data.conversation_id ?? conversationId.value;
        messages.value.push({ role: 'assistant', content: data.reply ?? '' });
    } catch {
        error.value = trans('assistant.error');
    } finally {
        window.clearTimeout(timeout);
        sending.value = false;
        scrollToEnd();
    }
}
</script>

<template>
    <Sheet :open="isOpen" @update:open="(v) => !v && close()">
        <SheetContent side="right" class="flex w-full flex-col gap-0 p-0 sm:max-w-md">
            <SheetHeader class="border-b p-4">
                <SheetTitle>{{ $t('assistant.title') }}</SheetTitle>
            </SheetHeader>

            <div ref="listEl" class="flex-1 space-y-3 overflow-y-auto p-4">
                <p v-if="messages.length === 0" class="text-sm" style="color: var(--fg-muted)">{{ $t('assistant.empty') }}</p>

                <div v-for="(m, i) in messages" :key="i" class="flex" :class="m.role === 'user' ? 'justify-end' : 'justify-start'">
                    <div
                        class="max-w-[85%] whitespace-pre-wrap rounded-lg px-3 py-2 text-sm"
                        :style="
                            m.role === 'user'
                                ? { background: 'var(--accent)', color: 'var(--accent-fg)' }
                                : { background: 'var(--bg-sunken)', color: 'var(--fg)' }
                        "
                    >
                        {{ m.content }}
                    </div>
                </div>

                <div v-if="sending" class="flex items-center gap-2 text-sm" style="color: var(--fg-muted)">
                    <Loader2 :size="14" class="animate-spin" />
                    {{ $t('assistant.thinking') }}
                </div>

                <p v-if="error" class="text-sm" style="color: #dc2626">{{ error }}</p>
            </div>

            <form class="flex items-center gap-2 border-t p-3" @submit.prevent="send">
                <input
                    v-model="input"
                    type="text"
                    class="field flex-1"
                    :placeholder="$t('assistant.placeholder')"
                    :disabled="sending"
                    data-test="assistant-input"
                />
                <button type="submit" class="btn-primary" style="height: 32px" :disabled="sending || !input.trim()" :title="$t('assistant.send')">
                    <SendHorizonal :size="14" />
                </button>
            </form>
        </SheetContent>
    </Sheet>
</template>
