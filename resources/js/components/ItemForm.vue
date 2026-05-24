<script setup lang="ts">
import ItemTypeIcon from '@/components/ItemTypeIcon.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import type { ItemSummary, ItemTypeDescriptor, ItemTypeValue, TagSummary } from '@/types';
import { useForm } from '@inertiajs/vue3';
import { Check } from 'lucide-vue-next';
import { computed } from 'vue';

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
});

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
        form.post('/items');
    } else if (props.item) {
        form.put(`/items/${props.item.id}`);
    }
}
</script>

<template>
    <form class="flex flex-col gap-6" @submit.prevent="submit">
        <div class="grid gap-2">
            <Label>Type</Label>
            <div class="grid grid-cols-3 gap-2">
                <button
                    v-for="type in types"
                    :key="type.value"
                    type="button"
                    class="flex flex-col items-center gap-1.5 rounded-md border p-3 text-sm transition hover:bg-muted"
                    :class="form.type === type.value ? 'border-primary bg-primary/5 text-primary' : 'border-border'"
                    @click="form.type = type.value"
                >
                    <ItemTypeIcon :type="type.value" class="size-5" />
                    {{ type.label }}
                </button>
            </div>
            <InputError :message="form.errors.type" />
        </div>

        <div class="grid gap-2">
            <Label for="name">Name</Label>
            <Input id="name" v-model="form.name" autofocus required placeholder="e.g. Toolbox" />
            <InputError :message="form.errors.name" />
        </div>

        <div class="grid gap-2">
            <Label for="description">Description</Label>
            <textarea
                id="description"
                v-model="form.description"
                rows="3"
                placeholder="Optional notes"
                class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
            />
            <InputError :message="form.errors.description" />
        </div>

        <div v-if="mode === 'create'" class="grid gap-2">
            <Label for="parent_id">Inside</Label>
            <select
                id="parent_id"
                v-model="form.parent_id"
                class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
            >
                <option :value="null">— Top level —</option>
                <option v-for="candidate in eligibleParents" :key="candidate.id" :value="candidate.id">
                    {{ candidate.type.label }} · {{ candidate.name }}
                </option>
            </select>
            <InputError :message="form.errors.parent_id" />
        </div>

        <div class="grid gap-2">
            <Label>Tags</Label>
            <div v-if="tags.length === 0" class="text-sm text-muted-foreground">
                No tags yet. Create one from the Tags page.
            </div>
            <div v-else class="flex flex-wrap gap-2">
                <button
                    v-for="tag in tags"
                    :key="tag.id"
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs transition"
                    :class="form.tags.includes(tag.id) ? 'border-primary bg-primary/10 text-primary' : 'border-border text-muted-foreground hover:bg-muted'"
                    @click="toggleTag(tag.id)"
                >
                    <Check v-if="form.tags.includes(tag.id)" class="size-3" />
                    <span v-if="tag.color" class="size-2 rounded-full" :style="{ backgroundColor: tag.color }" />
                    {{ tag.name }}
                </button>
            </div>
            <InputError :message="form.errors.tags" />
        </div>

        <div class="flex justify-end gap-2">
            <Button type="submit" :disabled="form.processing">
                {{ submitLabel ?? (mode === 'create' ? 'Create' : 'Save') }}
            </Button>
        </div>
    </form>
</template>
