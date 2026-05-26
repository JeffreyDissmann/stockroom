<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import HouseholdLayout from '@/layouts/household/Layout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm, usePoll } from '@inertiajs/vue3';
import { RefreshCw } from 'lucide-vue-next';
import { computed, watch } from 'vue';

interface ReindexStatus {
    state: 'running' | 'done' | 'failed';
    done?: number;
    total?: number;
    indexed?: number;
    error?: string;
}

const props = defineProps<{ status: ReindexStatus | null; total: number; semantic: boolean }>();

const breadcrumbItems: BreadcrumbItem[] = [{ title: 'Search index', href: '/household/search-index' }];

const form = useForm({});

const running = computed(() => props.status?.state === 'running');
const percent = computed(() => {
    const s = props.status;
    return s && s.total ? Math.round(((s.done ?? 0) / s.total) * 100) : 0;
});

// Poll the status prop every 2s only while a rebuild is running.
const { start, stop } = usePoll(2000, { only: ['status'] }, { autoStart: false });
watch(running, (isRunning) => (isRunning ? start() : stop()), { immediate: true });

function rebuild() {
    form.post('/household/search-index', { preserveScroll: true });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Search index" />

        <HouseholdLayout>
            <div class="space-y-6">
                <HeadingSmall
                    title="Search index"
                    description="Rebuild the full-text search index for all items. Useful after a bulk change, or to (re)generate semantic-search embeddings."
                />

                <p style="font-size: 13px; color: var(--fg-muted)">
                    {{ total }} item{{ total === 1 ? '' : 's' }} to index.
                    <template v-if="semantic">Semantic search is on — unchanged items reuse cached embeddings, so re-runs are fast.</template>
                    <template v-else>Semantic search is off (keyword only).</template>
                    The rebuild runs in the background — a queue worker must be running.
                </p>

                <div>
                    <button type="button" class="btn-primary" :disabled="form.processing || running" data-test="rebuild-index" @click="rebuild">
                        <RefreshCw :size="14" />
                        Rebuild search index
                    </button>
                </div>

                <div v-if="status" data-test="reindex-status" style="border-top: 1px solid var(--border); padding-top: 20px">
                    <template v-if="status.state === 'running'">
                        <p style="font-size: 13px; margin-bottom: 8px">Indexing… {{ status.done }} / {{ status.total }}</p>
                        <div style="height: 8px; border-radius: 999px; background: var(--bg-sunken); overflow: hidden">
                            <div :style="{ width: `${percent}%`, height: '100%', background: 'var(--accent)', transition: 'width .3s' }" />
                        </div>
                    </template>
                    <p v-else-if="status.state === 'done'" style="font-size: 13px; color: var(--fg)">
                        Done — indexed {{ status.indexed ?? status.total }} item{{ (status.indexed ?? status.total) === 1 ? '' : 's' }}.
                    </p>
                    <p v-else-if="status.state === 'failed'" style="font-size: 13px; color: var(--neg)">Reindex failed: {{ status.error }}</p>
                </div>
            </div>
        </HouseholdLayout>
    </AppLayout>
</template>
