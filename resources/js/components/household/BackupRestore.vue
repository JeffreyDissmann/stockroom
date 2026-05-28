<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { useIsAdmin } from '@/composables/useIsAdmin';
// Wayfinder's named exports for the backup routes — `exportMethod` and
// `importMethod` are the function names (suffixed because `export` and
// `import` are reserved words at module top level). Going through the
// named exports avoids the default-export object whose keys are unsuffixed
// (`backup.exportMethod` is undefined, breaking the :href at render time).
import { exportMethod, importMethod } from '@/routes/household/backup';
import { type SharedData } from '@/types';
import { useForm, usePage } from '@inertiajs/vue3';
import { Download, Upload } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const isAdmin = useIsAdmin();

const page = usePage<SharedData>();
const lastImport = computed(() => page.props.flash.backup);

const fileInput = ref<HTMLInputElement>();
const form = useForm<{ file: File | null }>({ file: null });

function onFileChange(event: Event) {
    const target = event.target as HTMLInputElement;
    form.file = target.files?.[0] ?? null;
}

function restore() {
    form.post(importMethod().url, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            form.reset('file');
            if (fileInput.value) fileInput.value.value = '';
        },
    });
}
</script>

<template>
    <div class="space-y-6">
        <HeadingSmall :title="$t('household.nav.backup')" :description="$t('household.backup.description')" />

        <p v-if="!isAdmin" class="text-sm text-neutral-600 dark:text-neutral-400">{{ $t('common.admin_only') }}</p>

        <div v-if="isAdmin" class="space-y-4">
            <Button as-child data-test="backup-download">
                <a :href="exportMethod().url">
                    <Download class="size-4" />
                    {{ $t('household.backup.download') }}
                </a>
            </Button>
        </div>

        <form v-if="isAdmin" class="space-y-4 border-t border-neutral-200 pt-6 dark:border-neutral-800" @submit.prevent="restore">
            <p class="text-sm text-neutral-600 dark:text-neutral-400">
                {{ $t('household.backup.restore_note') }}
            </p>
            <div class="grid gap-2">
                <input
                    ref="fileInput"
                    type="file"
                    accept=".zip,application/zip"
                    data-test="backup-file"
                    class="block w-full text-sm text-neutral-600 file:mr-4 file:rounded-md file:border file:border-neutral-300 file:bg-transparent file:px-3 file:py-1.5 file:text-sm dark:text-neutral-400 dark:file:border-neutral-700"
                    @change="onFileChange"
                />
                <InputError :message="form.errors.file" />
            </div>

            <Button :disabled="form.processing || !form.file" variant="destructive">
                <Upload class="size-4" />
                {{ $t('household.backup.restore') }}
            </Button>

            <p v-if="lastImport" class="text-sm text-neutral-600 dark:text-neutral-400" data-test="backup-result">
                {{ $t('household.backup.result', { items: lastImport.items, tags: lastImport.tags, images: lastImport.images }) }}
            </p>
        </form>
    </div>
</template>
