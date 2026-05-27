<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { type SharedData } from '@/types';
import { useForm, usePage } from '@inertiajs/vue3';
import { Download, Upload } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const page = usePage<SharedData>();
const lastImport = computed(() => page.props.flash.backup);

const fileInput = ref<HTMLInputElement>();
const form = useForm<{ file: File | null }>({ file: null });

function onFileChange(event: Event) {
    const target = event.target as HTMLInputElement;
    form.file = target.files?.[0] ?? null;
}

function restore() {
    form.post('/household/backup/import', {
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

        <div class="space-y-4">
            <Button as-child>
                <a href="/household/backup/export">
                    <Download class="size-4" />
                    {{ $t('household.backup.download') }}
                </a>
            </Button>
        </div>

        <form class="space-y-4 border-t border-neutral-200 pt-6 dark:border-neutral-800" @submit.prevent="restore">
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
