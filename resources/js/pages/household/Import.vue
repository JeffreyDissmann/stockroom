<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import HouseholdLayout from '@/layouts/household/Layout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm, usePoll } from '@inertiajs/vue3';
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

const breadcrumbItems: BreadcrumbItem[] = [{ title: 'Import from Homebox', href: '/household/import' }];

const form = useForm({ url: '', username: '', password: '' });

const running = computed(() => props.status?.state === 'running');
const percent = computed(() => {
    const s = props.status;
    return s && s.total ? Math.round(((s.done ?? 0) / s.total) * 100) : 0;
});

const { start, stop } = usePoll(2000, { only: ['status'] }, { autoStart: false });
watch(running, (isRunning) => (isRunning ? start() : stop()), { immediate: true });

function submit() {
    form.post('/household/import', {
        preserveScroll: true,
        onSuccess: () => form.reset('password'),
    });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Import from Homebox" />

        <HouseholdLayout>
            <div class="space-y-6">
                <HeadingSmall
                    title="Import from Homebox"
                    description="Pull locations, items, photos, tags and custom fields from a running Homebox instance. Re-running updates existing items instead of duplicating them."
                />

                <form class="form" @submit.prevent="submit">
                    <div class="form-row">
                        <label for="url">Homebox URL</label>
                        <input id="url" v-model="form.url" type="url" class="field" placeholder="https://homebox.example.com" :disabled="running" />
                        <InputError :message="form.errors.url" />
                    </div>
                    <div class="form-row">
                        <label for="username">Email</label>
                        <input id="username" v-model="form.username" type="text" autocomplete="off" class="field" :disabled="running" />
                        <InputError :message="form.errors.username" />
                    </div>
                    <div class="form-row">
                        <label for="password">Password</label>
                        <input id="password" v-model="form.password" type="password" autocomplete="off" class="field" :disabled="running" />
                        <InputError :message="form.errors.password" />
                    </div>

                    <InputError :message="form.errors.connection" />
                    <p style="font-size: 12px; color: var(--fg-muted)">
                        Your credentials are used once to obtain a token and are never stored. The import runs in the background — a queue
                        worker must be running.
                    </p>

                    <div>
                        <button type="submit" class="btn-primary" :disabled="form.processing || running">
                            <Download :size="14" />
                            Connect &amp; import
                        </button>
                    </div>
                </form>

                <div v-if="status" data-test="import-status" style="border-top: 1px solid var(--border); padding-top: 20px">
                    <template v-if="status.state === 'running'">
                        <p style="font-size: 13px; margin-bottom: 8px">Importing… {{ status.done }} / {{ status.total }}</p>
                        <div style="height: 8px; border-radius: 999px; background: var(--bg-sunken); overflow: hidden">
                            <div :style="{ width: `${percent}%`, height: '100%', background: 'var(--accent)', transition: 'width .3s' }" />
                        </div>
                    </template>
                    <p v-else-if="status.state === 'done'" style="font-size: 13px; color: var(--fg)">
                        Imported {{ status.entities }} entities ({{ status.created }} new, {{ status.updated }} updated) and {{ status.images }} photos<template v-if="status.imagesSkipped"> ({{ status.imagesSkipped }} unsupported photo(s) skipped)</template>.
                    </p>
                    <p v-else-if="status.state === 'failed'" style="font-size: 13px; color: var(--neg)">Import failed: {{ status.error }}</p>
                </div>
            </div>
        </HouseholdLayout>
    </AppLayout>
</template>
