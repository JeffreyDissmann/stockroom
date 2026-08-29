<script setup lang="ts">
import proposalRoutes from '@/routes/proposals';
import { router } from '@inertiajs/vue3';
import { AlertTriangle, Check, Sparkles, X } from '@lucide/vue';
import { ref } from 'vue';

// One AI suggestion, wherever it is shown. The item page renders it beside the
// field it would change, so the photos that produced it are in view; the queue
// renders the same component so a decision looks and behaves identically in
// both places.
const props = defineProps<{
    id: number;
    proposedValue: string;
    currentValue?: string | null;
    // The item moved on after the suggestion was made. Accepting would undo
    // that edit, so the route refuses it — computed server-side by the same
    // rule, so this badge cannot disagree with what the route will do.
    isStale?: boolean;
    // The item page already shows the current value above; the queue does not.
    showCurrent?: boolean;
}>();

const deciding = ref(false);

function decide(verb: 'accept' | 'reject') {
    deciding.value = true;
    const url = proposalRoutes[verb](props.id).url;
    const options = { preserveScroll: true, onFinish: () => (deciding.value = false) };

    if (verb === 'accept') {
        router.patch(url, {}, options);
    } else {
        router.delete(url, options);
    }
}
</script>

<template>
    <div class="suggestion" :data-test="`suggestion-${id}`">
        <p v-if="isStale" class="suggestion-stale" :data-test="`suggestion-stale-${id}`">
            <AlertTriangle :size="13" class="shrink-0" />
            <span>{{ $t('proposals.stale') }}</span>
        </p>

        <div class="suggestion-body">
            <div v-if="showCurrent" class="suggestion-side">
                <span class="suggestion-label">{{ $t('proposals.current') }}</span>
                <p class="suggestion-value is-current">{{ currentValue || $t('proposals.empty_field') }}</p>
            </div>
            <div class="suggestion-side">
                <span class="suggestion-label">
                    <Sparkles :size="11" class="shrink-0" />
                    {{ $t('proposals.proposed') }}
                </span>
                <p class="suggestion-value">{{ proposedValue }}</p>
            </div>
        </div>

        <div class="suggestion-actions">
            <button type="button" class="btn-ghost" :disabled="deciding" :data-test="`suggestion-reject-${id}`" @click="decide('reject')">
                <X :size="13" />
                {{ $t('proposals.reject') }}
            </button>
            <button
                type="button"
                class="btn-primary"
                :disabled="deciding || isStale"
                :data-test="`suggestion-accept-${id}`"
                @click="decide('accept')"
            >
                <Check :size="13" />
                {{ $t('proposals.accept') }}
            </button>
        </div>
    </div>
</template>

<style scoped>
/* Dashed, like the Paperless document chips — it marks something proposed
   rather than recorded, and the two should read as the same kind of thing. */
.suggestion {
    border: 1px dashed var(--border-strong);
    border-radius: var(--radius-sm);
    background: var(--bg-elev);
    padding: 10px 12px;
}
.suggestion-stale {
    display: flex;
    align-items: flex-start;
    gap: 6px;
    margin: 0 0 8px;
    font-size: 12px;
    color: var(--warn);
}
.suggestion-body {
    display: grid;
    gap: 10px;
    grid-template-columns: 1fr;
}
@media (min-width: 720px) {
    .suggestion-body:has(.suggestion-side + .suggestion-side) {
        grid-template-columns: 1fr 1fr;
    }
}
.suggestion-side {
    min-width: 0;
}
.suggestion-label {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--fg-subtle);
}
.suggestion-value {
    margin: 3px 0 0;
    font-size: 13px;
    line-height: 1.5;
    color: var(--fg);
    white-space: pre-wrap;
}
.suggestion-value.is-current {
    color: var(--fg-muted);
}
.suggestion-actions {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    margin-top: 10px;
}
</style>
