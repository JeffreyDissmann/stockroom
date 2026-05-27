<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import { useIsAdmin } from '@/composables/useIsAdmin';
import { trans } from '@/composables/useTranslations';
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

const breadcrumbItems: BreadcrumbItem[] = [{ title: trans('household.nav.search_index'), href: '/household/search-index' }];

const isAdmin = useIsAdmin();

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
        <Head :title="$t('household.nav.search_index')" />

        <HouseholdLayout>
            <div class="space-y-6">
                <HeadingSmall :title="$t('household.nav.search_index')" :description="$t('household.search_index.description')" />

                <p style="font-size: 13px; color: var(--fg-muted)">
                    {{ $tChoice('household.search_index.count', total) }}
                    <template v-if="semantic">{{ $t('household.search_index.semantic_on') }}</template>
                    <template v-else>{{ $t('household.search_index.semantic_off') }}</template>
                    {{ $t('household.search_index.worker_note') }}
                </p>

                <div v-if="isAdmin">
                    <button type="button" class="btn-primary" :disabled="form.processing || running" data-test="rebuild-index" @click="rebuild">
                        <RefreshCw :size="14" />
                        {{ $t('household.search_index.rebuild') }}
                    </button>
                </div>
                <p v-else class="text-sm" style="color: var(--fg-muted)">{{ $t('common.admin_only') }}</p>

                <div v-if="status" data-test="reindex-status" style="border-top: 1px solid var(--border); padding-top: 20px">
                    <template v-if="status.state === 'running'">
                        <p style="font-size: 13px; margin-bottom: 8px">{{ $t('household.search_index.progress', { done: status.done ?? 0, total: status.total ?? 0 }) }}</p>
                        <div style="height: 8px; border-radius: 999px; background: var(--bg-sunken); overflow: hidden">
                            <div :style="{ width: `${percent}%`, height: '100%', background: 'var(--accent)', transition: 'width .3s' }" />
                        </div>
                    </template>
                    <p v-else-if="status.state === 'done'" style="font-size: 13px; color: var(--fg)">
                        {{ $tChoice('household.search_index.done', status.indexed ?? status.total ?? 0) }}
                    </p>
                    <p v-else-if="status.state === 'failed'" style="font-size: 13px; color: var(--neg)">{{ $t('household.search_index.failed', { error: status.error ?? '' }) }}</p>
                </div>
            </div>
        </HouseholdLayout>
    </AppLayout>
</template>
