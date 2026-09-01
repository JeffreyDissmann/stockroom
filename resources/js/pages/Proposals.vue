<script setup lang="ts">
import ItemSuggestion from '@/components/ItemSuggestion.vue';
import { trans } from '@/composables/useTranslations';
import PageHeader from '@/components/PageHeader.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import itemRoutes from '@/routes/items';
import proposalRoutes from '@/routes/proposals';
import type { BreadcrumbItem, SharedData } from '@/types';
import { Head, Link, useForm, usePage, usePoll } from '@inertiajs/vue3';
import { ImageOff, Sparkles } from '@lucide/vue';
import { computed, watch } from 'vue';

interface Suggestion {
    id: number;
    field_label: string;
    current_value: string | null;
    proposed_value: string;
    photo_count: number;
    model: string;
    created_at_human: string | null;
    is_stale: boolean;
}

interface Row {
    item: {
        id: number;
        name: string;
        images: { id: number; thumb_url: string }[];
        location_path: string;
    };
    suggestions: Suggestion[];
}

interface RunStatus {
    state: 'running' | 'done' | 'failed';
    done?: number;
    total?: number;
    proposed?: number;
    failed?: number;
    error?: string;
}

const props = defineProps<{ proposals: Row[]; status: RunStatus | null; unreviewed: number }>();

const aiEnabled = usePage<SharedData>().props.features.ai;
const form = useForm({});
const running = computed(() => props.status?.state === 'running');

// Poll only while a run is going. A photo costs roughly fifteen seconds of
// vision inference, so 3s is frequent enough to feel live without hammering
// a machine that is already busy doing the actual work.
const { start, stop } = usePoll(3000, { only: ['status', 'proposals', 'unreviewed'] }, { autoStart: false });
watch(running, (isRunning) => (isRunning ? start() : stop()), { immediate: true });

function run() {
    form.post(proposalRoutes.run().url, { preserveScroll: true });
}

const breadcrumbItems: BreadcrumbItem[] = [{ title: trans('nav.proposals'), href: proposalRoutes.index().url }];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="$t('proposals.title')" />

        <div class="page">
            <PageHeader :title="$t('proposals.title')" :description="$t('proposals.description')" />

            <div v-if="aiEnabled" class="run-bar">
                <button
                    type="button"
                    class="btn-primary"
                    :disabled="form.processing || running || unreviewed === 0"
                    data-test="proposals-run"
                    @click="run"
                >
                    <Sparkles :size="14" />
                    {{ $t('proposals.run') }}
                </button>
                <span class="text-13 text-fg-muted">
                    {{ unreviewed === 0 ? $t('proposals.run_none') : $tChoice('proposals.run_pending', unreviewed) }}
                </span>
            </div>

            <div v-if="status" class="run-status" data-test="proposals-run-status">
                <template v-if="status.state === 'running'">
                    <p class="m-0 mb-2 text-13">
                        {{ $t('proposals.running', { done: status.done ?? 0, total: status.total ?? 0 }) }}
                    </p>
                    <div class="run-track">
                        <div class="run-fill" :style="{ width: `${status.total ? Math.round(((status.done ?? 0) / status.total) * 100) : 0}%` }" />
                    </div>
                </template>
                <template v-else-if="status.state === 'done'">
                    <p class="m-0 text-13">
                        {{ $tChoice('proposals.run_done', status.proposed ?? 0, { total: status.total ?? 0 }) }}
                    </p>
                    <p v-if="status.failed" class="m-0 mt-1 text-13 text-neg">
                        {{ $tChoice('proposals.run_failed_some', status.failed) }}
                    </p>
                </template>
                <p v-else class="m-0 text-13 text-neg">{{ $t('proposals.run_failed', { error: status.error ?? '' }) }}</p>
            </div>

            <p v-if="proposals.length === 0" class="text-13 text-fg-muted" data-test="proposals-empty">
                {{ $t('proposals.empty') }}
            </p>

            <ul v-else class="queue" data-test="proposal-list">
                <!-- One card per item, not per suggestion: the photo and the
                     context belong to the item, and two suggestions about the
                     same box are one sitting-down, not two. -->
                <li v-for="row in proposals" :key="row.item.id" class="queue-row" data-test="proposal-row">
                    <div class="queue-body">
                        <div class="queue-head">
                            <Link :href="itemRoutes.show(row.item.id).url" class="queue-name">{{ row.item.name }}</Link>
                            <span v-if="row.item.location_path" class="queue-location">{{ row.item.location_path }}</span>
                        </div>

                        <!-- Every photo, full width: the suggestions below were
                             read from these, so hiding all but one would mean
                             judging most of the evidence unseen. -->
                        <div v-if="row.item.images.length" class="queue-photos" data-test="proposal-photos">
                            <Link
                                v-for="image in row.item.images"
                                :key="image.id"
                                :href="itemRoutes.show(row.item.id).url"
                                class="queue-thumb"
                                :title="row.item.name"
                            >
                                <img :src="image.thumb_url" :alt="row.item.name" loading="lazy" />
                            </Link>
                        </div>
                        <div v-else class="queue-photos">
                            <span class="queue-thumb"><ImageOff :size="18" class="text-fg-subtle" /></span>
                        </div>

                        <div v-for="suggestion in row.suggestions" :key="suggestion.id" class="queue-suggestion">
                            <div class="queue-meta">
                                <span class="queue-field">{{ suggestion.field_label }}</span>
                                <span class="grow"></span>
                                <span>{{ $tChoice('proposals.from_model', suggestion.photo_count, { model: suggestion.model }) }}</span>
                                <span>·</span>
                                <span>{{ suggestion.created_at_human }}</span>
                            </div>
                            <ItemSuggestion
                                :id="suggestion.id"
                                :proposed-value="suggestion.proposed_value"
                                :current-value="suggestion.current_value"
                                :is-stale="suggestion.is_stale"
                                show-current
                            />
                        </div>
                    </div>
                </li>
            </ul>
        </div>
    </AppLayout>
</template>

<style scoped>
.run-bar {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 16px;
}
.run-status {
    margin-bottom: 16px;
    border: 1px solid var(--border);
    border-radius: var(--radius);
    background: var(--bg-elev);
    padding: 12px 14px;
}
.run-track {
    height: 8px;
    overflow: hidden;
    border-radius: 999px;
    background: var(--bg-sunken);
}
.run-fill {
    height: 100%;
    background: var(--accent);
    transition: width 0.3s;
}
.queue {
    display: flex;
    flex-direction: column;
    gap: 12px;
    padding: 0;
    margin: 0;
    list-style: none;
}
.queue-row {
    display: block;
    border: 1px solid var(--border);
    border-radius: var(--radius);
    background: var(--bg-elev);
    padding: 14px 16px;
}
/* Scrolls sideways rather than wrapping, so a well-photographed item cannot
   push its own suggestions off the bottom of the screen. */
.queue-photos {
    display: flex;
    gap: 8px;
    overflow-x: auto;
    margin: 10px 0 12px;
    padding-bottom: 2px;
}
/* Big enough to recognise the thing at a glance, which is the whole reason the
   queue shows a picture: a suggestion about a photo cannot be judged blind. */
.queue-thumb {
    display: grid;
    place-items: center;
    flex: 0 0 auto;
    width: 112px;
    height: 112px;
    border-radius: var(--radius-sm);
    background: var(--bg-sunken);
    overflow: hidden;
}
.queue-thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.queue-body {
    min-width: 0;
    flex: 1;
}
.queue-head {
    display: flex;
    align-items: baseline;
    gap: 8px;
    flex-wrap: wrap;
    margin-bottom: 10px;
}
.queue-name {
    font-weight: 600;
    font-size: 14px;
    color: var(--fg);
    text-decoration: none;
}
.queue-name:hover {
    text-decoration: underline;
}
.queue-location {
    font-size: 12px;
    color: var(--fg-subtle);
}
.queue-suggestion + .queue-suggestion {
    margin-top: 12px;
}
.queue-meta {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 6px;
    font-size: 12px;
    color: var(--fg-subtle);
}
.queue-field {
    text-transform: uppercase;
    letter-spacing: 0.04em;
    border: 1px solid var(--border);
    border-radius: 999px;
    padding: 1px 8px;
    font-size: 11px;
}
@media (max-width: 640px) {
    .queue-thumb {
        width: 72px;
        height: 72px;
    }
}
</style>
