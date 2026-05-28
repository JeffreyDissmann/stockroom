<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { useIsAdmin } from '@/composables/useIsAdmin';
import { trans } from '@/composables/useTranslations';
import householdImport from '@/routes/household/import';
import { useForm, usePoll } from '@inertiajs/vue3';
import { Download } from 'lucide-vue-next';
import { computed, watch } from 'vue';

interface ImportStatus {
    state: 'running' | 'done' | 'failed';
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

const running = computed(() => props.status?.state === 'running');
const percent = computed(() => {
    const s = props.status;
    return s && s.total ? Math.round(((s.done ?? 0) / s.total) * 100) : 0;
});

// Poll the parent page (`only: ['status']`) for progress while a job is
// running, then stop to avoid a permanent background tick.
const { start, stop } = usePoll(2000, { only: ['status'] }, { autoStart: false });
watch(running, (isRunning) => (isRunning ? start() : stop()), { immediate: true });

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
                <input id="homebox-url" v-model="form.url" type="url" class="field" placeholder="https://homebox.example.com" :disabled="running" />
                <InputError :message="form.errors.url" />
            </div>
            <div class="form-row">
                <label for="homebox-username">{{ $t('household.import.email') }}</label>
                <input id="homebox-username" v-model="form.username" type="text" autocomplete="off" class="field" :disabled="running" />
                <InputError :message="form.errors.username" />
            </div>
            <div class="form-row">
                <label for="homebox-password">{{ $t('household.import.password') }}</label>
                <input id="homebox-password" v-model="form.password" type="password" autocomplete="off" class="field" :disabled="running" />
                <InputError :message="form.errors.password" />
            </div>

            <InputError :message="form.errors.connection" />
            <p style="font-size: 12px; color: var(--fg-muted)">
                {{ $t('household.import.note') }}
            </p>

            <div>
                <button type="submit" class="btn-primary" :disabled="form.processing || running">
                    <Download :size="14" />
                    {{ $t('household.import.submit') }}
                </button>
            </div>
        </form>

        <div v-if="status" data-test="import-status" style="border-top: 1px solid var(--border); padding-top: 20px">
            <template v-if="status.state === 'running'">
                <p style="font-size: 13px; margin-bottom: 8px">{{ $t('household.import.progress', { done: status.done ?? 0, total: status.total ?? 0 }) }}</p>
                <div style="height: 8px; border-radius: 999px; background: var(--bg-sunken); overflow: hidden">
                    <div :style="{ width: `${percent}%`, height: '100%', background: 'var(--accent)', transition: 'width .3s' }" />
                </div>
            </template>
            <p v-else-if="status.state === 'done'" style="font-size: 13px; color: var(--fg)">
                {{ $t('household.import.done', { entities: status.entities ?? 0, created: status.created ?? 0, updated: status.updated ?? 0, images: status.images ?? 0 }) }}<template v-if="status.imagesSkipped">{{ $t('household.import.skipped', { count: status.imagesSkipped }) }}</template>.
            </p>
            <p v-else-if="status.state === 'failed'" style="font-size: 13px; color: var(--neg)">{{ $t('household.import.failed', { error: status.error ?? '' }) }}</p>
        </div>
    </div>
</template>
