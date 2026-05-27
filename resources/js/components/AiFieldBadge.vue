<script setup lang="ts">
import { Loader2, Sparkles } from 'lucide-vue-next';

// 'pending'   — the model is still analysing; a value may be filled in shortly.
// 'suggested' — the model filled this field; the user should review it.
defineProps<{ state: 'pending' | 'suggested' | null }>();
</script>

<template>
    <span v-if="state" class="ai-badge" :class="`ai-badge-${state}`">
        <Loader2 v-if="state === 'pending'" :size="10" class="ai-badge-spin" />
        <Sparkles v-else :size="10" />
        {{ state === 'pending' ? $t('items.form.badge_analyzing') : $t('items.form.badge_suggested') }}
    </span>
</template>

<style scoped>
.ai-badge {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    margin-left: 6px;
    padding: 1px 6px;
    border-radius: 999px;
    font-size: 10px;
    font-weight: 500;
    vertical-align: middle;
}
.ai-badge-suggested {
    color: var(--accent);
    background: color-mix(in srgb, var(--accent) 12%, transparent);
}
.ai-badge-pending {
    color: var(--fg-subtle);
    background: var(--bg-sunken);
}
.ai-badge-spin {
    animation: ai-badge-spin 0.8s linear infinite;
}
@keyframes ai-badge-spin {
    to {
        transform: rotate(360deg);
    }
}
</style>
