<script setup lang="ts">
import AiFieldBadge from '@/components/AiFieldBadge.vue';
import CustomFieldsInput from '@/components/CustomFieldsInput.vue';
import InputError from '@/components/InputError.vue';
import ItemImageManager from '@/components/ItemImageManager.vue';
import ItemTypeIcon from '@/components/ItemTypeIcon.vue';
import type { CustomFieldDefinition, ItemSummary, ItemTypeDescriptor, ItemTypeValue, SharedData, TagSummary } from '@/types';
import { useForm, usePage } from '@inertiajs/vue3';
import { Check, Loader2, Sparkles } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const currency = usePage<SharedData>().props.currency;

type Mode = 'create' | 'edit';

const props = defineProps<{
    mode: Mode;
    item?: ItemSummary | null;
    parent?: ItemSummary | null;
    items: ItemSummary[];
    tags: TagSummary[];
    types: ItemTypeDescriptor[];
    customFields: CustomFieldDefinition[];
    submitLabel?: string;
}>();

const form = useForm({
    name: props.item?.name ?? '',
    description: props.item?.description ?? '',
    type: (props.item?.type.value ?? (props.parent ? 'item' : 'room')) as ItemTypeValue,
    parent_id: props.item?.parent_id ?? props.parent?.id ?? null,
    tags: (props.item?.tags ?? []).map((t) => t.id),
    images: [] as File[],
    quantity: props.item?.quantity ?? 1,
    purchased_from: props.item?.purchased_from ?? '',
    purchase_date: props.item?.purchase_date ?? '',
    purchase_price: props.item?.purchase_price ?? '',
    manufacturer: props.item?.manufacturer ?? '',
    model_number: props.item?.model_number ?? '',
    serial_number: props.item?.serial_number ?? '',
    lifetime_warranty: props.item?.lifetime_warranty ?? false,
    warranty_expires: props.item?.warranty_expires ?? '',
    warranty_details: props.item?.warranty_details ?? '',
    sold_to: props.item?.sold_to ?? '',
    sold_price: props.item?.sold_price ?? '',
    sold_date: props.item?.sold_date ?? '',
    sold_notes: props.item?.sold_notes ?? '',
    custom_fields: Object.fromEntries(
        (props.item?.custom_fields ?? []).map((f) => [f.custom_field_id, f.value]),
    ) as Record<number, string | number | boolean | null>,
});

const queuedFiles = ref<File[]>([]);

function onFilesUpdate(files: File[]) {
    queuedFiles.value = files;
    form.images = files;
}

// ── AI: pre-fill the form from a photo ──────────────────────────────────────
// Vision inference can take a few seconds (and longer on a cold model), so the
// call is fully async with a visible busy state, a disabled trigger, and a
// hard client-side timeout so the UI never hangs indefinitely.
const aiEnabled = usePage<SharedData>().props.features.ai;
const analyzing = ref(false);
const analyzeError = ref<string | null>(null);

// Field keys the most recent analysis populated, so the UI can flag them for review.
const aiFilled = ref<Set<string>>(new Set());

// Fields the photo analysis can populate. While analysing they show a "pending"
// cue so the user sees values are still coming; once filled, a "suggested" cue.
const aiCandidates = ['name', 'description', 'manufacturer', 'model_number', 'serial_number'];

type AiFieldState = 'pending' | 'suggested' | null;

const fieldStates = computed<Record<string, AiFieldState>>(() => {
    const states: Record<string, AiFieldState> = {};
    for (const key of aiCandidates) {
        states[key] = analyzing.value ? 'pending' : aiFilled.value.has(key) ? 'suggested' : null;
    }
    return states;
});

const analyzeHint = computed(() => {
    if (analyzing.value) {
        return 'The vision model is looking at your photo…';
    }
    if (aiFilled.value.size > 0) {
        const n = aiFilled.value.size;
        return `Filled ${n} field${n === 1 ? '' : 's'} from the photo — review the highlighted fields before saving.`;
    }
    return 'Reads the first photo to pre-fill the fields below — review before saving.';
});

const ANALYZE_TIMEOUT_MS = 130_000; // a touch beyond the server-side agent timeout

function readXsrfToken(): string {
    const match = document.cookie.split('; ').find((c) => c.startsWith('XSRF-TOKEN='));
    return match ? decodeURIComponent(match.split('=')[1]) : '';
}

async function analyzeFromPhoto() {
    const photo = queuedFiles.value[0];
    if (!photo || analyzing.value) {
        return;
    }

    analyzing.value = true;
    analyzeError.value = null;

    const controller = new AbortController();
    const timeout = window.setTimeout(() => controller.abort(), ANALYZE_TIMEOUT_MS);

    try {
        const body = new FormData();
        body.append('photo', photo);

        const response = await fetch('/items/analyze-photo', {
            method: 'POST',
            headers: { Accept: 'application/json', 'X-XSRF-TOKEN': readXsrfToken() },
            credentials: 'same-origin',
            body,
            signal: controller.signal,
        });

        if (!response.ok) {
            throw new Error(String(response.status));
        }

        const { fields } = (await response.json()) as { fields: Record<string, string | null> };
        applyDetectedFields(fields);
    } catch (error) {
        analyzeError.value =
            error instanceof DOMException && error.name === 'AbortError'
                ? 'The photo is taking too long to analyse. Please try again or fill the form manually.'
                : 'Could not read that photo. Fill the form manually, or try another image.';
    } finally {
        window.clearTimeout(timeout);
        analyzing.value = false;
    }
}

// The server already trims values and nulls out blanks, so apply each present
// field directly and remember which ones we touched (to flag them for review).
function applyDetectedFields(fields: Record<string, string | null>) {
    const filled = new Set<string>();

    if (fields.name) {
        form.name = fields.name;
        filled.add('name');
    }
    if (fields.description) {
        form.description = fields.description;
        filled.add('description');
    }
    if (fields.manufacturer) {
        form.manufacturer = fields.manufacturer;
        filled.add('manufacturer');
    }
    if (fields.model_number) {
        form.model_number = fields.model_number;
        filled.add('model_number');
    }
    if (fields.serial_number) {
        form.serial_number = fields.serial_number;
        filled.add('serial_number');
    }

    aiFilled.value = filled;
}

// Once the user edits a suggested field, it's been reviewed — drop the flag.
function clearAiFlag(key: string) {
    aiFilled.value.delete(key);
}

const eligibleParents = computed(() => props.items.filter((i) => !props.item || i.id !== props.item.id));

// Rooms are places, not possessions — hide the purchase/warranty/sale fields.
// Reactive to the type selector, and the server blanks them too on save.
const showDetails = computed(() => props.types.find((t) => t.value === form.type)?.details ?? true);

function toggleTag(id: number) {
    if (form.tags.includes(id)) {
        form.tags = form.tags.filter((t) => t !== id);
    } else {
        form.tags = [...form.tags, id];
    }
}

function submit() {
    if (props.mode === 'create') {
        // No forceFormData: Inertia auto-detects a File in form.images and switches
        // to multipart only when needed. Forcing it serialised an empty multipart
        // body (dropping name/type) when no image was queued.
        form.post('/items');
    } else if (props.item) {
        form.put(`/items/${props.item.id}`);
    }
}
</script>

<template>
    <form class="form" @submit.prevent="submit">
        <div class="form-row">
            <label>Type</label>
            <div class="grid grid-cols-3 gap-2">
                <button
                    v-for="type in types"
                    :key="type.value"
                    type="button"
                    class="flex flex-col items-center gap-1.5 p-3 text-[13px] transition rounded-md border"
                    :style="{
                        borderColor: form.type === type.value ? 'var(--fg)' : 'var(--border)',
                        background: form.type === type.value ? 'var(--bg-sunken)' : 'var(--bg-elev)',
                        color: form.type === type.value ? 'var(--fg)' : 'var(--fg-muted)',
                    }"
                    @click="form.type = type.value"
                >
                    <ItemTypeIcon :type="type.value" class="size-5" />
                    {{ type.label }}
                </button>
            </div>
            <InputError :message="form.errors.type" />
        </div>

        <div class="form-row">
            <label for="name">Name <AiFieldBadge :state="fieldStates.name" /></label>
            <input
                id="name"
                v-model="form.name"
                autofocus
                required
                placeholder="e.g. Toolbox"
                :class="['field', fieldStates.name ? `ai-${fieldStates.name}` : '']"
                @input="clearAiFlag('name')"
            />
            <InputError :message="form.errors.name" />
        </div>

        <div class="form-row">
            <label for="description">Description <AiFieldBadge :state="fieldStates.description" /></label>
            <textarea
                id="description"
                v-model="form.description"
                rows="3"
                placeholder="Optional notes"
                :class="['field', fieldStates.description ? `ai-${fieldStates.description}` : '']"
                @input="clearAiFlag('description')"
            />
            <InputError :message="form.errors.description" />
        </div>

        <div v-if="showDetails" class="form-row" style="max-width: 160px">
            <label for="quantity">Quantity</label>
            <input id="quantity" v-model.number="form.quantity" type="number" min="0" step="1" class="field" />
            <InputError :message="form.errors.quantity" />
        </div>

        <div v-if="mode === 'create'" class="form-row">
            <label for="parent_id">Inside</label>
            <select id="parent_id" v-model="form.parent_id" class="field">
                <option :value="null">— Top level —</option>
                <option v-for="candidate in eligibleParents" :key="candidate.id" :value="candidate.id">
                    {{ candidate.type.label }} · {{ candidate.name }}
                </option>
            </select>
            <InputError :message="form.errors.parent_id" />
        </div>

        <ItemImageManager
            :mode="mode"
            :item-id="item?.id ?? null"
            :existing="item?.images ?? []"
            :files="queuedFiles"
            @update:files="onFilesUpdate"
        />
        <InputError :message="form.errors['images.0']" />

        <div v-if="mode === 'create' && aiEnabled && queuedFiles.length > 0" class="ai-fill">
            <button type="button" class="btn-pill" :disabled="analyzing" data-test="ai-fill" @click="analyzeFromPhoto">
                <Loader2 v-if="analyzing" :size="14" class="ai-spin" />
                <Sparkles v-else :size="14" />
                {{ analyzing ? 'Reading photo…' : 'Fill details from photo' }}
            </button>
            <span v-if="!analyzeError" class="ai-fill-hint">{{ analyzeHint }}</span>
            <p v-if="analyzeError" class="ai-fill-error">{{ analyzeError }}</p>
        </div>

        <div class="form-row">
            <label>Tags</label>
            <div v-if="tags.length === 0" style="color: var(--fg-muted); font-size: 13px">
                No tags yet. Create one from the Tags page.
            </div>
            <div v-else class="flex flex-wrap gap-2">
                <button
                    v-for="tag in tags"
                    :key="tag.id"
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-[11.5px] transition"
                    :style="
                        form.tags.includes(tag.id)
                            ? { background: 'var(--accent)', color: 'var(--accent-fg)', border: '1px solid var(--accent)' }
                            : { background: 'var(--bg-elev)', color: 'var(--fg-muted)', border: '1px solid var(--border)' }
                    "
                    @click="toggleTag(tag.id)"
                >
                    <Check v-if="form.tags.includes(tag.id)" :size="12" />
                    <span v-if="tag.color" class="size-2 rounded-full" :style="{ backgroundColor: tag.color }" />
                    {{ tag.name }}
                </button>
            </div>
            <InputError :message="form.errors.tags" />
        </div>

        <template v-if="showDetails">
        <hr style="border: 0; border-top: 1px solid var(--border); margin: 2px 0" />
        <p class="section-label">Purchase &amp; identification</p>

        <div class="form-grid">
            <div class="form-row">
                <label for="manufacturer">Manufacturer <AiFieldBadge :state="fieldStates.manufacturer" /></label>
                <input
                    id="manufacturer"
                    v-model="form.manufacturer"
                    placeholder="e.g. DeWalt"
                    :class="['field', fieldStates.manufacturer ? `ai-${fieldStates.manufacturer}` : '']"
                    @input="clearAiFlag('manufacturer')"
                />
                <InputError :message="form.errors.manufacturer" />
            </div>
            <div class="form-row">
                <label for="model_number">Model number <AiFieldBadge :state="fieldStates.model_number" /></label>
                <input
                    id="model_number"
                    v-model="form.model_number"
                    :class="['field', fieldStates.model_number ? `ai-${fieldStates.model_number}` : '']"
                    @input="clearAiFlag('model_number')"
                />
                <InputError :message="form.errors.model_number" />
            </div>
            <div class="form-row">
                <label for="serial_number">Serial number <AiFieldBadge :state="fieldStates.serial_number" /></label>
                <input
                    id="serial_number"
                    v-model="form.serial_number"
                    :class="['field', fieldStates.serial_number ? `ai-${fieldStates.serial_number}` : '']"
                    @input="clearAiFlag('serial_number')"
                />
                <InputError :message="form.errors.serial_number" />
            </div>
            <div class="form-row">
                <label for="purchased_from">Purchased from</label>
                <input id="purchased_from" v-model="form.purchased_from" class="field" placeholder="Vendor / store" />
                <InputError :message="form.errors.purchased_from" />
            </div>
            <div class="form-row">
                <label for="purchase_date">Purchase date</label>
                <input id="purchase_date" v-model="form.purchase_date" type="date" class="field" />
                <InputError :message="form.errors.purchase_date" />
            </div>
            <div class="form-row">
                <label for="purchase_price">Purchase price ({{ currency.code }})</label>
                <input id="purchase_price" v-model="form.purchase_price" type="number" min="0" step="0.01" class="field" placeholder="0.00" />
                <InputError :message="form.errors.purchase_price" />
            </div>
        </div>

        <template v-if="customFields.length">
            <hr style="border: 0; border-top: 1px solid var(--border); margin: 2px 0" />
            <p class="section-label">Custom fields</p>
            <CustomFieldsInput v-model="form.custom_fields" :fields="customFields" :errors="form.errors" />
        </template>

        <hr style="border: 0; border-top: 1px solid var(--border); margin: 2px 0" />
        <p class="section-label">Warranty</p>

        <label class="flex items-center gap-2" style="font-size: 13px; cursor: pointer">
            <input v-model="form.lifetime_warranty" type="checkbox" />
            Lifetime warranty
        </label>
        <div class="form-grid">
            <div class="form-row">
                <label for="warranty_expires">Warranty expires</label>
                <input id="warranty_expires" v-model="form.warranty_expires" type="date" class="field" :disabled="form.lifetime_warranty" />
                <InputError :message="form.errors.warranty_expires" />
            </div>
        </div>
        <div class="form-row">
            <label for="warranty_details">Warranty details</label>
            <textarea id="warranty_details" v-model="form.warranty_details" rows="2" class="field" placeholder="Coverage notes, claim contact, etc." />
            <InputError :message="form.errors.warranty_details" />
        </div>

        <hr style="border: 0; border-top: 1px solid var(--border); margin: 2px 0" />
        <p class="section-label">Sold</p>

        <div class="form-grid">
            <div class="form-row">
                <label for="sold_to">Sold to</label>
                <input id="sold_to" v-model="form.sold_to" class="field" placeholder="Buyer" />
                <InputError :message="form.errors.sold_to" />
            </div>
            <div class="form-row">
                <label for="sold_price">Sold price ({{ currency.code }})</label>
                <input id="sold_price" v-model="form.sold_price" type="number" min="0" step="0.01" class="field" placeholder="0.00" />
                <InputError :message="form.errors.sold_price" />
            </div>
            <div class="form-row">
                <label for="sold_date">Sold date</label>
                <input id="sold_date" v-model="form.sold_date" type="date" class="field" />
                <InputError :message="form.errors.sold_date" />
            </div>
        </div>
        <div class="form-row">
            <label for="sold_notes">Sold notes</label>
            <textarea id="sold_notes" v-model="form.sold_notes" rows="2" class="field" />
            <InputError :message="form.errors.sold_notes" />
        </div>
        </template>

        <div class="flex justify-end gap-2">
            <button type="submit" :disabled="form.processing" class="btn-primary">
                <Check :size="14" />
                {{ submitLabel ?? (mode === 'create' ? 'Create' : 'Save') }}
            </button>
        </div>
    </form>
</template>

<style scoped>
.ai-fill {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
    margin-top: -4px;
}
.ai-fill-hint {
    font-size: 12px;
    color: var(--fg-subtle);
}
.ai-fill-error {
    flex-basis: 100%;
    margin: 0;
    font-size: 12px;
    color: #dc2626;
}
.dark .ai-fill-error {
    color: #f87171;
}
.ai-spin {
    animation: ai-spin 0.8s linear infinite;
}
@keyframes ai-spin {
    to {
        transform: rotate(360deg);
    }
}

/* Field cues that pair with <AiFieldBadge>: dashed while a value is incoming,
   solid accent once the model has suggested one. */
.field.ai-pending {
    border-style: dashed;
    border-color: color-mix(in srgb, var(--accent) 55%, var(--border));
}
.field.ai-suggested {
    border-color: var(--accent);
    background: color-mix(in srgb, var(--accent) 6%, transparent);
}
</style>
