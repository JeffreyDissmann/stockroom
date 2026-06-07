<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { useIsAdmin } from '@/composables/useIsAdmin';
import { trans } from '@/composables/useTranslations';
import AppLayout from '@/layouts/AppLayout.vue';
import HouseholdLayout from '@/layouts/household/Layout.vue';
import customFields from '@/routes/custom-fields';
import type { BreadcrumbItem, CustomFieldDefinition, CustomFieldTypeValue } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Check, Lock, Pencil, Plus, Search, SearchX, Trash2, X } from 'lucide-vue-next';
import { ref } from 'vue';

const isAdmin = useIsAdmin();

defineProps<{ fields: CustomFieldDefinition[] }>();

const types: { value: CustomFieldTypeValue; label: string }[] = [
    { value: 'text', label: trans('enums.custom_field_type.text') },
    { value: 'number', label: trans('enums.custom_field_type.number') },
    { value: 'date', label: trans('enums.custom_field_type.date') },
    { value: 'boolean', label: trans('enums.custom_field_type.boolean') },
    { value: 'url', label: trans('enums.custom_field_type.url') },
];

const breadcrumbItems: BreadcrumbItem[] = [{ title: trans('household.nav.custom_fields'), href: '/household/custom-fields' }];

const createForm = useForm<{ name: string; type: CustomFieldTypeValue; searchable: boolean }>({ name: '', type: 'text', searchable: false });
function add() {
    createForm.post(customFields.store().url, { preserveScroll: true, onSuccess: () => createForm.reset() });
}

const editingId = ref<number | null>(null);
const editForm = useForm<{ name: string; type: CustomFieldTypeValue; searchable: boolean }>({ name: '', type: 'text', searchable: false });
function startEdit(field: CustomFieldDefinition) {
    editingId.value = field.id;
    editForm.name = field.name;
    editForm.type = field.type;
    editForm.searchable = field.is_searchable ?? true;
    editForm.clearErrors();
}
function saveEdit(id: number) {
    editForm.put(customFields.update(id).url, { preserveScroll: true, onSuccess: () => (editingId.value = null) });
}
function destroy(field: CustomFieldDefinition) {
    if (!confirm(trans('household.custom_fields.delete_confirm', { name: field.name }))) return;
    router.delete(customFields.destroy(field.id).url, { preserveScroll: true });
}
function typeLabel(type: CustomFieldTypeValue) {
    return types.find((t) => t.value === type)?.label ?? type;
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="$t('household.nav.custom_fields')" />

        <HouseholdLayout>
            <div class="space-y-6">
                <HeadingSmall :title="$t('household.nav.custom_fields')" :description="$t('household.custom_fields.description')" />

                <form v-if="isAdmin" class="flex flex-wrap items-center gap-2" data-test="custom-field-create" @submit.prevent="add">
                    <div class="flex-1" style="min-width: 180px">
                        <input
                            v-model="createForm.name"
                            :placeholder="$t('household.custom_fields.name_placeholder')"
                            class="field w-full"
                            data-test="custom-field-name"
                        />
                        <InputError :message="createForm.errors.name" />
                    </div>
                    <select v-model="createForm.type" class="field" style="max-width: 150px" data-test="custom-field-type">
                        <option v-for="t in types" :key="t.value" :value="t.value">{{ t.label }}</option>
                    </select>
                    <label
                        class="flex items-center gap-1.5 text-sm"
                        style="color: var(--fg-muted)"
                        :title="$t('household.custom_fields.searchable_title')"
                    >
                        <input v-model="createForm.searchable" type="checkbox" data-test="custom-field-searchable" />
                        {{ $t('household.custom_fields.searchable') }}
                    </label>
                    <button type="submit" class="btn-primary" style="height: 32px" :disabled="createForm.processing || !createForm.name">
                        <Plus :size="14" />
                        {{ $t('household.custom_fields.add') }}
                    </button>
                </form>

                <div v-if="fields.length === 0" class="text-sm" style="color: var(--fg-muted)">{{ $t('household.custom_fields.empty') }}</div>

                <ul v-else class="divide-y" style="border-top: 1px solid var(--border)">
                    <li v-for="field in fields" :key="field.id" class="flex items-center gap-3 py-3" style="border-bottom: 1px solid var(--border)">
                        <template v-if="editingId === field.id">
                            <input v-model="editForm.name" class="field flex-1" />
                            <select v-model="editForm.type" class="field" style="max-width: 150px">
                                <option v-for="t in types" :key="t.value" :value="t.value">{{ t.label }}</option>
                            </select>
                            <label
                                class="flex items-center gap-1.5 text-sm"
                                style="color: var(--fg-muted)"
                                :title="$t('household.custom_fields.searchable_title')"
                            >
                                <input v-model="editForm.searchable" type="checkbox" />
                                {{ $t('household.custom_fields.searchable') }}
                            </label>
                            <button type="button" class="btn-pill" @click="saveEdit(field.id)"><Check :size="14" /> {{ $t('common.save') }}</button>
                            <button type="button" class="btn-ghost" @click="editingId = null"><X :size="14" /></button>
                        </template>
                        <template v-else>
                            <div class="flex-1">
                                <div style="font-weight: 500; font-size: 14px">{{ field.name }}</div>
                                <div class="flex items-center gap-2 text-xs" style="color: var(--fg-muted)">
                                    <span>{{ typeLabel(field.type) }}</span>
                                    <span
                                        class="inline-flex items-center gap-1"
                                        :title="field.is_searchable ? $t('household.custom_fields.included') : $t('household.custom_fields.excluded')"
                                    >
                                        <component :is="field.is_searchable ? Search : SearchX" :size="11" />
                                        {{
                                            field.is_searchable
                                                ? $t('household.custom_fields.searchable')
                                                : $t('household.custom_fields.not_searchable')
                                        }}
                                    </span>
                                </div>
                            </div>
                            <span v-if="field.is_system" class="inline-flex items-center gap-1 text-xs" style="color: var(--fg-subtle)">
                                <Lock :size="12" /> {{ $t('household.custom_fields.system') }}
                            </span>
                            <template v-else-if="isAdmin">
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
