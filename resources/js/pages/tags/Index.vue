<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { useIsAdmin } from '@/composables/useIsAdmin';
import { trans, transChoice } from '@/composables/useTranslations';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItemType } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Check, Pencil, Plus, Trash2, X } from 'lucide-vue-next';
import { ref } from 'vue';

const isAdmin = useIsAdmin();

interface TagRow {
    id: number;
    name: string;
    slug: string;
    color: string | null;
    items_count: number;
}

defineProps<{ tags: TagRow[] }>();

const breadcrumbs: BreadcrumbItemType[] = [{ title: trans('tags.title'), href: '/tags' }];

// A native color input always shows a colour (black for an empty value), so the
// form must start with a real default — otherwise an untouched picker submits
// nothing and the tag ends up colourless.
const createForm = useForm({ name: '', color: '#64748b' });
const editing = ref<TagRow | null>(null);
const editForm = useForm({ name: '', color: '' as string | null });

function startEdit(tag: TagRow) {
    editing.value = tag;
    editForm.reset();
    editForm.clearErrors();
    editForm.name = tag.name;
    editForm.color = tag.color ?? '';
}

function cancelEdit() {
    editing.value = null;
}

function submitCreate() {
    createForm
        .transform((data) => ({ ...data, color: data.color || null }))
        .post('/tags', {
            preserveScroll: true,
            onSuccess: () => createForm.reset(),
        });
}

function submitEdit() {
    if (!editing.value) return;
    editForm
        .transform((data) => ({ ...data, color: data.color || null }))
        .put(`/tags/${editing.value.id}`, {
            preserveScroll: true,
            onSuccess: () => (editing.value = null),
        });
}

function destroyTag(tag: TagRow) {
    if (!confirm(trans('tags.delete_confirm', { name: tag.name, count: tag.items_count }))) return;
    router.delete(`/tags/${tag.id}`, { preserveScroll: true });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="$t('tags.title')" />

        <div class="page">
            <h2 style="margin: 0 0 4px; font-size: 22px; font-weight: 600; letter-spacing: -0.015em">{{ $t('tags.title') }}</h2>
            <p class="sub" style="color: var(--fg-muted); font-size: 13px; margin: 0 0 20px">
                {{ $t('tags.subtitle') }}
            </p>

            <form v-if="isAdmin" class="card card-pad mb-6" @submit.prevent="submitCreate">
                <div class="grid gap-3 sm:grid-cols-[1fr_140px_auto] sm:items-end">
                    <div class="form-row">
                        <label for="new-name">{{ $t('tags.new_tag') }}</label>
                        <input id="new-name" v-model="createForm.name" required :placeholder="$t('tags.name_placeholder')" class="field" />
                        <InputError :message="createForm.errors.name" />
                    </div>
                    <div class="form-row">
                        <label for="new-color">{{ $t('tags.color') }}</label>
                        <input id="new-color" v-model="createForm.color" type="color" class="field" style="padding: 2px; height: 32px" />
                        <InputError :message="createForm.errors.color" />
                    </div>
                    <button type="submit" :disabled="createForm.processing" class="btn-primary" style="height: 32px">
                        <Plus :size="14" />
                        {{ $t('tags.add') }}
                    </button>
                </div>
            </form>

            <div v-if="tags.length === 0" class="card card-pad" style="text-align: center; color: var(--fg-muted)">
                {{ $t('tags.empty') }}
            </div>

            <div v-else class="card">
                <div v-for="(tag, i) in tags" :key="tag.id" :style="{ borderTop: i ? '1px solid var(--border)' : '' }">
                    <div v-if="editing?.id === tag.id" class="card-pad">
                        <div class="grid gap-3 sm:grid-cols-[1fr_140px_auto] sm:items-end">
                            <div class="form-row">
                                <label :for="`edit-name-${tag.id}`">{{ $t('common.name') }}</label>
                                <input :id="`edit-name-${tag.id}`" v-model="editForm.name" required class="field" />
                                <InputError :message="editForm.errors.name" />
                            </div>
                            <div class="form-row">
                                <label :for="`edit-color-${tag.id}`">{{ $t('tags.color') }}</label>
                                <input :id="`edit-color-${tag.id}`" v-model="editForm.color" type="color" class="field" style="padding: 2px; height: 32px" />
                                <InputError :message="editForm.errors.color" />
                            </div>
                            <div class="flex gap-2">
                                <button class="btn-primary" type="button" :disabled="editForm.processing" @click="submitEdit">
                                    <Check :size="14" />
                                    {{ $t('common.save') }}
                                </button>
                                <button class="btn-ghost" type="button" @click="cancelEdit">
                                    <X :size="14" />
                                </button>
                            </div>
                        </div>
                    </div>

                    <div v-else class="flex items-center gap-3 px-4 py-3">
                        <span
                            class="size-3 shrink-0 rounded-full"
                            :style="{ background: tag.color ?? 'transparent', border: tag.color ? 'none' : '1px solid var(--border)' }"
                        />
                        <Link
                            :href="`/search?tags[]=${tag.id}`"
                            class="group min-w-0 flex-1"
                            :title="trans('tags.show_tagged', { name: tag.name })"
                        >
                            <div class="font-medium group-hover:underline" style="font-size: 13px">{{ tag.name }}</div>
                            <div class="mono" style="font-size: 11.5px; color: var(--fg-subtle)">
                                {{ transChoice('tags.items_count', tag.items_count) }}
                            </div>
                        </Link>
                        <div v-if="isAdmin" class="flex gap-1">
                            <button class="btn-ghost" type="button" @click="startEdit(tag)">
                                <Pencil :size="14" />
                            </button>
                            <button class="btn-ghost btn-danger" type="button" @click="destroyTag(tag)">
                                <Trash2 :size="14" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
