import { trans } from '@/composables/useTranslations';
import { ref, shallowRef } from 'vue';

export interface ConfirmOptions {
    /**
     * The question itself. These read as complete sentences already ("Delete
     * «Drill»? This cannot be undone."), because they were written for the
     * native confirm() this replaces.
     */
    message: string;
    /** Extra context under the question. Most callers do not need it. */
    description?: string;
    /** Defaults to common.confirm. */
    confirmLabel?: string;
    /** Destructive styling on the confirm button. On by default — nearly every caller is deleting something. */
    destructive?: boolean;
}

interface PendingConfirm extends ConfirmOptions {
    resolve: (confirmed: boolean) => void;
}

/**
 * Module-level so a single <ConfirmDialog> mounted in AppLayout serves every
 * call site. Without this each of the fifteen callers would need its own ref
 * and its own dialog block in the template.
 */
const pending = shallowRef<PendingConfirm | null>(null);
const isOpen = ref(false);

/**
 * Ask the user to confirm, resolving true only if they accept.
 *
 * Deliberately shaped as a drop-in for the native `confirm()` it replaces, so
 * the call sites keep their guard-clause shape:
 *
 *   if (!(await confirm({ message: trans('tags.delete_confirm', { name }) }))) return;
 *
 * A native confirm() cannot be styled, ignores dark mode, and is auto-dismissed
 * by headless Chromium — which is why none of these flows could be covered by a
 * browser test before.
 */
export function confirm(options: ConfirmOptions): Promise<boolean> {
    // A second ask while one is open would strand the first promise.
    pending.value?.resolve(false);

    return new Promise<boolean>((resolve) => {
        pending.value = { ...options, resolve };
        isOpen.value = true;
    });
}

/** Internal — used by the ConfirmDialog host, not by callers. */
export function useConfirmHost() {
    function settle(confirmed: boolean) {
        pending.value?.resolve(confirmed);
        pending.value = null;
        isOpen.value = false;
    }

    return {
        isOpen,
        pending,
        confirmLabel: () => pending.value?.confirmLabel ?? trans('common.confirm'),
        accept: () => settle(true),
        // Covers the Cancel button, Escape and a click outside: dismissing in
        // any way means "no", the same as the native dialog.
        dismiss: () => settle(false),
    };
}
