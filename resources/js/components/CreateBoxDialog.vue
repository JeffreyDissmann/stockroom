<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import itemBox from '@/routes/items/box';
import type { ItemSummary } from '@/types';
import { useForm } from '@inertiajs/vue3';
import { PackageOpen } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const props = defineProps<{
    item: ItemSummary;
    // Tailwind class on the inline trigger button — lets the parent hide it
    // on a given breakpoint (e.g. `hidden md:inline-flex`) while still being
    // able to open the dialog programmatically via the exposed openDialog().
    triggerClass?: string;
}>();

const open = ref(false);

// Lets a parent open the dialog from outside (e.g. a mobile More-menu item)
// without having to model the dialog's open state at parent level.
defineExpose({
    openDialog: () => {
        open.value = true;
    },
});

// Form defaults mirror what the controller would derive server-side — but
// surfacing them client-side lets the admin tweak before saving. Quantity
// is kept as an integer; the others are nullable strings.
const form = useForm<{
    name: string;
    serial_number: string | null;
    manufacturer: string | null;
    model_number: string | null;
    description: string | null;
    quantity: number;
}>({
    name: `BOX: ${props.item.name}`,
    serial_number: props.item.serial_number ?? null,
    manufacturer: props.item.manufacturer ?? null,
    model_number: props.item.model_number ?? null,
    description: props.item.description ?? null,
    quantity: props.item.quantity ?? 1,
});

// Re-prime the form whenever the dialog reopens, so a previous attempt's
// edits don't bleed into the next box-for-the-same-item action.
watch(open, (isOpen) => {
    if (isOpen) {
        form.reset();
        form.clearErrors();
    }
});

function submit() {
    form.post(itemBox.store(props.item.id).url, {
        // Server redirects to the new box's show page on success, so let the
        // browser follow — no need to stay on this page.
        onSuccess: () => (open.value = false),
    });
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <button type="button" :class="['btn-pill', triggerClass]" data-test="create-box">
                <PackageOpen :size="14" />
                {{ $t('items.box.trigger') }}
            </button>
        </DialogTrigger>
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ $t('items.box.title', { name: item.name }) }}</DialogTitle>
                <DialogDescription>{{ $t('items.box.description') }}</DialogDescription>
            </DialogHeader>

            <form class="form" @submit.prevent="submit">
                <!-- Labels reuse the existing item form / common keys so we
                     don't carry a duplicate translation set — these fields
                     mean the same thing here as they do on the item edit
                     page, so a single source of truth is right. -->
                <div class="form-row">
                    <label for="box-name">{{ $t('common.name') }}</label>
                    <input id="box-name" v-model="form.name" type="text" class="field" data-test="box-name" />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="form-row">
                    <label for="box-serial">{{ $t('items.form.serial_number') }}</label>
                    <input id="box-serial" v-model="form.serial_number" type="text" class="field" data-test="box-serial" />
                    <InputError :message="form.errors.serial_number" />
                </div>

                <div class="form-row">
                    <label for="box-manufacturer">{{ $t('items.form.manufacturer') }}</label>
                    <input id="box-manufacturer" v-model="form.manufacturer" type="text" class="field" data-test="box-manufacturer" />
                    <InputError :message="form.errors.manufacturer" />
                </div>

                <div class="form-row">
                    <label for="box-model">{{ $t('items.form.model_number') }}</label>
                    <input id="box-model" v-model="form.model_number" type="text" class="field" data-test="box-model" />
                    <InputError :message="form.errors.model_number" />
                </div>

                <div class="form-row">
                    <label for="box-description">{{ $t('common.description') }}</label>
                    <textarea id="box-description" v-model="form.description" rows="3" class="field" data-test="box-description" />
                    <InputError :message="form.errors.description" />
                </div>

                <div class="form-row">
                    <label for="box-quantity">{{ $t('items.form.quantity') }}</label>
                    <input
                        id="box-quantity"
                        v-model.number="form.quantity"
                        type="number"
                        min="1"
                        class="field"
                        style="max-width: 100px"
                        data-test="box-quantity"
                    />
                    <InputError :message="form.errors.quantity" />
                </div>

                <DialogFooter>
                    <DialogClose as-child>
                        <button type="button" class="btn-ghost">{{ $t('common.cancel') }}</button>
                    </DialogClose>
                    <button type="submit" class="btn-primary" :disabled="form.processing || !form.name" data-test="box-submit">
                        <PackageOpen :size="14" />
                        {{ $t('items.box.submit') }}
                    </button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
