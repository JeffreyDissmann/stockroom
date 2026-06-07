<script setup lang="ts">
import { FileText, X } from 'lucide-vue-next';

// Inline "Document says: X — apply?" chip under a form field whose current
// value CONFLICTS with what a linked Paperless document proposes. Empty
// fields are filled directly (with the AI badge); overriding an existing
// value is always this explicit per-field decision.
defineProps<{
    field: string;
    value: string | number | null | undefined;
}>();

defineEmits<{ apply: []; dismiss: [] }>();
</script>

<template>
    <div v-if="value != null" class="doc-proposal" :data-test="`doc-proposal-${field}`">
        <FileText :size="12" class="shrink-0" :style="{ color: 'var(--fg-muted)' }" />
        <span class="doc-proposal-text">{{ $t('items.form.document_says', { value: String(value) }) }}</span>
        <button type="button" class="doc-proposal-apply" :data-test="`doc-proposal-apply-${field}`" @click="$emit('apply')">
            {{ $t('items.form.apply') }}
        </button>
        <button type="button" class="doc-proposal-dismiss" :aria-label="$t('common.cancel')" @click="$emit('dismiss')">
            <X :size="12" />
        </button>
    </div>
</template>

<style scoped>
.doc-proposal {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-top: 4px;
    padding: 4px 8px;
    border: 1px dashed var(--border);
    border-radius: var(--radius-sm);
    background: var(--bg-elev);
    font-size: 12px;
}
.doc-proposal-text {
    flex: 1;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    color: var(--fg-muted);
}
.doc-proposal-apply {
    border: 0;
    background: transparent;
    color: var(--accent);
    font-size: 12px;
    font-weight: 500;
    cursor: pointer;
    padding: 0 2px;
    flex-shrink: 0;
}
.doc-proposal-dismiss {
    border: 0;
    background: transparent;
    color: var(--fg-muted);
    cursor: pointer;
    padding: 0;
    display: inline-flex;
    flex-shrink: 0;
}
</style>
