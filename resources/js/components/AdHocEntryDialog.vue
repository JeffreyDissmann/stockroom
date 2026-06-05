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
import { localToday } from '@/lib/date';
import maintenanceEntries from '@/routes/items/maintenance-entries';
import type { ItemSummary, SharedData } from '@/types';
import { useForm, usePage } from '@inertiajs/vue3';
import { NotebookPen } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

/**
 * Log a one-time maintenance/repair entry that never had a schedule —
 * "replaced the brake pads". Notes are required: they're the only
 * description of what was done.
 */
const props = defineProps<{
    item: ItemSummary;
}>();

const open = ref(false);

// Household currency code in the cost label — same convention as the
// purchase/sold price fields on the item form.
const currency = usePage<SharedData>().props.currency;

const form = useForm({
    completed_at: localToday(),
    notes: '',
    cost: '',
});

form.transform((data) => ({
    completed_at: data.completed_at,
    notes: data.notes,
    cost: data.cost === '' ? null : data.cost,
}));

watch(open, (isOpen) => {
    if (!isOpen) return;
    form.reset();
    form.clearErrors();
    form.completed_at = localToday();
});

// Inert until the payload is valid: notes are mandatory (they're the only
// description of what was done), the date exists and isn't in the future,
// and the cost is empty or a non-negative number.
const canSubmit = computed(
    () => form.notes.trim() !== '' && form.completed_at !== '' && form.completed_at <= localToday() && (form.cost === '' || Number(form.cost) >= 0),
);

function submit() {
    form.post(maintenanceEntries.store(props.item.id).url, {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
        },
    });
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <button type="button" class="btn-pill" data-test="maintenance-entry-add">
                <NotebookPen :size="14" />
                {{ $t('maintenance.log_entry') }}
            </button>
        </DialogTrigger>
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ $t('maintenance.entry_dialog.title') }}</DialogTitle>
                <DialogDescription>{{ $t('maintenance.entry_dialog.description', { name: item.name }) }}</DialogDescription>
            </DialogHeader>

            <form class="form" @submit.prevent="submit">
                <div class="form-row">
                    <label for="entry-notes">{{ $t('maintenance.entry_dialog.notes_label') }}</label>
                    <textarea id="entry-notes" v-model="form.notes" rows="2" class="field" data-test="maintenance-entry-notes" />
                    <InputError :message="form.errors.notes" />
                </div>

                <div class="form-row">
                    <label for="entry-date">{{ $t('maintenance.entry_dialog.date_label') }}</label>
                    <input
                        id="entry-date"
                        v-model="form.completed_at"
                        type="date"
                        :max="localToday()"
                        class="field"
                        data-test="maintenance-entry-date"
                    />
                    <InputError :message="form.errors.completed_at" />
                </div>

                <div class="form-row">
                    <label for="entry-cost">{{ $t('maintenance.entry_dialog.cost_label', { code: currency.code }) }}</label>
                    <input
                        id="entry-cost"
                        v-model="form.cost"
                        type="number"
                        step="0.01"
                        min="0"
                        class="field"
                        style="max-width: 140px"
                        data-test="maintenance-entry-cost"
                    />
                    <InputError :message="form.errors.cost" />
                </div>

                <DialogFooter>
                    <DialogClose as-child>
                        <button type="button" class="btn-ghost">{{ $t('common.cancel') }}</button>
                    </DialogClose>
                    <button type="submit" class="btn-primary" :disabled="form.processing || !canSubmit" data-test="maintenance-entry-submit">
                        <NotebookPen :size="14" />
                        {{ $t('maintenance.entry_dialog.submit') }}
                    </button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
