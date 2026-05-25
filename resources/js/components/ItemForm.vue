<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import ItemImageManager from '@/components/ItemImageManager.vue';
import ItemTypeIcon from '@/components/ItemTypeIcon.vue';
import type { ItemSummary, ItemTypeDescriptor, ItemTypeValue, TagSummary } from '@/types';
import { useForm } from '@inertiajs/vue3';
import { Check } from 'lucide-vue-next';
import { computed, ref } from 'vue';

type Mode = 'create' | 'edit';

const props = defineProps<{
    mode: Mode;
    item?: ItemSummary | null;
    parent?: ItemSummary | null;
    items: ItemSummary[];
    tags: TagSummary[];
    types: ItemTypeDescriptor[];
    submitLabel?: string;
}>();

const form = useForm({
    name: props.item?.name ?? '',
    description: props.item?.description ?? '',
    type: (props.item?.type.value ?? (props.parent ? 'item' : 'room')) as ItemTypeValue,
    parent_id: props.item?.parent_id ?? props.parent?.id ?? null,
    tags: (props.item?.tags ?? []).map((t) => t.id),
    images: [] as File[],
});

const queuedFiles = ref<File[]>([]);

function onFilesUpdate(files: File[]) {
    queuedFiles.value = files;
    form.images = files;
}

const eligibleParents = computed(() => props.items.filter((i) => !props.item || i.id !== props.item.id));

function toggleTag(id: number) {
    if (form.tags.includes(id)) {
        form.tags = form.tags.filter((t) => t !== id);
    } else {
        form.tags = [...form.tags, id];
    }
}

function submit() {
    if (props.mode === 'create') {
        // No forceFormData: Inertia auto-detects a File in form.images and switches
        // to multipart only when needed. Forcing it serialised an empty multipart
        // body (dropping name/type) when no image was queued.
        form.post('/items');
    } else if (props.item) {
        form.put(`/items/${props.item.id}`);
    }
}
</script>

<template>
    <form class="form" @submit.prevent="submit">
        <div class="form-row">
            <label>Type</label>
            <div class="grid grid-cols-3 gap-2">
                <button
                    v-for="type in types"
                    :key="type.value"
                    type="button"
                    class="flex flex-col items-center gap-1.5 p-3 text-[13px] transition rounded-md border"
                    :style="{
                        borderColor: form.type === type.value ? 'var(--fg)' : 'var(--border)',
                        background: form.type === type.value ? 'var(--bg-sunken)' : 'var(--bg-elev)',
                        color: form.type === type.value ? 'var(--fg)' : 'var(--fg-muted)',
                    }"
                    @click="form.type = type.value"
                >
                    <ItemTypeIcon :type="type.value" class="size-5" />
                    {{ type.label }}
                </button>
            </div>
            <InputError :message="form.errors.type" />
        </div>

        <div class="form-row">
            <label for="name">Name</label>
            <input id="name" v-model="form.name" autofocus required placeholder="e.g. Toolbox" class="field" />
            <InputError :message="form.errors.name" />
        </div>

        <div class="form-row">
            <label for="description">Description</label>
            <textarea id="description" v-model="form.description" rows="3" placeholder="Optional notes" class="field" />
            <InputError :message="form.errors.description" />
        </div>

        <div v-if="mode === 'create'" class="form-row">
            <label for="parent_id">Inside</label>
            <select id="parent_id" v-model="form.parent_id" class="field">
                <option :value="null">— Top level —</option>
                <option v-for="candidate in eligibleParents" :key="candidate.id" :value="candidate.id">
                    {{ candidate.type.label }} · {{ candidate.name }}
                </option>
            </select>
            <InputError :message="form.errors.parent_id" />
        </div>

        <ItemImageManager
            :mode="mode"
            :item-id="item?.id ?? null"
            :existing="item?.images ?? []"
            :files="queuedFiles"
            @update:files="onFilesUpdate"
        />
        <InputError :message="form.errors['images.0']" />

        <div class="form-row">
            <label>Tags</label>
            <div v-if="tags.length === 0" style="color: var(--fg-muted); font-size: 13px">
                No tags yet. Create one from the Tags page.
            </div>
            <div v-else class="flex flex-wrap gap-2">
                <button
                    v-for="tag in tags"
                    :key="tag.id"
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-[11.5px] transition"
                    :style="
                        form.tags.includes(tag.id)
                            ? { background: 'var(--accent)', color: 'var(--accent-fg)', border: '1px solid var(--accent)' }
                            : { background: 'var(--bg-elev)', color: 'var(--fg-muted)', border: '1px solid var(--border)' }
                    "
                    @click="toggleTag(tag.id)"
                >
                    <Check v-if="form.tags.includes(tag.id)" :size="12" />
                    <span v-if="tag.color" class="size-2 rounded-full" :style="{ backgroundColor: tag.color }" />
                    {{ tag.name }}
                </button>
            </div>
            <InputError :message="form.errors.tags" />
        </div>

        <div class="flex justify-end gap-2">
            <button type="submit" :disabled="form.processing" class="btn-primary">
                <Check :size="14" />
                {{ submitLabel ?? (mode === 'create' ? 'Create' : 'Save') }}
            </button>
        </div>
    </form>
</template>
