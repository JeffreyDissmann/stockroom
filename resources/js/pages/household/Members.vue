<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import HouseholdLayout from '@/layouts/household/Layout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Check, Copy, Plus, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';

interface InvitationRow {
    id: number;
    label: string | null;
    url: string;
    created_human: string | null;
    expires_human: string | null;
    created_by: string | null;
}

interface MemberRow {
    id: number;
    name: string;
    email: string;
    joined_human: string | null;
    is_self: boolean;
}

defineProps<{ invitations: InvitationRow[]; members: MemberRow[] }>();

const breadcrumbItems: BreadcrumbItem[] = [{ title: 'Members', href: '/household/members' }];

const createForm = useForm<{ label: string }>({ label: '' });
function createInvite() {
    createForm.post('/household/invitations', { preserveScroll: true, onSuccess: () => createForm.reset() });
}

const copiedId = ref<number | null>(null);
async function copyLink(invitation: InvitationRow) {
    try {
        await navigator.clipboard.writeText(invitation.url);
        copiedId.value = invitation.id;
        window.setTimeout(() => (copiedId.value = null), 1500);
    } catch {
        // Clipboard API unavailable (e.g. non-secure context) — the field is selectable as a fallback.
    }
}

function revoke(invitation: InvitationRow) {
    if (!confirm('Revoke this invite link? It will stop working immediately.')) return;
    router.delete(`/household/invitations/${invitation.id}`, { preserveScroll: true });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Members" />

        <HouseholdLayout>
            <div class="space-y-10">
                <div class="space-y-6">
                    <HeadingSmall
                        title="Invite links"
                        description="Create a single-use link and share it however you like. It expires after 7 days, and the person sets their own name, email, and password when they join."
                    />

                    <form class="flex flex-wrap items-center gap-2" data-test="invite-create" @submit.prevent="createInvite">
                        <div class="flex-1" style="min-width: 180px">
                            <input v-model="createForm.label" placeholder="Label (optional, e.g. For Anna)" class="field w-full" data-test="invite-label" maxlength="100" />
                            <InputError :message="createForm.errors.label" />
                        </div>
                        <button type="submit" class="btn-primary" style="height: 32px" :disabled="createForm.processing">
                            <Plus :size="14" />
                            Create invite link
                        </button>
                    </form>

                    <div v-if="invitations.length === 0" class="text-sm" style="color: var(--fg-muted)">No active invite links.</div>

                    <ul v-else class="divide-y" style="border-top: 1px solid var(--border)">
                        <li v-for="invitation in invitations" :key="invitation.id" class="space-y-2 py-3" style="border-bottom: 1px solid var(--border)">
                            <div class="flex items-center gap-3">
                                <div class="flex-1">
                                    <div style="font-weight: 500; font-size: 14px">{{ invitation.label || 'Invite link' }}</div>
                                    <div class="text-xs" style="color: var(--fg-muted)">
                                        Expires {{ invitation.expires_human }}<template v-if="invitation.created_by"> · from {{ invitation.created_by }}</template>
                                    </div>
                                </div>
                                <button type="button" class="btn-ghost btn-danger" title="Revoke link" @click="revoke(invitation)"><Trash2 :size="14" /></button>
                            </div>
                            <div class="flex items-center gap-2">
                                <input :value="invitation.url" readonly class="field flex-1 mono" style="font-size: 12px" @focus="(e) => (e.target as HTMLInputElement).select()" />
                                <button type="button" class="btn-pill" @click="copyLink(invitation)">
                                    <component :is="copiedId === invitation.id ? Check : Copy" :size="14" />
                                    {{ copiedId === invitation.id ? 'Copied' : 'Copy' }}
                                </button>
                            </div>
                        </li>
                    </ul>
                </div>

                <div class="space-y-4">
                    <HeadingSmall title="People" description="Everyone with access to this home inventory." />

                    <ul class="divide-y" style="border-top: 1px solid var(--border)">
                        <li v-for="member in members" :key="member.id" class="flex items-center gap-3 py-3" style="border-bottom: 1px solid var(--border)">
                            <div class="flex-1">
                                <div style="font-weight: 500; font-size: 14px">
                                    {{ member.name }}
                                    <span v-if="member.is_self" class="text-xs" style="color: var(--fg-subtle)">(you)</span>
                                </div>
                                <div class="text-xs" style="color: var(--fg-muted)">{{ member.email }}</div>
                            </div>
                            <div v-if="member.joined_human" class="text-xs" style="color: var(--fg-subtle)">Joined {{ member.joined_human }}</div>
                        </li>
                    </ul>
                </div>
            </div>
        </HouseholdLayout>
    </AppLayout>
</template>
