<script setup lang="ts">
/**
 * The single confirmation dialog, mounted once in AppLayout and driven through
 * the `confirm()` composable.
 *
 * Replaces fifteen native confirm() calls that ranged from unlinking a document
 * to wiping the whole household — every one of them an unstyled browser alert,
 * while deleting your own account (the mildest of them) already had a proper
 * dialog. This is that dialog, made available to all of them.
 */
import { useConfirmHost } from '@/composables/useConfirm';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';

const { isOpen, pending, confirmLabel, accept, dismiss } = useConfirmHost();

// Radix emits update:open for Escape and outside clicks too, so this is the one
// place that has to translate "closed somehow" into "declined".
function onOpenChange(open: boolean) {
    if (!open) dismiss();
}
</script>

<template>
    <Dialog :open="isOpen" @update:open="onOpenChange">
        <DialogContent v-if="pending">
            <!-- The marker sits here, not on DialogContent: that component's
                 root is a DialogPortal, so fallthrough attributes never reach
                 the DOM and a `data-test` on it is silently dropped. -->
            <DialogHeader class="space-y-3" data-test="confirm-dialog">
                <DialogTitle>{{ pending.message }}</DialogTitle>
                <DialogDescription v-if="pending.description">{{ pending.description }}</DialogDescription>
            </DialogHeader>

            <DialogFooter>
                <Button variant="secondary" data-test="confirm-cancel" @click="dismiss">{{ $t('common.cancel') }}</Button>
                <Button :variant="pending.destructive === false ? 'default' : 'destructive'" data-test="confirm-accept" @click="accept">
                    {{ confirmLabel() }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
