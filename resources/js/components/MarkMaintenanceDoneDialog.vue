<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { localToday } from '@/lib/date';
import maintenanceTasks from '@/routes/items/maintenance-tasks';
import type { ItemSummary, MaintenanceTaskRow, SharedData } from '@/types';
import { useForm, usePage } from '@inertiajs/vue3';
import { Check } from 'lucide-vue-next';
import { computed, watch } from 'vue';

/**
 * "Mark done" dialog: records a completion entry and rolls the schedule.
 * Date prefilled with today, backdatable; notes/cost optional. Open state
 * is owned by the parent — one instance serves every task card.
 *
 * `item` only needs an id (the route) — Pick lets the global maintenance
 * page pass its slim per-row item payload as well as a full ItemSummary.
 */
const props = defineProps<{
    item: Pick<ItemSummary, 'id' | 'name'>;
    task: MaintenanceTaskRow | null;
}>();

const open = defineModel<boolean>('open', { required: true });

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
    notes: data.notes || null,
    cost: data.cost === '' ? null : data.cost,
}));

watch(open, (isOpen) => {
    if (!isOpen) return;
    form.reset();
    form.clearErrors();
    form.completed_at = localToday();
});

// Inert until the payload is valid: a date that exists and isn't in the
// future (ISO strings compare lexicographically), and a cost that is empty
// or a non-negative number.
const canSubmit = computed(() => form.completed_at !== '' && form.completed_at <= localToday() && (form.cost === '' || Number(form.cost) >= 0));

function submit() {
    if (!props.task) return;
    form.post(maintenanceTasks.complete([props.item.id, props.task.id]).url, {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
        },
    });
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ $t('maintenance.done_dialog.title', { title: task?.title ?? '' }) }}</DialogTitle>
                <DialogDescription>{{ $t('maintenance.done_dialog.description') }}</DialogDescription>
            </DialogHeader>

            <form class="form" @submit.prevent="submit">
                <div class="form-row">
                    <label for="done-date">{{ $t('maintenance.done_dialog.date_label') }}</label>
                    <input
                        id="done-date"
                        v-model="form.completed_at"
                        type="date"
                        :max="localToday()"
                        class="field"
                        data-test="maintenance-done-date"
                    />
                    <InputError :message="form.errors.completed_at" />
                </div>

                <div class="form-row">
                    <label for="done-notes">{{ $t('maintenance.done_dialog.notes_label') }}</label>
                    <textarea id="done-notes" v-model="form.notes" rows="2" class="field" data-test="maintenance-done-notes" />
                    <InputError :message="form.errors.notes" />
                </div>

                <div class="form-row">
                    <label for="done-cost">{{ $t('maintenance.done_dialog.cost_label', { code: currency.code }) }}</label>
                    <input
                        id="done-cost"
                        v-model="form.cost"
                        type="number"
                        step="0.01"
                        min="0"
                        class="field"
                        style="max-width: 140px"
                        data-test="maintenance-done-cost"
                    />
                    <InputError :message="form.errors.cost" />
                </div>

                <DialogFooter>
                    <DialogClose as-child>
                        <button type="button" class="btn-ghost">{{ $t('common.cancel') }}</button>
                    </DialogClose>
                    <button type="submit" class="btn-primary" :disabled="form.processing || !canSubmit" data-test="maintenance-done-submit">
                        <Check :size="14" />
                        {{ $t('maintenance.done_dialog.submit') }}
                    </button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
