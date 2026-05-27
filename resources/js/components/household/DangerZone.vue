<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import { trans } from '@/composables/useTranslations';
import { useForm } from '@inertiajs/vue3';
import { Trash2 } from 'lucide-vue-next';

const form = useForm<{ include_tags: boolean; include_custom_fields: boolean }>({
    include_tags: false,
    include_custom_fields: false,
});

function wipe() {
    const extras: string[] = [];
    if (form.include_tags) extras.push(trans('household.danger.extra_tags'));
    if (form.include_custom_fields) extras.push(trans('household.danger.extra_custom_fields'));
    const and = trans('household.danger.and');
    const tail = extras.length ? ` ${and} ${extras.join(` ${and} `)}` : '';
    if (!confirm(trans('household.danger.confirm', { tail }))) return;
    form.post('/household/reset', { preserveScroll: true });
}
</script>

<template>
    <div class="space-y-4" style="border-top: 1px solid var(--border); padding-top: 28px">
        <HeadingSmall :title="$t('household.danger.title')" :description="$t('household.danger.description')" />

        <div class="space-y-2">
            <label class="flex items-center gap-2" style="font-size: 13px; cursor: pointer">
                <input v-model="form.include_tags" type="checkbox" data-test="wipe-include-tags" />
                {{ $t('household.danger.include_tags') }}
            </label>
            <label class="flex items-center gap-2" style="font-size: 13px; cursor: pointer">
                <input v-model="form.include_custom_fields" type="checkbox" data-test="wipe-include-custom-fields" />
                {{ $t('household.danger.include_custom_fields') }}
            </label>
        </div>

        <button type="button" class="btn-pill btn-danger" data-test="wipe-button" :disabled="form.processing" @click="wipe">
            <Trash2 :size="14" />
            {{ $t('household.danger.wipe') }}
        </button>

        <p v-if="form.recentlySuccessful" class="text-sm" style="color: var(--fg-muted)" data-test="wipe-done">{{ $t('household.danger.done') }}</p>
    </div>
</template>
