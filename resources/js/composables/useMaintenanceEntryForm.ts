import { localToday } from '@/lib/date';
import { useForm } from '@inertiajs/vue3';
import { computed, watch, type Ref } from 'vue';

/**
 * The shared form behind both maintenance-entry dialogs ("mark done" and
 * ad-hoc "log entry"): completed_at / notes / cost with identical
 * transform, reset-on-open and validity rules. The only behavioural
 * difference between the dialogs is whether notes are mandatory (ad-hoc
 * entries have no task title, so the notes are their only description).
 */
export function useMaintenanceEntryForm(open: Ref<boolean>, options: { requireNotes: boolean }) {
    const form = useForm({
        completed_at: localToday(),
        notes: '',
        cost: '',
    });

    form.transform((data) => ({
        completed_at: data.completed_at,
        notes: options.requireNotes ? data.notes : data.notes || null,
        cost: data.cost === '' ? null : data.cost,
    }));

    // Re-prime whenever the dialog opens so a previous attempt's edits
    // don't bleed into the next one.
    watch(open, (isOpen) => {
        if (!isOpen) return;
        form.reset();
        form.clearErrors();
        form.completed_at = localToday();
    });

    // Inert until the payload is valid: a date that exists and isn't in
    // the future (ISO strings compare lexicographically), a cost that is
    // empty or a non-negative number, and notes when they're mandatory.
    const canSubmit = computed(
        () =>
            (!options.requireNotes || form.notes.trim() !== '') &&
            form.completed_at !== '' &&
            form.completed_at <= localToday() &&
            (form.cost === '' || Number(form.cost) >= 0),
    );

    function submit(url: string) {
        form.post(url, {
            preserveScroll: true,
            onSuccess: () => {
                open.value = false;
            },
        });
    }

    return { form, canSubmit, submit };
}
