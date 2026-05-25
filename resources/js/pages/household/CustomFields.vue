<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import HouseholdLayout from '@/layouts/household/Layout.vue';
import type { BreadcrumbItem, CustomFieldDefinition, CustomFieldTypeValue } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Check, Lock, Pencil, Plus, Trash2, X } from 'lucide-vue-next';
import { ref } from 'vue';

defineProps<{ fields: CustomFieldDefinition[] }>();

const types: { value: CustomFieldTypeValue; label: string }[] = [
    { value: 'text', label: 'Text' },
    { value: 'number', label: 'Number' },
    { value: 'date', label: 'Date' },
    { value: 'boolean', label: 'Yes / No' },
    { value: 'url', label: 'Link' },
];

const breadcrumbItems: BreadcrumbItem[] = [{ title: 'Custom fields', href: '/household/custom-fields' }];

const createForm = useForm<{ name: string; type: CustomFieldTypeValue }>({ name: '', type: 'text' });
function add() {
    createForm.post('/household/custom-fields', { preserveScroll: true, onSuccess: () => createForm.reset() });
}

const editingId = ref<number | null>(null);
const editForm = useForm<{ name: string; type: CustomFieldTypeValue }>({ name: '', type: 'text' });
function startEdit(field: CustomFieldDefinition) {
    editingId.value = field.id;
    editForm.name = field.name;
    editForm.type = field.type;
    editForm.clearErrors();
}
function saveEdit(id: number) {
    editForm.put(`/household/custom-fields/${id}`, { preserveScroll: true, onSuccess: () => (editingId.value = null) });
}
function destroy(field: CustomFieldDefinition) {
    if (!confirm(`Delete the "${field.name}" field? Its values on every item will be removed.`)) return;
    router.delete(`/household/custom-fields/${field.id}`, { preserveScroll: true });
}
function typeLabel(type: CustomFieldTypeValue) {
    return types.find((t) => t.value === type)?.label ?? type;
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Custom fields" />

        <HouseholdLayout>
            <div class="space-y-6">
                <HeadingSmall
                    title="Custom fields"
                    description="Define extra typed fields (e.g. Color, Voltage, Purchase URL) that can be filled in on any item."
                />

                <form class="flex flex-wrap items-center gap-2" data-test="custom-field-create" @submit.prevent="add">
                    <div class="flex-1" style="min-width: 180px">
                        <input v-model="createForm.name" placeholder="Field name" class="field w-full" data-test="custom-field-name" />
                        <InputError :message="createForm.errors.name" />
                    </div>
                    <select v-model="createForm.type" class="field" style="max-width: 150px" data-test="custom-field-type">
                        <option v-for="t in types" :key="t.value" :value="t.value">{{ t.label }}</option>
                    </select>
                    <button type="submit" class="btn-primary" style="height: 32px" :disabled="createForm.processing || !createForm.name">
                        <Plus :size="14" />
                        Add field
                    </button>
                </form>

                <div v-if="fields.length === 0" class="text-sm" style="color: var(--fg-muted)">No custom fields yet.</div>

                <ul v-else class="divide-y" style="border-top: 1px solid var(--border)">
                    <li v-for="field in fields" :key="field.id" class="flex items-center gap-3 py-3" style="border-bottom: 1px solid var(--border)">
                        <template v-if="editingId === field.id">
                            <input v-model="editForm.name" class="field flex-1" />
                            <select v-model="editForm.type" class="field" style="max-width: 150px">
                                <option v-for="t in types" :key="t.value" :value="t.value">{{ t.label }}</option>
                            </select>
                            <button type="button" class="btn-pill" @click="saveEdit(field.id)"><Check :size="14" /> Save</button>
                            <button type="button" class="btn-ghost" @click="editingId = null"><X :size="14" /></button>
                        </template>
                        <template v-else>
                            <div class="flex-1">
                                <div style="font-weight: 500; font-size: 14px">{{ field.name }}</div>
                                <div class="text-xs" style="color: var(--fg-muted)">{{ typeLabel(field.type) }}</div>
                            </div>
                            <span v-if="field.is_system" class="inline-flex items-center gap-1 text-xs" style="color: var(--fg-subtle)">
                                <Lock :size="12" /> System
                            </span>
                            <template v-else>
                                <button type="button" class="btn-ghost" @click="startEdit(field)"><Pencil :size="14" /></button>
                                <button type="button" class="btn-ghost btn-danger" @click="destroy(field)"><Trash2 :size="14" /></button>
                            </template>
                        </template>
                    </li>
                </ul>
            </div>
        </HouseholdLayout>
    </AppLayout>
</template>
