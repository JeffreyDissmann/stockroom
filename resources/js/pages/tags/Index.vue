<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2, X } from 'lucide-vue-next';
import { ref } from 'vue';

interface TagRow {
    id: number;
    name: string;
    slug: string;
    color: string | null;
    items_count: number;
}

defineProps<{
    tags: TagRow[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Tags', href: '/tags' }];

const createForm = useForm({ name: '', color: '' as string | null });
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
    if (!confirm(`Delete tag "${tag.name}"? It will be removed from ${tag.items_count} item(s).`)) {
        return;
    }
    router.delete(`/tags/${tag.id}`, { preserveScroll: true });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Tags" />

        <div class="flex flex-col gap-6 p-4 md:p-6">
            <h1 class="text-2xl font-semibold tracking-tight">Tags</h1>

            <form class="grid gap-4 rounded-lg border bg-card p-4 sm:grid-cols-[1fr_140px_auto] sm:items-end" @submit.prevent="submitCreate">
                <div class="grid gap-2">
                    <Label for="new-name">Name</Label>
                    <Input id="new-name" v-model="createForm.name" required placeholder="e.g. tools" />
                    <InputError :message="createForm.errors.name" />
                </div>
                <div class="grid gap-2">
                    <Label for="new-color">Color</Label>
                    <Input id="new-color" v-model="createForm.color" type="color" class="h-9 p-1" />
                    <InputError :message="createForm.errors.color" />
                </div>
                <Button type="submit" :disabled="createForm.processing">
                    <Plus class="mr-1 size-4" />
                    Add tag
                </Button>
            </form>

            <div v-if="tags.length === 0" class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground">
                No tags yet.
            </div>

            <ul v-else class="divide-y rounded-lg border bg-card">
                <li v-for="tag in tags" :key="tag.id" class="p-4">
                    <div v-if="editing?.id === tag.id" class="grid gap-3 sm:grid-cols-[1fr_140px_auto] sm:items-end">
                        <div class="grid gap-2">
                            <Label :for="`edit-name-${tag.id}`">Name</Label>
                            <Input :id="`edit-name-${tag.id}`" v-model="editForm.name" required />
                            <InputError :message="editForm.errors.name" />
                        </div>
                        <div class="grid gap-2">
                            <Label :for="`edit-color-${tag.id}`">Color</Label>
                            <Input :id="`edit-color-${tag.id}`" v-model="editForm.color" type="color" class="h-9 p-1" />
                            <InputError :message="editForm.errors.color" />
                        </div>
                        <div class="flex gap-2">
                            <Button size="sm" :disabled="editForm.processing" @click="submitEdit">Save</Button>
                            <Button size="sm" variant="ghost" type="button" @click="cancelEdit">
                                <X class="size-4" />
                            </Button>
                        </div>
                    </div>

                    <div v-else class="flex items-center justify-between gap-3">
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="size-3 shrink-0 rounded-full border" :style="{ backgroundColor: tag.color ?? 'transparent' }" />
                            <div class="min-w-0">
                                <p class="truncate font-medium">{{ tag.name }}</p>
                                <p class="text-xs text-muted-foreground">{{ tag.items_count }} item(s)</p>
                            </div>
                        </div>
                        <div class="flex gap-1">
                            <Button size="sm" variant="ghost" type="button" @click="startEdit(tag)">
                                <Pencil class="size-4" />
                            </Button>
                            <Button size="sm" variant="ghost" type="button" class="text-destructive hover:text-destructive" @click="destroyTag(tag)">
                                <Trash2 class="size-4" />
                            </Button>
                        </div>
                    </div>
                </li>
            </ul>
        </div>
    </AppLayout>
</template>
