<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import type { ItemSummary } from '@/types';
import { router, usePage } from '@inertiajs/vue3';
import { CornerUpRight } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface MoveTarget {
    id: number;
    name: string;
    path: string;
    type: { value: string; label: string };
}

const props = defineProps<{
    item: ItemSummary;
    targets: MoveTarget[];
}>();

const open = ref(false);
const selected = ref<number | null>(props.item.parent_id ?? null);
const processing = ref(false);

const page = usePage();
const error = computed(() => (page.props.errors as Record<string, string> | undefined)?.parent_id);

// Reset the selection to the item's current parent whenever the dialog opens.
watch(open, (isOpen) => {
    if (isOpen) {
        selected.value = props.item.parent_id ?? null;
    }
});

function move() {
    processing.value = true;
    router.patch(
        `/items/${props.item.id}/move`,
        { parent_id: selected.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                open.value = false;
            },
            onFinish: () => {
                processing.value = false;
            },
        },
    );
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <button type="button" class="btn-pill" data-test="move-item">
                <CornerUpRight :size="14" />
                Move
            </button>
        </DialogTrigger>
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Move "{{ item.name }}"</DialogTitle>
                <DialogDescription>Choose where this item should live. Its contents move with it.</DialogDescription>
            </DialogHeader>

            <div class="form-row">
                <label for="move-target">New location</label>
                <select id="move-target" v-model="selected" class="field" data-test="move-target">
                    <option :value="null">— Top level —</option>
                    <option v-for="target in targets" :key="target.id" :value="target.id">
                        {{ target.path }}
                    </option>
                </select>
                <InputError :message="error" />
            </div>

            <DialogFooter>
                <DialogClose as-child>
                    <button type="button" class="btn-ghost">Cancel</button>
                </DialogClose>
                <button type="button" class="btn-primary" :disabled="processing" @click="move">
                    <CornerUpRight :size="14" />
                    Move here
                </button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
