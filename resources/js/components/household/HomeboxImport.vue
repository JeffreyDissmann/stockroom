<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { useIsAdmin } from '@/composables/useIsAdmin';
import householdImport from '@/routes/household/import';
import { useForm, usePoll } from '@inertiajs/vue3';
import { AlertTriangle, CheckCircle2, Download, Loader2 } from 'lucide-vue-next';
import { computed, watch } from 'vue';

interface ImportStatus {
    state: 'discovering' | 'running' | 'done' | 'failed';
    done?: number;
    total?: number;
    entities?: number;
    images?: number;
    imagesSkipped?: number;
    created?: number;
    updated?: number;
    error?: string;
}

const props = defineProps<{ status: ImportStatus | null }>();

const isAdmin = useIsAdmin();

const form = useForm({ url: '', username: '', password: '' });

// `busy` covers both pre-progress and active progress phases. 'discovering'
// is the silent bootstrap inside the importer (tree fetch + allEntities
// pagination) before any onProgress fires — we want the form locked and
// the poll running during that phase too, otherwise the page looks idle
// while a real job is grinding.
const busy = computed(() => props.status?.state === 'discovering' || props.status?.state === 'running');
const percent = computed(() => {
    const s = props.status;
    return s && s.total ? Math.round(((s.done ?? 0) / s.total) * 100) : 0;
});

// Poll the parent page (`only: ['status']`) for progress while a job is
// running, then stop to avoid a permanent background tick.
const { start, stop } = usePoll(2000, { only: ['status'] }, { autoStart: false });
watch(busy, (isBusy) => (isBusy ? start() : stop()), { immediate: true });

function submit() {
    form.post(householdImport.start().url, {
        preserveScroll: true,
        onSuccess: () => form.reset('password'),
    });
}
</script>

<template>
    <div class="space-y-6 border-t border-neutral-200 pt-6 dark:border-neutral-800">
        <HeadingSmall :title="$t('household.import.title')" :description="$t('household.import.description')" />

        <p v-if="!isAdmin" class="text-sm" style="color: var(--fg-muted)">{{ $t('common.admin_only') }}</p>

        <form v-if="isAdmin" class="form" @submit.prevent="submit">
            <div class="form-row">
                <label for="homebox-url">{{ $t('household.import.url') }}</label>
                <input id="homebox-url" v-model="form.url" type="url" class="field" placeholder="https://homebox.example.com" :disabled="busy" />
                <InputError :message="form.errors.url" />
            </div>
            <div class="form-row">
                <label for="homebox-username">{{ $t('household.import.email') }}</label>
                <input id="homebox-username" v-model="form.username" type="text" autocomplete="off" class="field" :disabled="busy" />
                <InputError :message="form.errors.username" />
            </div>
            <div class="form-row">
                <label for="homebox-password">{{ $t('household.import.password') }}</label>
                <input id="homebox-password" v-model="form.password" type="password" autocomplete="off" class="field" :disabled="busy" />
                <InputError :message="form.errors.password" />
            </div>

            <InputError :message="form.errors.connection" />
            <p style="font-size: 12px; color: var(--fg-muted)">
                {{ $t('household.import.note') }}
            </p>

            <div>
                <button type="submit" class="btn-primary" :disabled="form.processing || busy">
                    <Download :size="14" />
                    {{ $t('household.import.submit') }}
                </button>
            </div>
        </form>

        <div v-if="status" data-test="import-status" style="border-top: 1px solid var(--border); padding-top: 20px">
            <!-- 'discovering': indeterminate state before counts are known.
                 Indeterminate slider so it's visually obvious work is
                 happening even though done/total are still 0. -->
            <template v-if="status.state === 'discovering'">
                <p data-test="import-status-discovering" style="display: flex; align-items: center; gap: 8px; font-size: 13px; margin-bottom: 8px">
                    <Loader2 :size="14" class="animate-spin" :style="{ color: 'var(--accent)' }" />
                    {{ $t('household.import.discovering') }}
                </p>
                <div class="hb-bar">
                    <div class="hb-bar-indeterminate" />
                </div>
            </template>

            <!-- 'running': real progress, percent + counter. -->
            <template v-else-if="status.state === 'running'">
                <p style="font-size: 13px; margin-bottom: 8px">
                    {{ $t('household.import.progress', { done: status.done ?? 0, total: status.total ?? 0 }) }}
                </p>
                <div class="hb-bar">
                    <div :style="{ width: `${percent}%`, height: '100%', background: 'var(--accent)', transition: 'width .3s' }" />
                </div>
            </template>

            <!-- 'done': success summary card. -->
            <div
                v-else-if="status.state === 'done'"
                data-test="import-status-done"
                style="
                    display: flex;
                    gap: 10px;
                    padding: 12px 14px;
                    border-radius: 8px;
                    background: color-mix(in srgb, var(--pos) 12%, transparent);
                    color: var(--pos);
                "
            >
                <CheckCircle2 :size="18" style="flex-shrink: 0; margin-top: 1px" />
                <p style="font-size: 13px; line-height: 1.5; margin: 0; color: var(--fg)">
                    {{
                        $t('household.import.done', {
                            entities: status.entities ?? 0,
                            created: status.created ?? 0,
                            updated: status.updated ?? 0,
                            images: status.images ?? 0,
                        })
                    }}<template v-if="status.imagesSkipped">{{ $t('household.import.skipped', { count: status.imagesSkipped }) }}</template
                    >.
                </p>
            </div>

            <!-- 'failed': error card. Promoted from a single line of red text
                 to a card with title + message so the failure is impossible
                 to miss when the form is sitting right above it. -->
            <div
                v-else-if="status.state === 'failed'"
                data-test="import-status-failed"
                style="
                    display: flex;
                    gap: 10px;
                    padding: 12px 14px;
                    border-radius: 8px;
                    background: color-mix(in srgb, var(--neg) 12%, transparent);
                    color: var(--neg);
                "
            >
                <AlertTriangle :size="18" style="flex-shrink: 0; margin-top: 1px" />
                <div style="font-size: 13px; line-height: 1.5">
                    <p style="font-weight: 600; margin: 0">{{ $t('household.import.failed_title') }}</p>
                    <p style="margin: 4px 0 0; color: var(--fg)">{{ $t('household.import.failed', { error: status.error ?? '' }) }}</p>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.hb-bar {
    height: 8px;
    border-radius: 999px;
    background: var(--bg-sunken);
    overflow: hidden;
}
/* Indeterminate bar: a fixed-width slug slides across, looping forever. Pure
   CSS so it stays smooth even when the browser tab is throttled. */
.hb-bar-indeterminate {
    width: 35%;
    height: 100%;
    background: var(--accent);
    border-radius: 999px;
    animation: hb-slide 1.4s ease-in-out infinite;
}
@keyframes hb-slide {
    0% {
        transform: translateX(-100%);
    }
    100% {
        transform: translateX(285%);
    } /* (100/35)*100% so the slug exits the right edge */
}
</style>
