<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import { useForm } from '@inertiajs/vue3';
import { Trash2 } from 'lucide-vue-next';

const form = useForm<{ include_tags: boolean; include_custom_fields: boolean }>({
    include_tags: false,
    include_custom_fields: false,
});

function wipe() {
    const extras: string[] = [];
    if (form.include_tags) extras.push('all tags');
    if (form.include_custom_fields) extras.push('all custom fields');
    const tail = extras.length ? ` and ${extras.join(' and ')}` : '';
    if (!confirm(`This permanently deletes every item and photo${tail}. This cannot be undone. Continue?`)) return;
    form.post('/household/reset', { preserveScroll: true });
}
</script>

<template>
    <div class="space-y-4" style="border-top: 1px solid var(--border); padding-top: 28px">
        <HeadingSmall
            title="Danger zone"
            description="Permanently delete the inventory — every item and photo. Export a backup first; this cannot be undone."
        />

        <div class="space-y-2">
            <label class="flex items-center gap-2" style="font-size: 13px; cursor: pointer">
                <input v-model="form.include_tags" type="checkbox" data-test="wipe-include-tags" />
                Also delete all tags
            </label>
            <label class="flex items-center gap-2" style="font-size: 13px; cursor: pointer">
                <input v-model="form.include_custom_fields" type="checkbox" data-test="wipe-include-custom-fields" />
                Also delete all custom fields
            </label>
        </div>

        <button type="button" class="btn-pill btn-danger" data-test="wipe-button" :disabled="form.processing" @click="wipe">
            <Trash2 :size="14" />
            Wipe inventory
        </button>

        <p v-if="form.recentlySuccessful" class="text-sm" style="color: var(--fg-muted)" data-test="wipe-done">Inventory wiped.</p>
    </div>
</template>
