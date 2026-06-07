<script setup lang="ts">
/**
 * Single-button toggle that flips the bulk-selection store between
 * "Select" and "Done". Reused everywhere the user can enter selection
 * mode — Items index, Search results, and the Contents section on item
 * Show. Lives outside the topbar now (sits next to the List/Grid view
 * toggle), so it shares the same row as the rest of the per-section
 * affordances and stays close to the data it acts on.
 */
import { useBulkSelection } from '@/composables/useBulkSelection';
import { CheckSquare } from 'lucide-vue-next';

const bulk = useBulkSelection();
</script>

<template>
    <button type="button" class="btn-pill" data-test="bulk-toggle" :aria-pressed="bulk.isSelectMode.value" @click="bulk.toggleMode">
        <CheckSquare :size="14" />
        {{ bulk.isSelectMode.value ? $t('items.bulk.done') : $t('items.bulk.select') }}
    </button>
</template>
