<script setup lang="ts">
import SearchImageDialog from '@/components/SearchImageDialog.vue';
import type { ItemImageSummary, SharedData } from '@/types';
import { useSortable } from '@vueuse/integrations/useSortable';
import { router, usePage } from '@inertiajs/vue3';
import { GripVertical, ImagePlus, Star, Trash2, Upload } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';

type Mode = 'create' | 'edit';

interface PendingFile {
    id: string;
    file: File;
    previewUrl: string;
}

const props = withDefaults(
    defineProps<{
        mode: Mode;
        itemId?: number | null;
        existing?: ItemImageSummary[];
        files?: File[];
    }>(),
    {
        itemId: null,
        existing: () => [],
        files: () => [],
    },
);

const emit = defineEmits<{
    (e: 'update:files', files: File[]): void;
}>();

const page = usePage<SharedData>();
const imageSearchEnabled = computed(() => page.props.features.imageSearch);

const dragOver = ref(false);
const inputEl = ref<HTMLInputElement | null>(null);

// In create mode we hold a local queue of files we'll submit with the form.
// Each gets a stable id + object URL for preview.
const pending = ref<PendingFile[]>([]);

watch(
    () => props.files,
    (newFiles) => {
        // External replacement (e.g. form reset) — re-sync.
        const stillPresent = pending.value.filter((p) => newFiles.includes(p.file));
        if (stillPresent.length !== pending.value.length) {
            pending.value.forEach((p) => {
                if (!newFiles.includes(p.file)) URL.revokeObjectURL(p.previewUrl);
            });
            pending.value = stillPresent;
        }
    },
);

onBeforeUnmount(() => {
    pending.value.forEach((p) => URL.revokeObjectURL(p.previewUrl));
});

function makePending(file: File): PendingFile {
    return {
        id: `${file.name}-${file.lastModified}-${Math.random().toString(36).slice(2, 8)}`,
        file,
        previewUrl: URL.createObjectURL(file),
    };
}

function syncPendingToProp() {
    emit('update:files', pending.value.map((p) => p.file));
}

function addFiles(fileList: FileList | File[]) {
    const incoming = Array.from(fileList).filter((f) => f.type.startsWith('image/'));
    if (incoming.length === 0) return;

    if (props.mode === 'create') {
        pending.value = [...pending.value, ...incoming.map(makePending)];
        syncPendingToProp();
    } else if (props.itemId) {
        const fd = new FormData();
        incoming.forEach((f) => fd.append('images[]', f));
        router.post(`/items/${props.itemId}/images`, fd, { preserveScroll: true, forceFormData: true });
    }
}

function removePending(id: string) {
    const target = pending.value.find((p) => p.id === id);
    if (target) URL.revokeObjectURL(target.previewUrl);
    pending.value = pending.value.filter((p) => p.id !== id);
    syncPendingToProp();
}

function handleDrop(e: DragEvent) {
    e.preventDefault();
    dragOver.value = false;
    if (e.dataTransfer?.files) addFiles(e.dataTransfer.files);
}

function handlePick(e: Event) {
    const target = e.target as HTMLInputElement;
    if (target.files) addFiles(target.files);
    target.value = '';
}

// ── Edit-mode operations on saved images ────────────────────────────────
function makePrimary(image: ItemImageSummary) {
    if (!props.itemId) return;
    router.patch(`/items/${props.itemId}/images/${image.id}`, { is_primary: true }, { preserveScroll: true });
}

function destroyImage(image: ItemImageSummary) {
    if (!props.itemId) return;
    if (!confirm('Delete this image?')) return;
    router.delete(`/items/${props.itemId}/images/${image.id}`, { preserveScroll: true });
}

// Drag-and-drop reorder of existing images
const sortableList = ref<HTMLElement | null>(null);
const sortableExisting = ref<ItemImageSummary[]>([]);

watch(
    () => props.existing,
    (val) => {
        sortableExisting.value = [...val];
    },
    { immediate: true, deep: true },
);

useSortable(sortableList, sortableExisting, {
    handle: '.drag-handle',
    animation: 150,
    onEnd: () => {
        if (!props.itemId) return;
        const ids = sortableExisting.value.map((i) => i.id);
        router.patch(
            `/items/${props.itemId}/images/order`,
            { ids },
            { preserveScroll: true },
        );
    },
});

const totalCount = computed(() => sortableExisting.value.length + pending.value.length);
</script>

<template>
    <div class="form-row">
        <label>Images</label>

        <div
            class="dropzone"
            :class="{ 'dropzone-active': dragOver }"
            @dragover.prevent="dragOver = true"
            @dragleave.prevent="dragOver = false"
            @drop="handleDrop"
            @click="inputEl?.click()"
            role="button"
            tabindex="0"
        >
            <Upload :size="18" />
            <div>
                <p class="dz-title">Drop images here or click to pick</p>
                <p class="dz-hint">JPG / PNG / WebP / HEIC, up to 10 MB each</p>
            </div>
            <input
                ref="inputEl"
                type="file"
                multiple
                accept="image/jpeg,image/png,image/webp,image/heic"
                class="hidden"
                data-test="image-input"
                @change="handlePick"
            />
        </div>

        <div v-if="mode === 'edit' && itemId && imageSearchEnabled" class="search-row">
            <span class="hint">or</span>
            <SearchImageDialog :item-id="itemId" />
        </div>

        <p v-if="totalCount === 0" class="hint">No images yet.</p>

        <div ref="sortableList" v-if="sortableExisting.length > 0" class="img-grid">
            <div v-for="image in sortableExisting" :key="image.id" class="img-card">
                <button type="button" class="drag-handle" aria-label="Reorder">
                    <GripVertical :size="14" />
                </button>
                <img :src="image.thumb_url" :alt="''" class="img-thumb" />
                <div class="img-actions">
                    <button
                        type="button"
                        class="img-btn"
                        :class="{ 'img-btn-primary': image.is_primary }"
                        :title="image.is_primary ? 'Primary image' : 'Make primary'"
                        @click="makePrimary(image)"
                        :disabled="image.is_primary"
                    >
                        <Star :size="13" :fill="image.is_primary ? 'currentColor' : 'none'" />
                    </button>
                    <button
                        type="button"
                        class="img-btn img-btn-danger"
                        title="Delete image"
                        @click="destroyImage(image)"
                    >
                        <Trash2 :size="13" />
                    </button>
                </div>
            </div>
        </div>

        <div v-if="pending.length > 0" class="img-grid">
            <div v-for="p in pending" :key="p.id" class="img-card img-card-pending">
                <img :src="p.previewUrl" alt="" class="img-thumb" />
                <span class="pending-badge"><ImagePlus :size="11" /> Queued</span>
                <div class="img-actions">
                    <button type="button" class="img-btn img-btn-danger" title="Remove" @click="removePending(p.id)">
                        <Trash2 :size="13" />
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.hidden { display: none; }
.dropzone {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 16px;
    border: 1px dashed var(--border-strong);
    border-radius: var(--radius);
    background: var(--bg-sunken);
    color: var(--fg-muted);
    cursor: pointer;
    transition: background 0.12s, border-color 0.12s;
}
.dropzone:hover { background: var(--bg-hover); }
.dropzone-active {
    background: var(--bg-active);
    border-color: var(--fg);
    color: var(--fg);
}
.dz-title { margin: 0; font-size: 13px; font-weight: 500; color: var(--fg); }
.dz-hint { margin: 2px 0 0; font-size: 11.5px; color: var(--fg-subtle); }
.hint { margin: 0; font-size: 12px; color: var(--fg-subtle); }
.search-row { display: flex; align-items: center; gap: 8px; margin-top: 8px; }

/* .img-grid / .img-card / .img-thumb are shared globals (see app.css). */
.img-grid { margin-top: 4px; }
.img-card-pending { opacity: 0.85; }
.drag-handle {
    position: absolute;
    top: 4px;
    left: 4px;
    width: 22px;
    height: 22px;
    display: grid;
    place-items: center;
    background: rgba(0, 0, 0, 0.45);
    color: white;
    border-radius: 4px;
    border: 0;
    cursor: grab;
    opacity: 0;
    transition: opacity 0.12s;
}
.img-card:hover .drag-handle { opacity: 1; }
.img-actions {
    position: absolute;
    bottom: 4px;
    right: 4px;
    display: flex;
    gap: 3px;
}
.img-btn {
    width: 22px;
    height: 22px;
    display: grid;
    place-items: center;
    background: rgba(0, 0, 0, 0.55);
    color: white;
    border: 0;
    border-radius: 4px;
    cursor: pointer;
}
.img-btn:hover { background: rgba(0, 0, 0, 0.75); }
.img-btn[disabled] { cursor: default; }
.img-btn-primary { background: #f59e0b; }
.img-btn-primary:hover { background: #f59e0b; }
.img-btn-danger:hover { background: #b91c1c; }
.pending-badge {
    position: absolute;
    top: 4px;
    left: 4px;
    display: inline-flex;
    align-items: center;
    gap: 3px;
    background: rgba(0, 0, 0, 0.55);
    color: white;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 10px;
}
</style>
