<script setup lang="ts">
import type { ItemViewMode } from '@/types';
import { Grid, List } from 'lucide-vue-next';

const view = defineModel<ItemViewMode>({ required: true });
</script>

<template>
    <div class="flex items-center gap-2">
        <button :class="['chip', view === 'list' ? 'active' : '']" type="button" @click="view = 'list'">
            <List />
            {{ $t('common.list') }}
        </button>
        <button :class="['chip', view === 'grid' ? 'active' : '']" type="button" @click="view = 'grid'">
            <Grid />
            {{ $t('common.grid') }}
        </button>
    </div>
</template>
