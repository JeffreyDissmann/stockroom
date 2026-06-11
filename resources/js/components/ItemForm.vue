<script setup lang="ts">
import AiFieldBadge from '@/components/AiFieldBadge.vue';
import CustomFieldsInput from '@/components/CustomFieldsInput.vue';
import DocumentFieldProposal from '@/components/DocumentFieldProposal.vue';
import IconPicker from '@/components/IconPicker.vue';
import InputError from '@/components/InputError.vue';
import ItemImageManager from '@/components/ItemImageManager.vue';
import ItemThumbnail from '@/components/ItemThumbnail.vue';
import ItemTypeIcon from '@/components/ItemTypeIcon.vue';
import LinkPaperlessDocumentDialog from '@/components/LinkPaperlessDocumentDialog.vue';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { trans, transChoice } from '@/composables/useTranslations';
import itemRoutes from '@/routes/items';
import homeAssistantLinkRoutes from '@/routes/items/home-assistant-link';
import paperlessLinksRoutes from '@/routes/items/paperless-links';
import type { CustomFieldDefinition, ItemSummary, ItemTypeDescriptor, ItemTypeValue, SharedData, TagSummary } from '@/types';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { Check, FileText, House, Loader2, Lock, Sparkles, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const currency = usePage<SharedData>().props.currency;

type Mode = 'create' | 'edit';

interface PaperlessLinkSummary {
    document_id: number;
    url: string;
    // Cached snapshot from Paperless — null until the repair job has seen
    // the link; the chip falls back to the bare #id.
    title: string | null;
    type: string | null;
}

interface HomeAssistantLinkSummary {
    entity_id: string | null;
    device_id: string | null;
    friendly_name: string | null;
    url: string | null;
}

const props = defineProps<{
    mode: Mode;
    item?: ItemSummary | null;
    parent?: ItemSummary | null;
    items: ItemSummary[];
    tags: TagSummary[];
    types: ItemTypeDescriptor[];
    customFields: CustomFieldDefinition[];
    // Curated battery-cell suggestions for the type picker (free string).
    batteryTypes?: string[];
    // Tag ids the user may not detach (e.g. the auto-managed Battery tag).
    lockedTagIds?: number[];
    submitLabel?: string;
    // Paperless-ngx documents linked to this item (#7). Edit-page only —
    // Show.vue renders the same chips read-only. Empty array on create.
    paperlessLinks?: PaperlessLinkSummary[];
    // The Home Assistant entity linked to this item (1:1), or null. Edit-page
    // only — Show.vue renders it read-only. Unlinking removes the backlink.
    homeAssistantLink?: HomeAssistantLinkSummary | null;
}>();

const form = useForm({
    name: props.item?.name ?? '',
    description: props.item?.description ?? '',
    icon: props.item?.icon ?? '',
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
    battery_type: props.item?.battery_type ?? '',
    lifetime_warranty: props.item?.lifetime_warranty ?? false,
    warranty_expires: props.item?.warranty_expires ?? '',
    warranty_details: props.item?.warranty_details ?? '',
    sold_to: props.item?.sold_to ?? '',
    sold_price: props.item?.sold_price ?? '',
    sold_date: props.item?.sold_date ?? '',
    sold_notes: props.item?.sold_notes ?? '',
    custom_fields: Object.fromEntries((props.item?.custom_fields ?? []).map((f) => [f.custom_field_id, f.value])) as Record<
        number,
        string | number | boolean | null
    >,
});

const isPlace = computed(() => form.type === 'room' || form.type === 'container');

// Battery type: a curated Select with a "Custom…" escape that reveals a text
// input, so the free-string column still accepts an unusual cell. Sentinels
// keep radix's value model non-empty.
const BATTERY_NONE = '__none__';
const BATTERY_CUSTOM = '__custom__';
const isPresetBattery = (value: string): boolean => (props.batteryTypes ?? []).includes(value);
const batteryCustom = ref(form.battery_type !== '' && !isPresetBattery(form.battery_type));

const batterySelectValue = computed<string>({
    get: () => {
        if (batteryCustom.value) return BATTERY_CUSTOM;
        return form.battery_type === '' ? BATTERY_NONE : form.battery_type;
    },
    set: (value) => {
        if (value === BATTERY_CUSTOM) {
            batteryCustom.value = true;
            form.battery_type = '';
        } else {
            batteryCustom.value = false;
            form.battery_type = value === BATTERY_NONE ? '' : value;
        }
    },
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
const paperlessEnabled = usePage<SharedData>().props.features.paperless;

// The combined "Connections" section shows when the item has a Paperless doc
// and/or a Home Assistant link — one, both, or none.
const hasConnections = computed(() => (paperlessEnabled && (props.paperlessLinks?.length ?? 0) > 0) || props.homeAssistantLink != null);
const analyzing = ref(false);
const analyzeError = ref<string | null>(null);

// Field keys the most recent analysis populated, so the UI can flag them for review.
const aiFilled = ref<Set<string>>(new Set());

// Fields the photo analysis can populate. While analysing they show a "pending"
// cue so the user sees values are still coming; once filled, a "suggested" cue.
const aiCandidates = ['name', 'description', 'manufacturer', 'model_number', 'serial_number'];

// Fields a linked Paperless document can propose (suggest-fields endpoint).
// Superset of aiCandidates plus the purchase block.
const documentFields = [
    'name',
    'description',
    'manufacturer',
    'model_number',
    'serial_number',
    'purchased_from',
    'purchase_price',
    'purchase_date',
    'quantity',
] as const;

type DocumentField = (typeof documentFields)[number];

type AiFieldState = 'pending' | 'suggested' | null;

const fieldStates = computed<Record<string, AiFieldState>>(() => {
    const states: Record<string, AiFieldState> = {};
    for (const key of new Set([...aiCandidates, ...documentFields])) {
        // "pending" is photo-analysis only — document suggestions arrive in
        // one shot, so their fields go straight to "suggested".
        states[key] = analyzing.value && aiCandidates.includes(key) ? 'pending' : aiFilled.value.has(key) ? 'suggested' : null;
    }
    return states;
});

const analyzeHint = computed(() => {
    if (analyzing.value) {
        return trans('items.form.ai_hint_analyzing');
    }
    if (aiFilled.value.size > 0) {
        return transChoice('items.form.ai_hint_filled', aiFilled.value.size);
    }
    return trans('items.form.ai_hint_idle');
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

        const response = await fetch(itemRoutes.analyzePhoto().url, {
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
                ? trans('items.form.ai_error_timeout')
                : trans('items.form.ai_error_generic');
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

const isTagLocked = (id: number): boolean => (props.lockedTagIds ?? []).includes(id);

function toggleTag(id: number) {
    if (isTagLocked(id)) return; // system-assigned, can't be removed here
    if (form.tags.includes(id)) {
        form.tags = form.tags.filter((t) => t !== id);
    } else {
        form.tags = [...form.tags, id];
    }
}

// Drop a Paperless-link pivot row + scrub the item id from the doc's
// stockroom_item_ids custom field. Edit-page only; Show is read-only.
// preserveScroll keeps the user in place when the page refreshes the
// paperlessLinks prop.
function unlinkPaperless(documentId: number) {
    if (!props.item) return;
    if (!confirm(trans('items.paperless.unlink_confirm'))) return;

    router.delete(paperlessLinksRoutes.destroy([props.item.id, documentId]).url, {
        preserveScroll: true,
    });
}

// ── Suggest field values from a linked Paperless document ──────────────────
// Re-reads the doc's OCR text server-side and proposes catalogue fields.
// Empty fields fill directly (with the "suggested" badge, like photo
// analysis); a field that already holds a DIFFERENT value is never
// overwritten — the proposal renders as an explicit per-field
// "Document says: X — apply?" chip instead.
const suggestingDocument = ref<number | null>(null);
const suggestError = ref<string | null>(null);
const documentProposals = ref<Partial<Record<DocumentField, string | number>>>({});

async function suggestFromDocument(documentId: number) {
    if (!props.item || suggestingDocument.value !== null) return;

    suggestingDocument.value = documentId;
    suggestError.value = null;

    const controller = new AbortController();
    const timeout = window.setTimeout(() => controller.abort(), ANALYZE_TIMEOUT_MS);

    try {
        const response = await fetch(paperlessLinksRoutes.suggestFields([props.item.id, documentId]).url, {
            method: 'POST',
            headers: { Accept: 'application/json', 'X-XSRF-TOKEN': readXsrfToken() },
            credentials: 'same-origin',
            signal: controller.signal,
        });

        if (!response.ok) {
            throw new Error(String(response.status));
        }

        const { fields } = (await response.json()) as { fields: Record<string, string | number | null> };
        applyDocumentProposals(fields);
    } catch (error) {
        suggestError.value =
            error instanceof DOMException && error.name === 'AbortError'
                ? trans('items.form.ai_error_timeout')
                : trans('items.paperless.suggest_error');
    } finally {
        window.clearTimeout(timeout);
        suggestingDocument.value = null;
    }
}

function applyDocumentProposals(fields: Record<string, string | number | null>) {
    const proposals: Partial<Record<DocumentField, string | number>> = {};

    for (const key of documentFields) {
        const proposed = fields[key];
        if (proposed == null) continue;

        const current = form[key];
        if (current === '' || current == null) {
            (form[key] as string | number) = proposed;
            aiFilled.value.add(key);
        } else if (proposalDiffers(current, proposed)) {
            proposals[key] = proposed;
        }
    }

    documentProposals.value = proposals;
}

/**
 * Numeric-aware comparison so "849.00" (form string) vs 849 (proposal)
 * doesn't produce a pointless override chip.
 */
function proposalDiffers(current: string | number | boolean | null, proposed: string | number): boolean {
    if (typeof current !== 'string' && typeof current !== 'number') return true;
    if (current !== '' && !isNaN(Number(current)) && !isNaN(Number(proposed))) {
        return Number(current) !== Number(proposed);
    }
    return String(current).trim() !== String(proposed).trim();
}

function applyProposal(key: DocumentField) {
    const proposed = documentProposals.value[key];
    if (proposed === undefined) return;

    (form[key] as string | number) = proposed;
    aiFilled.value.add(key);
    dismissProposal(key);
}

function dismissProposal(key: DocumentField) {
    const next = { ...documentProposals.value };
    delete next[key];
    documentProposals.value = next;
}

// Remove the Home Assistant backlink. Edit-page only; Show is read-only. The
// HA integration re-links on its next sync if the device still points here.
function unlinkHomeAssistant() {
    if (!props.item) return;
    if (!confirm(trans('items.home_assistant.unlink_confirm'))) return;

    router.delete(homeAssistantLinkRoutes.destroy(props.item.id).url, {
        preserveScroll: true,
    });
}

function submit() {
    if (props.mode === 'create') {
        // No forceFormData: Inertia auto-detects a File in form.images and switches
        // to multipart only when needed. Forcing it serialised an empty multipart
        // body (dropping name/type) when no image was queued.
        form.post(itemRoutes.store().url);
    } else if (props.item) {
        form.put(itemRoutes.update(props.item.id).url);
    }
}
</script>

<template>
    <form class="form" @submit.prevent="submit">
        <div class="form-row">
            <label>{{ $t('items.form.type') }}</label>
            <div class="grid grid-cols-3 gap-2">
                <button
                    v-for="type in types"
                    :key="type.value"
                    type="button"
                    class="flex flex-col items-center gap-1.5 rounded-md border p-3 text-[13px] transition"
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
            <label for="name">{{ $t('common.name') }} <AiFieldBadge :state="fieldStates.name" /></label>
            <input
                id="name"
                v-model="form.name"
                autofocus
                required
                :placeholder="$t('items.form.name_placeholder')"
                :class="['field', fieldStates.name ? `ai-${fieldStates.name}` : '']"
                @input="clearAiFlag('name')"
            />
            <InputError :message="form.errors.name" />
            <DocumentFieldProposal field="name" :value="documentProposals.name" @apply="applyProposal('name')" @dismiss="dismissProposal('name')" />
        </div>

        <div class="form-row">
            <label for="description">{{ $t('common.description') }} <AiFieldBadge :state="fieldStates.description" /></label>
            <textarea
                id="description"
                v-model="form.description"
                rows="3"
                :placeholder="$t('items.form.description_placeholder')"
                :class="['field', fieldStates.description ? `ai-${fieldStates.description}` : '']"
                @input="clearAiFlag('description')"
            />
            <InputError :message="form.errors.description" />
            <DocumentFieldProposal
                field="description"
                :value="documentProposals.description"
                @apply="applyProposal('description')"
                @dismiss="dismissProposal('description')"
            />
        </div>

        <div v-if="isPlace" class="form-row">
            <label for="icon">{{ $t('items.form.icon') }}</label>
            <p class="appearance-hint">{{ $t('items.form.icon_hint') }}</p>
            <div class="appearance">
                <span class="appearance-preview">
                    <ItemThumbnail
                        :item="{
                            name: form.name || 'Item',
                            type: { value: form.type, label: '', icon: '' },
                            thumb_url: null,
                            icon: form.icon || null,
                        }"
                        size="md"
                    />
                </span>
                <IconPicker v-model="form.icon" />
            </div>
            <InputError :message="form.errors.icon" />
        </div>

        <div v-if="showDetails" class="form-row" style="max-width: 160px">
            <label for="quantity">{{ $t('items.form.quantity') }} <AiFieldBadge :state="fieldStates.quantity" /></label>
            <input
                id="quantity"
                v-model.number="form.quantity"
                type="number"
                min="0"
                step="1"
                :class="['field', fieldStates.quantity ? `ai-${fieldStates.quantity}` : '']"
                @input="clearAiFlag('quantity')"
            />
            <InputError :message="form.errors.quantity" />
            <DocumentFieldProposal
                field="quantity"
                :value="documentProposals.quantity"
                @apply="applyProposal('quantity')"
                @dismiss="dismissProposal('quantity')"
            />
        </div>

        <div v-if="mode === 'create'" class="form-row">
            <label for="parent_id">{{ $t('items.form.inside') }}</label>
            <select id="parent_id" v-model="form.parent_id" class="field">
                <option :value="null">{{ $t('items.form.top_level') }}</option>
                <option v-for="candidate in eligibleParents" :key="candidate.id" :value="candidate.id">
                    {{ candidate.type.label }} · {{ candidate.name }}
                </option>
            </select>
            <InputError :message="form.errors.parent_id" />
        </div>

        <!-- The image panel hosts the single "Find image" trigger (the
             "or Find image" row under the drop zone) — it used to be
             duplicated by a second standalone button right below. -->
        <ItemImageManager
            :mode="mode"
            :item-id="item?.id ?? null"
            :item-name="item?.name ?? null"
            :existing="item?.images ?? []"
            :files="queuedFiles"
            @update:files="onFilesUpdate"
        />
        <InputError :message="form.errors['images.0']" />

        <div v-if="mode === 'create' && aiEnabled && queuedFiles.length > 0" class="ai-fill">
            <button type="button" class="btn-pill" :disabled="analyzing" data-test="ai-fill" @click="analyzeFromPhoto">
                <Loader2 v-if="analyzing" :size="14" class="ai-spin" />
                <Sparkles v-else :size="14" />
                {{ analyzing ? $t('items.form.ai_reading') : $t('items.form.ai_fill') }}
            </button>
            <span v-if="!analyzeError" class="ai-fill-hint">{{ analyzeHint }}</span>
            <p v-if="analyzeError" class="ai-fill-error">{{ analyzeError }}</p>
        </div>

        <div class="form-row">
            <label>{{ $t('items.form.tags') }}</label>
            <div v-if="tags.length === 0" style="color: var(--fg-muted); font-size: 13px">
                {{ $t('items.form.no_tags') }}
            </div>
            <div v-else class="flex flex-wrap gap-2">
                <button
                    v-for="tag in tags"
                    :key="tag.id"
                    type="button"
                    :disabled="isTagLocked(tag.id)"
                    :title="isTagLocked(tag.id) ? $t('items.form.tag_locked') : undefined"
                    class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-[11.5px] transition"
                    :class="{ 'cursor-not-allowed': isTagLocked(tag.id) }"
                    :style="
                        form.tags.includes(tag.id)
                            ? { background: 'var(--accent)', color: 'var(--accent-fg)', border: '1px solid var(--accent)' }
                            : { background: 'var(--bg-elev)', color: 'var(--fg-muted)', border: '1px solid var(--border)' }
                    "
                    @click="toggleTag(tag.id)"
                >
                    <Lock v-if="isTagLocked(tag.id)" :size="11" />
                    <Check v-else-if="form.tags.includes(tag.id)" :size="12" />
                    <span v-if="tag.color" class="size-2 rounded-full" :style="{ backgroundColor: tag.color }" />
                    {{ tag.name }}
                </button>
            </div>
            <InputError :message="form.errors.tags" />
        </div>

        <template v-if="showDetails">
            <hr style="border: 0; border-top: 1px solid var(--border); margin: 2px 0" />
            <p class="section-label">{{ $t('items.form.section_purchase') }}</p>

            <div class="form-grid">
                <div class="form-row">
                    <label for="manufacturer">{{ $t('items.form.manufacturer') }} <AiFieldBadge :state="fieldStates.manufacturer" /></label>
                    <input
                        id="manufacturer"
                        v-model="form.manufacturer"
                        :placeholder="$t('items.form.manufacturer_placeholder')"
                        :class="['field', fieldStates.manufacturer ? `ai-${fieldStates.manufacturer}` : '']"
                        @input="clearAiFlag('manufacturer')"
                    />
                    <InputError :message="form.errors.manufacturer" />
                    <DocumentFieldProposal
                        field="manufacturer"
                        :value="documentProposals.manufacturer"
                        @apply="applyProposal('manufacturer')"
                        @dismiss="dismissProposal('manufacturer')"
                    />
                </div>
                <div class="form-row">
                    <label for="model_number">{{ $t('items.form.model_number') }} <AiFieldBadge :state="fieldStates.model_number" /></label>
                    <input
                        id="model_number"
                        v-model="form.model_number"
                        :class="['field', fieldStates.model_number ? `ai-${fieldStates.model_number}` : '']"
                        @input="clearAiFlag('model_number')"
                    />
                    <InputError :message="form.errors.model_number" />
                    <DocumentFieldProposal
                        field="model_number"
                        :value="documentProposals.model_number"
                        @apply="applyProposal('model_number')"
                        @dismiss="dismissProposal('model_number')"
                    />
                </div>
                <div class="form-row">
                    <label for="serial_number">{{ $t('items.form.serial_number') }} <AiFieldBadge :state="fieldStates.serial_number" /></label>
                    <input
                        id="serial_number"
                        v-model="form.serial_number"
                        :class="['field', fieldStates.serial_number ? `ai-${fieldStates.serial_number}` : '']"
                        @input="clearAiFlag('serial_number')"
                    />
                    <InputError :message="form.errors.serial_number" />
                    <DocumentFieldProposal
                        field="serial_number"
                        :value="documentProposals.serial_number"
                        @apply="applyProposal('serial_number')"
                        @dismiss="dismissProposal('serial_number')"
                    />
                </div>
                <div class="form-row">
                    <label for="battery_type">{{ $t('items.form.battery_type') }}</label>
                    <Select v-model="batterySelectValue">
                        <SelectTrigger id="battery_type" data-test="item-battery-type">
                            <SelectValue :placeholder="$t('items.form.battery_type_none')" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="BATTERY_NONE">{{ $t('items.form.battery_type_none') }}</SelectItem>
                            <SelectItem v-for="bt in batteryTypes ?? []" :key="bt" :value="bt">{{ bt }}</SelectItem>
                            <SelectItem :value="BATTERY_CUSTOM">{{ $t('items.form.battery_type_custom') }}</SelectItem>
                        </SelectContent>
                    </Select>
                    <input
                        v-if="batteryCustom"
                        v-model="form.battery_type"
                        class="field mt-2"
                        :placeholder="$t('items.form.battery_type_placeholder')"
                        data-test="item-battery-type-custom"
                    />
                    <InputError :message="form.errors.battery_type" />
                </div>
                <div class="form-row">
                    <label for="purchased_from">{{ $t('items.form.purchased_from') }} <AiFieldBadge :state="fieldStates.purchased_from" /></label>
                    <input
                        id="purchased_from"
                        v-model="form.purchased_from"
                        :placeholder="$t('items.form.purchased_from_placeholder')"
                        :class="['field', fieldStates.purchased_from ? `ai-${fieldStates.purchased_from}` : '']"
                        @input="clearAiFlag('purchased_from')"
                    />
                    <InputError :message="form.errors.purchased_from" />
                    <DocumentFieldProposal
                        field="purchased_from"
                        :value="documentProposals.purchased_from"
                        @apply="applyProposal('purchased_from')"
                        @dismiss="dismissProposal('purchased_from')"
                    />
                </div>
                <div class="form-row">
                    <label for="purchase_date">{{ $t('items.form.purchase_date') }} <AiFieldBadge :state="fieldStates.purchase_date" /></label>
                    <input
                        id="purchase_date"
                        v-model="form.purchase_date"
                        type="date"
                        :class="['field', fieldStates.purchase_date ? `ai-${fieldStates.purchase_date}` : '']"
                        @input="clearAiFlag('purchase_date')"
                    />
                    <InputError :message="form.errors.purchase_date" />
                    <DocumentFieldProposal
                        field="purchase_date"
                        :value="documentProposals.purchase_date"
                        @apply="applyProposal('purchase_date')"
                        @dismiss="dismissProposal('purchase_date')"
                    />
                </div>
                <div class="form-row">
                    <label for="purchase_price">
                        {{ $t('items.form.purchase_price', { code: currency.code }) }} <AiFieldBadge :state="fieldStates.purchase_price" />
                    </label>
                    <input
                        id="purchase_price"
                        v-model="form.purchase_price"
                        type="number"
                        min="0"
                        step="0.01"
                        :class="['field', fieldStates.purchase_price ? `ai-${fieldStates.purchase_price}` : '']"
                        :placeholder="$t('items.form.price_placeholder')"
                        @input="clearAiFlag('purchase_price')"
                    />
                    <InputError :message="form.errors.purchase_price" />
                    <DocumentFieldProposal
                        field="purchase_price"
                        :value="documentProposals.purchase_price"
                        @apply="applyProposal('purchase_price')"
                        @dismiss="dismissProposal('purchase_price')"
                    />
                </div>
            </div>

            <!-- "Connections" section: external links this item has — a Home
             Assistant device and/or Paperless documents — in one section,
             matching the read-only Connections card on Show.vue. Edit-only,
             so a destructive unlink requires entering Edit first. Sits above
             Custom fields. An item may have one, both, or none — with
             Paperless enabled the section always shows, hosting the
             "link a document" input. -->
            <template v-if="mode === 'edit' && (hasConnections || paperlessEnabled)">
                <hr style="border: 0; border-top: 1px solid var(--border); margin: 2px 0" />
                <p class="section-label">{{ $t('items.links.section_title') }}</p>
                <ul v-if="hasConnections" class="paperless-list" data-test="connections-edit-list">
                    <li v-if="homeAssistantLink" class="paperless-row" data-test="ha-edit-row">
                        <a v-if="homeAssistantLink.url" :href="homeAssistantLink.url" target="_blank" rel="noopener" class="paperless-link">
                            <House :size="14" :style="{ color: 'var(--fg-muted)', flexShrink: 0 }" />
                            <span class="paperless-id">{{
                                homeAssistantLink.friendly_name || homeAssistantLink.entity_id || homeAssistantLink.device_id
                            }}</span>
                            <span class="paperless-host truncate">{{ $t('items.home_assistant.open_in_home_assistant') }}</span>
                        </a>
                        <span v-else class="paperless-link">
                            <House :size="14" :style="{ color: 'var(--fg-muted)', flexShrink: 0 }" />
                            <span class="paperless-id">{{
                                homeAssistantLink.friendly_name || homeAssistantLink.entity_id || homeAssistantLink.device_id
                            }}</span>
                        </span>
                        <button
                            type="button"
                            class="btn-ghost"
                            style="padding: 4px 8px"
                            data-test="ha-unlink"
                            :aria-label="$t('items.home_assistant.unlink')"
                            @click="unlinkHomeAssistant"
                        >
                            <X :size="14" />
                        </button>
                    </li>
                    <li v-for="link in paperlessLinks" :key="link.document_id" class="paperless-row">
                        <!-- The suggest action sits right next to the link text, away
                             from the destructive unlink ✕ at the far edge of the row. -->
                        <a
                            :href="link.url"
                            target="_blank"
                            rel="noopener"
                            class="paperless-link"
                            style="flex: 0 1 auto; min-width: 0"
                            :title="`#${link.document_id}`"
                        >
                            <FileText :size="14" :style="{ color: 'var(--fg-muted)', flexShrink: 0 }" />
                            <span v-if="link.type" class="paperless-type">{{ link.type }}</span>
                            <span v-if="link.title" class="paperless-id truncate">{{ link.title }}</span>
                            <span v-else class="paperless-id">#{{ link.document_id }}</span>
                            <span class="paperless-host truncate">{{ $t('items.paperless.open_in_paperless') }}</span>
                        </a>
                        <button
                            v-if="aiEnabled"
                            type="button"
                            class="btn-ghost"
                            style="padding: 4px 8px"
                            :data-test="`paperless-suggest-${link.document_id}`"
                            :disabled="suggestingDocument !== null"
                            :aria-label="$t('items.paperless.suggest')"
                            :title="$t('items.paperless.suggest')"
                            @click="suggestFromDocument(link.document_id)"
                        >
                            <Loader2 v-if="suggestingDocument === link.document_id" :size="14" class="ai-spin" />
                            <Sparkles v-else :size="14" />
                        </button>
                        <button
                            type="button"
                            class="btn-ghost"
                            style="padding: 4px 8px; margin-left: auto"
                            :data-test="`paperless-unlink-${link.document_id}`"
                            :aria-label="$t('items.paperless.unlink')"
                            @click="unlinkPaperless(link.document_id)"
                        >
                            <X :size="14" />
                        </button>
                    </li>
                </ul>
                <InputError :message="suggestError" />
                <div v-if="paperlessEnabled && item">
                    <LinkPaperlessDocumentDialog :item="item" />
                </div>
            </template>

            <template v-if="customFields.length">
                <hr style="border: 0; border-top: 1px solid var(--border); margin: 2px 0" />
                <p class="section-label">{{ $t('items.form.section_custom') }}</p>
                <CustomFieldsInput v-model="form.custom_fields" :fields="customFields" :errors="form.errors" />
            </template>

            <hr style="border: 0; border-top: 1px solid var(--border); margin: 2px 0" />
            <p class="section-label">{{ $t('items.form.section_warranty') }}</p>

            <label class="flex items-center gap-2" style="font-size: 13px; cursor: pointer">
                <input v-model="form.lifetime_warranty" type="checkbox" />
                {{ $t('items.form.lifetime_warranty') }}
            </label>
            <div class="form-grid">
                <div class="form-row">
                    <label for="warranty_expires">{{ $t('items.form.warranty_expires') }}</label>
                    <input id="warranty_expires" v-model="form.warranty_expires" type="date" class="field" :disabled="form.lifetime_warranty" />
                    <InputError :message="form.errors.warranty_expires" />
                </div>
            </div>
            <div class="form-row">
                <label for="warranty_details">{{ $t('items.form.warranty_details') }}</label>
                <textarea
                    id="warranty_details"
                    v-model="form.warranty_details"
                    rows="2"
                    class="field"
                    :placeholder="$t('items.form.warranty_details_placeholder')"
                />
                <InputError :message="form.errors.warranty_details" />
            </div>

            <hr style="border: 0; border-top: 1px solid var(--border); margin: 2px 0" />
            <p class="section-label">{{ $t('items.form.section_sold') }}</p>

            <div class="form-grid">
                <div class="form-row">
                    <label for="sold_to">{{ $t('items.form.sold_to') }}</label>
                    <input id="sold_to" v-model="form.sold_to" class="field" :placeholder="$t('items.form.sold_to_placeholder')" />
                    <InputError :message="form.errors.sold_to" />
                </div>
                <div class="form-row">
                    <label for="sold_price">{{ $t('items.form.sold_price', { code: currency.code }) }}</label>
                    <input
                        id="sold_price"
                        v-model="form.sold_price"
                        type="number"
                        min="0"
                        step="0.01"
                        class="field"
                        :placeholder="$t('items.form.price_placeholder')"
                    />
                    <InputError :message="form.errors.sold_price" />
                </div>
                <div class="form-row">
                    <label for="sold_date">{{ $t('items.form.sold_date') }}</label>
                    <input id="sold_date" v-model="form.sold_date" type="date" class="field" />
                    <InputError :message="form.errors.sold_date" />
                </div>
            </div>
            <div class="form-row">
                <label for="sold_notes">{{ $t('items.form.sold_notes') }}</label>
                <textarea id="sold_notes" v-model="form.sold_notes" rows="2" class="field" />
                <InputError :message="form.errors.sold_notes" />
            </div>
        </template>

        <div class="flex justify-end gap-2">
            <button type="submit" :disabled="form.processing" class="btn-primary">
                <Check :size="14" />
                {{ submitLabel ?? (mode === 'create' ? $t('common.create') : $t('common.save')) }}
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

/* Room appearance picker */
.appearance-hint {
    margin: 0 0 8px;
    font-size: 12px;
    color: var(--fg-subtle);
}
.appearance {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
}
.appearance-preview {
    width: 48px;
    height: 48px;
    flex-shrink: 0;
    border-radius: var(--radius);
    overflow: hidden;
    border: 1px solid var(--border);
}
/* Paperless link chips — same shape as Show.vue's read-only block, plus
   a trailing × per row that fires the unlink action. */
.paperless-list {
    list-style: none;
    padding: 0;
    margin: 0;
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.paperless-row {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px 10px;
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    background: var(--bg-elev);
}
.paperless-link {
    display: flex;
    align-items: center;
    gap: 8px;
    flex: 1;
    min-width: 0;
    color: inherit;
    text-decoration: none;
    font-size: 13px;
}
.paperless-link:hover .paperless-host {
    color: var(--accent);
}
.paperless-id {
    font-family: var(--font-mono, monospace);
    color: var(--fg);
}
/* Document-type pill from the cached Paperless snapshot ("Rechnung"). */
.paperless-type {
    flex-shrink: 0;
    font-size: 11px;
    padding: 1px 6px;
    border-radius: 999px;
    background: var(--bg-sunken);
    color: var(--fg-muted);
}
.paperless-host {
    color: var(--fg-muted);
}
.truncate {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
</style>
