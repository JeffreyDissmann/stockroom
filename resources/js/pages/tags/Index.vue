<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItemType } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Check, Pencil, Plus, Trash2, X } from 'lucide-vue-next';
import { ref } from 'vue';

interface TagRow {
    id: number;
    name: string;
    slug: string;
    color: string | null;
    items_count: number;
}

defineProps<{ tags: TagRow[] }>();

const breadcrumbs: BreadcrumbItemType[] = [{ title: 'Tags', href: '/tags' }];

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
    if (!confirm(`Delete tag "${tag.name}"? It will be removed from ${tag.items_count} item(s).`)) return;
    router.delete(`/tags/${tag.id}`, { preserveScroll: true });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Tags" />

        <div class="page">
            <h2 style="margin: 0 0 4px; font-size: 22px; font-weight: 600; letter-spacing: -0.015em">Tags</h2>
            <p class="sub" style="color: var(--fg-muted); font-size: 13px; margin: 0 0 20px">
                Free-form labels you can attach to any item.
            </p>

            <form class="card card-pad mb-6" @submit.prevent="submitCreate">
                <div class="grid gap-3 sm:grid-cols-[1fr_140px_auto] sm:items-end">
                    <div class="form-row">
                        <label for="new-name">New tag</label>
                        <input id="new-name" v-model="createForm.name" required placeholder="e.g. tools" class="field" />
                        <InputError :message="createForm.errors.name" />
                    </div>
                    <div class="form-row">
                        <label for="new-color">Color</label>
                        <input id="new-color" v-model="createForm.color" type="color" class="field" style="padding: 2px; height: 32px" />
                        <InputError :message="createForm.errors.color" />
                    </div>
                    <button type="submit" :disabled="createForm.processing" class="btn-primary" style="height: 32px">
                        <Plus :size="14" />
                        Add tag
                    </button>
                </div>
            </form>

            <div v-if="tags.length === 0" class="card card-pad" style="text-align: center; color: var(--fg-muted)">
                No tags yet.
            </div>

            <div v-else class="card">
                <div v-for="(tag, i) in tags" :key="tag.id" :style="{ borderTop: i ? '1px solid var(--border)' : '' }">
                    <div v-if="editing?.id === tag.id" class="card-pad">
                        <div class="grid gap-3 sm:grid-cols-[1fr_140px_auto] sm:items-end">
                            <div class="form-row">
                                <label :for="`edit-name-${tag.id}`">Name</label>
                                <input :id="`edit-name-${tag.id}`" v-model="editForm.name" required class="field" />
                                <InputError :message="editForm.errors.name" />
                            </div>
                            <div class="form-row">
                                <label :for="`edit-color-${tag.id}`">Color</label>
                                <input :id="`edit-color-${tag.id}`" v-model="editForm.color" type="color" class="field" style="padding: 2px; height: 32px" />
                                <InputError :message="editForm.errors.color" />
                            </div>
                            <div class="flex gap-2">
                                <button class="btn-primary" type="button" :disabled="editForm.processing" @click="submitEdit">
                                    <Check :size="14" />
                                    Save
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
                            :title="`Show items tagged “${tag.name}”`"
                        >
                            <div class="font-medium group-hover:underline" style="font-size: 13px">{{ tag.name }}</div>
                            <div class="mono" style="font-size: 11.5px; color: var(--fg-subtle)">
                                {{ tag.items_count }} item{{ tag.items_count === 1 ? '' : 's' }}
                            </div>
                        </Link>
                        <div class="flex gap-1">
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
