import { trans } from '@/composables/useTranslations';
import { router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

export interface ChatMessage {
    role: 'user' | 'assistant';
    content: string;
    image?: string;
    imageFailed?: boolean;
}

export interface SendAttachment {
    file: File;
    preview: string | null;
}

/**
 * Marks that the user reset to a fresh thread but hasn't sent a message yet,
 * persisted so the clean slate survives a page reload (the server otherwise
 * rehydrates the previous thread, which is still the user's "latest").
 */
const FRESH_THREAD_KEY = 'assistant:fresh';

function readXsrfToken(): string {
    const match = document.cookie.split('; ').find((c) => c.startsWith('XSRF-TOKEN='));
    return match ? decodeURIComponent(match.split('=')[1]) : '';
}

/**
 * The chat lifecycle for the assistant panel: messages, send/rehydrate/reset,
 * conversation id, mutated-page reload + redirect handling. The panel owns
 * DOM-specific concerns (scrolling, input field); this composable feeds it.
 */
export function useAssistantChat(options: { onMessagesChanged?: () => void } = {}) {
    const messages = ref<ChatMessage[]>([]);
    const conversationId = ref<string | null>(null);
    const sending = ref(false);
    const error = ref<string | null>(null);
    let loaded = false;

    const page = usePage();
    const contextItemId = computed<number | null>(() => {
        if (page.component !== 'items/Show') return null;
        return (page.props as { item?: { id?: number } }).item?.id ?? null;
    });

    function notify() {
        options.onMessagesChanged?.();
    }

    /**
     * Rehydrate the latest conversation (the first time the panel opens). A
     * pending reset flag short-circuits this so the panel stays empty until
     * the user actually sends a message.
     */
    async function rehydrate() {
        if (loaded) return;
        loaded = true;
        if (localStorage.getItem(FRESH_THREAD_KEY) === '1') return;
        try {
            const res = await fetch('/assistant/conversation', { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (res.ok) {
                const data = await res.json();
                conversationId.value = data.conversation_id ?? null;
                messages.value = data.messages ?? [];
                notify();
            }
        } catch {
            // Non-fatal: start with an empty thread.
        }
    }

    function startNewChat() {
        if (sending.value) return;
        messages.value = [];
        conversationId.value = null;
        error.value = null;
        localStorage.setItem(FRESH_THREAD_KEY, '1');
    }

    /**
     * Send a turn: pushes the user bubble, posts to the server, appends the
     * reply, and reacts to a write turn by reloading the current item page
     * (or visiting a redirect when the viewed item was deleted).
     */
    async function send(text: string, attachment: SendAttachment | null, close: () => void) {
        if ((text === '' && !attachment) || sending.value) return;

        messages.value.push({ role: 'user', content: text, image: attachment?.preview ?? undefined });
        error.value = null;
        sending.value = true;
        notify();

        let body: BodyInit;
        const headers: Record<string, string> = { Accept: 'application/json', 'X-XSRF-TOKEN': readXsrfToken() };
        if (attachment) {
            const form = new FormData();
            form.append('message', text);
            if (conversationId.value) form.append('conversation_id', conversationId.value);
            if (contextItemId.value) form.append('context_item_id', String(contextItemId.value));
            form.append('image', attachment.file);
            body = form; // browser sets multipart Content-Type with boundary
        } else {
            headers['Content-Type'] = 'application/json';
            body = JSON.stringify({ message: text, conversation_id: conversationId.value, context_item_id: contextItemId.value });
        }

        const controller = new AbortController();
        // Analysing a photo runs an extra vision call, so allow longer.
        const timeout = window.setTimeout(() => controller.abort(), attachment ? 240_000 : 130_000);

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
            localStorage.removeItem(FRESH_THREAD_KEY);
            messages.value.push({ role: 'assistant', content: data.reply ?? '' });
            notify();

            if (data.mutated) {
                if (data.redirect_to) {
                    close();
                    router.visit(data.redirect_to);
                } else if (page.component === 'items/Show') {
                    router.reload();
                }
            }
        } catch {
            error.value = trans('assistant.error');
        } finally {
            window.clearTimeout(timeout);
            sending.value = false;
            notify();
        }
    }

    return { messages, conversationId, sending, error, contextItemId, rehydrate, startNewChat, send };
}
