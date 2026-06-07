<script setup lang="ts">
import ActivityFeed from '@/components/ActivityFeed.vue';
import BulkActionBar from '@/components/BulkActionBar.vue';
import BulkSelectToggle from '@/components/BulkSelectToggle.vue';
import CreateBoxDialog from '@/components/CreateBoxDialog.vue';
import ItemCollection from '@/components/ItemCollection.vue';
import ItemTypeIcon from '@/components/ItemTypeIcon.vue';
import ItemViewToggle from '@/components/ItemViewToggle.vue';
import LinkRelatedItemDialog from '@/components/LinkRelatedItemDialog.vue';
import MaintenanceHistory from '@/components/MaintenanceHistory.vue';
import MaintenanceSection from '@/components/MaintenanceSection.vue';
import MoveItemDialog from '@/components/MoveItemDialog.vue';
import TagBadge from '@/components/TagBadge.vue';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { useBulkSelection } from '@/composables/useBulkSelection';
import { useCurrency } from '@/composables/useCurrency';
import { trans } from '@/composables/useTranslations';
import AppLayout from '@/layouts/AppLayout.vue';
import { itemIconMap } from '@/lib/itemIcons';
import itemRoutes from '@/routes/items';
import relatedItemsRoutes from '@/routes/items/related-items';
import type { ActivityRow, BreadcrumbItemType, ItemImageSummary, ItemSummary, ItemViewMode, MaintenanceData, SharedData, TagSummary } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { CheckCircle2, ChevronRight, FileText, House, MoreVertical, PackageOpen, Pencil, Plus, Trash2, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

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
    item: ItemSummary;
    breadcrumb: ItemSummary[];
    children: ItemSummary[];
    relatedItems: ItemSummary[];
    paperlessLinks: PaperlessLinkSummary[];
    homeAssistantLink: HomeAssistantLinkSummary | null;
    maintenance: MaintenanceData;
    activities: ActivityRow[];
    // For the bulk-tag dialog launched from the Contents section.
    tags?: TagSummary[];
}>();

const breadcrumbs = computed<BreadcrumbItemType[]>(() => {
    const base: BreadcrumbItemType[] = [{ title: 'Inventory', href: itemRoutes.index().url }];
    for (const item of props.breadcrumb) base.push({ title: item.name, href: itemRoutes.show(item.id).url });
    base.push({ title: props.item.name, href: itemRoutes.show(props.item.id).url });
    return base;
});

const page = usePage<SharedData>();

// Bulk-select store wired to the Contents section. Cmd/Ctrl-A selects
// the current item's direct children; out-of-mode clicks navigate
// normally into each child.
const bulk = useBulkSelection(() => props.children.map((c) => c.id));

// One-shot success banner after the new box was created — Inertia's flash
// payload carries the source item's name so the message can name the thing
// the box was made for. The banner is dismissible and disappears on the
// next navigation regardless.
const boxCreatedFor = computed(() => page.props.flash?.box_created_for ?? null);
const boxBannerDismissed = ref(false);

// Ref into the box dialog component so the mobile More-menu item can open
// it imperatively (the inline trigger button is CSS-hidden below md).
const createBoxDialog = ref<InstanceType<typeof CreateBoxDialog> | null>(null);

const images = computed<ItemImageSummary[]>(() => props.item.images ?? []);
const initialActive = computed<ItemImageSummary | null>(() => images.value.find((i) => i.is_primary) ?? images.value[0] ?? null);
const activeImageId = ref<number | null>(initialActive.value?.id ?? null);
const activeImage = computed<ItemImageSummary | null>(() => images.value.find((i) => i.id === activeImageId.value) ?? initialActive.value);

watch(initialActive, (img) => {
    if (img && (activeImageId.value === null || !images.value.find((i) => i.id === activeImageId.value))) {
        activeImageId.value = img.id;
    }
});

// Rooms/containers without a photo fall back to their chosen icon, then initials.
const isPlace = computed(() => props.item.type.value === 'room' || props.item.type.value === 'container');
const heroIcon = computed(() => (props.item.icon ? (itemIconMap[props.item.icon] ?? null) : null));
const initials = computed(() => {
    const words = props.item.name.trim().split(/\s+/).filter(Boolean);
    return (
        words
            .slice(0, 2)
            .map((w) => [...w][0])
            .join('') || '?'
    ).toUpperCase();
});

const { format: fmtMoney } = useCurrency();

interface DetailRow {
    label: string;
    value: string;
    mono?: boolean;
}

const detailRows = computed<DetailRow[]>(() => {
    const i = props.item;
    const rows: DetailRow[] = [];
    if (i.type.details === false) return rows; // rooms carry no detail fields
    if (i.quantity != null) rows.push({ label: trans('items.show.labels.quantity'), value: String(i.quantity) });
    if (i.manufacturer) rows.push({ label: trans('items.show.labels.manufacturer'), value: i.manufacturer });
    if (i.model_number) rows.push({ label: trans('items.show.labels.model'), value: i.model_number });
    if (i.serial_number) rows.push({ label: trans('items.show.labels.serial'), value: i.serial_number, mono: true });
    if (i.purchased_from) rows.push({ label: trans('items.show.labels.purchased_from'), value: i.purchased_from });
    if (i.purchase_date) rows.push({ label: trans('items.show.labels.purchased'), value: i.purchase_date });
    const paid = fmtMoney(i.purchase_price);
    if (paid) rows.push({ label: trans('items.show.labels.paid'), value: paid, mono: true });
    if (i.lifetime_warranty) rows.push({ label: trans('items.show.labels.warranty'), value: trans('items.show.labels.lifetime') });
    else if (i.warranty_expires) rows.push({ label: trans('items.show.labels.warranty_until'), value: i.warranty_expires });
    return rows;
});

const isSold = computed(() => {
    const i = props.item;
    return Boolean(i.sold_to || i.sold_price || i.sold_date || i.sold_notes);
});

const soldRows = computed<DetailRow[]>(() => {
    const i = props.item;
    const rows: DetailRow[] = [];
    if (i.sold_to) rows.push({ label: trans('items.show.labels.sold_to'), value: i.sold_to });
    const price = fmtMoney(i.sold_price);
    if (price) rows.push({ label: trans('items.show.labels.sold_for'), value: price, mono: true });
    if (i.sold_date) rows.push({ label: trans('items.show.labels.sold_on'), value: i.sold_date });
    return rows;
});

const contentsView = ref<ItemViewMode>('grid');
const relatedView = ref<ItemViewMode>('grid');

// "Related items" section actions. Unlink uses Inertia router so the page
// refreshes the relatedItems prop on success — no manual list pruning here.
function unlinkRelated(related: ItemSummary) {
    if (!confirm(trans('items.related.unlink_confirm', { name: related.name }))) return;
    router.delete(relatedItemsRoutes.destroy([props.item.id, related.id]).url, { preserveScroll: true });
}

// The "Connections" card shows when the item has a Paperless doc and/or a
// Home Assistant link — one, both, or none.
const hasPaperlessLinks = computed(() => page.props.features.paperless && props.paperlessLinks.length > 0);
const hasConnections = computed(() => hasPaperlessLinks.value || props.homeAssistantLink !== null);

const customFields = computed(() => (props.item.custom_fields ?? []).filter((f) => f.value !== null && f.value !== ''));

function destroyItem() {
    if (!confirm(trans('items.show.delete_confirm', { name: props.item.name }))) return;
    router.delete(itemRoutes.destroy(props.item.id).url);
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="item.name" />

        <template #topbar-actions>
            <Link :href="itemRoutes.edit(item.id).url" class="btn-pill">
                <Pencil :size="14" />
                {{ $t('common.edit') }}
            </Link>
            <MoveItemDialog :item="item" />

            <!-- Secondary actions: visible inline on wide desktops, hidden
                 below `xl` (1280px) and reachable via the More dropdown.
                 Raising the breakpoint from `md` to `xl` catches the
                 deeply-nested-breadcrumb case where the action row would
                 otherwise overflow past the right edge of the viewport.
                 The dialogs stay mounted on both breakpoints; only their
                 inline triggers are hidden — the mobile/narrow menu opens
                 them via the exposed openDialog() method on each component. -->
            <!-- `!`-prefixed utilities so .btn-pill's `display: inline-flex`
                 in app.css (loaded after Tailwind) doesn't override the
                 hide. The `!` adds !important which wins regardless of
                 stylesheet order. -->
            <CreateBoxDialog ref="createBoxDialog" :item="item" trigger-class="!hidden xl:!inline-flex" />
            <button class="btn-pill btn-danger !hidden xl:!inline-flex" type="button" @click="destroyItem">
                <Trash2 :size="14" />
                {{ $t('common.delete') }}
            </button>

            <Link :href="itemRoutes.create({ query: { parent: item.id } }).url" class="btn-primary">
                <Plus :size="14" />
                {{ $t('items.show.add_child') }}
            </Link>

            <!-- Narrow-viewport More menu: surfaces Create box / Delete in
                 a single tap target so the topbar doesn't overflow on a
                 phone OR a tablet OR a narrow desktop with a deep
                 breadcrumb. Move and Edit are deliberately kept inline
                 because they're the everyday actions when reorganising
                 inventory. Right-alignment of the whole row is handled by
                 .topbar-actions { justify-content: flex-end }. -->
            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <!-- Icon-only ⋮ button: deliberately no "More" text so the
                         tap target stays compact next to "Add child". Square-
                         ish padding overrides .btn-pill's wider horizontal
                         padding. -->
                    <button
                        type="button"
                        class="btn-pill xl:!hidden"
                        style="padding: 5px 8px"
                        data-test="item-actions-more"
                        :aria-label="$t('common.more')"
                    >
                        <MoreVertical :size="16" />
                    </button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuItem data-test="item-actions-more-create-box" @click="createBoxDialog?.openDialog()">
                        <PackageOpen class="mr-2 h-4 w-4" />
                        {{ $t('items.box.trigger') }}
                    </DropdownMenuItem>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem data-test="item-actions-more-delete" class="text-destructive focus:text-destructive" @click="destroyItem">
                        <Trash2 class="mr-2 h-4 w-4" />
                        {{ $t('common.delete') }}
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </template>

        <div class="page">
            <!-- One-shot banner after creating this record via "Create a box
                 for <item>". Names the source item so the success message
                 reads like a sentence. Dismissible; gone on next nav. -->
            <div
                v-if="boxCreatedFor && !boxBannerDismissed"
                data-test="box-created-banner"
                role="status"
                style="
                    display: flex;
                    gap: 10px;
                    padding: 12px 14px;
                    margin-bottom: 16px;
                    border-radius: 8px;
                    background: color-mix(in srgb, var(--pos) 12%, transparent);
                    color: var(--pos);
                "
            >
                <CheckCircle2 :size="18" style="flex-shrink: 0; margin-top: 1px" />
                <p style="font-size: 13px; line-height: 1.5; margin: 0; flex: 1; color: var(--fg)">
                    {{ $t('items.box.created_for', { name: boxCreatedFor }) }}
                </p>
                <button
                    type="button"
                    class="btn-ghost"
                    style="padding: 2px 6px; font-size: 12px"
                    data-test="box-created-banner-dismiss"
                    :aria-label="$t('common.close')"
                    @click="boxBannerDismissed = true"
                >
                    <X :size="14" />
                </button>
            </div>

            <div class="detail-grid">
                <div class="gallery">
                    <div class="main-img" :class="{ 'is-empty': !activeImage }">
                        <img v-if="activeImage" :src="activeImage.large_url" :alt="item.name" class="gallery-img" />
                        <component :is="heroIcon" v-else-if="heroIcon" />
                        <span v-else-if="isPlace" class="main-img-initials">{{ initials }}</span>
                        <ItemTypeIcon v-else :type="item.type.value" />
                    </div>
                    <div v-if="images.length > 1" class="gallery-row">
                        <button
                            v-for="img in images"
                            :key="img.id"
                            type="button"
                            class="gallery-mini"
                            :class="{ 'gallery-mini-active': img.id === activeImage?.id }"
                            @click="activeImageId = img.id"
                        >
                            <img :src="img.thumb_url" :alt="''" />
                        </button>
                    </div>
                </div>

                <div class="flex flex-col gap-4">
                    <div>
                        <p class="section-label">{{ item.type.label }}</p>
                        <h1 style="margin: 4px 0 0; font-size: 26px; font-weight: 600; letter-spacing: -0.02em">{{ item.name }}</h1>
                        <p v-if="item.description" style="margin: 12px 0 0; color: var(--fg-muted); font-size: 14px">{{ item.description }}</p>
                        <div v-if="item.tags?.length" class="mt-3 flex flex-wrap gap-1">
                            <TagBadge v-for="tag in item.tags" :key="tag.id" :tag="tag" />
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-head">
                            <h3>{{ $t('items.show.where') }}</h3>
                        </div>
                        <div class="card-pad">
                            <div v-if="breadcrumb.length === 0" style="color: var(--fg-muted); font-size: 13px">
                                {{ $t('items.show.top_level_none') }}
                            </div>
                            <div v-else class="flex flex-wrap items-center gap-1.5" style="font-size: 13px">
                                <template v-for="(crumb, i) in breadcrumb" :key="crumb.id">
                                    <ChevronRight v-if="i > 0" :size="12" style="color: var(--fg-subtle)" />
                                    <Link :href="itemRoutes.show(crumb.id).url" class="flex items-center gap-1.5">
                                        <ItemTypeIcon :type="crumb.type.value" class="size-3.5" />
                                        <span>{{ crumb.name }}</span>
                                    </Link>
                                </template>
                            </div>
                        </div>
                    </div>

                    <div v-if="detailRows.length" class="card">
                        <div class="card-head">
                            <h3>{{ $t('items.show.details') }}</h3>
                        </div>
                        <div class="card-pad">
                            <dl class="kv">
                                <template v-for="row in detailRows" :key="row.label">
                                    <dt>{{ row.label }}</dt>
                                    <dd :class="{ mono: row.mono }">{{ row.value }}</dd>
                                </template>
                            </dl>
                            <p v-if="item.warranty_details" style="margin: 12px 0 0; font-size: 13px; color: var(--fg-muted)">
                                {{ item.warranty_details }}
                            </p>
                        </div>
                    </div>

                    <!-- "Connections" card: external links this item has — a
                         Home Assistant device and/or Paperless documents. An
                         item may have one, both, or none. Read-only here;
                         unlinking lives on the Edit page for both, so a
                         destructive action requires an explicit edit-mode
                         click first. -->
                    <div v-if="hasConnections" class="card" data-test="connections-block">
                        <div class="card-head">
                            <h3>{{ $t('items.links.section_title') }}</h3>
                        </div>
                        <div class="card-pad">
                            <ul class="paperless-list">
                                <li v-if="homeAssistantLink" class="paperless-row" data-test="ha-link-row">
                                    <a
                                        v-if="homeAssistantLink.url"
                                        :href="homeAssistantLink.url"
                                        target="_blank"
                                        rel="noopener"
                                        class="paperless-link"
                                    >
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
                                </li>
                                <li v-for="link in paperlessLinks" :key="link.document_id" class="paperless-row" data-test="paperless-row">
                                    <a :href="link.url" target="_blank" rel="noopener" class="paperless-link" :title="`#${link.document_id}`">
                                        <FileText :size="14" :style="{ color: 'var(--fg-muted)', flexShrink: 0 }" />
                                        <span v-if="link.type" class="paperless-type">{{ link.type }}</span>
                                        <span v-if="link.title" class="paperless-id truncate">{{ link.title }}</span>
                                        <span v-else class="paperless-id">#{{ link.document_id }}</span>
                                        <span class="paperless-host truncate">{{ $t('items.paperless.open_in_paperless') }}</span>
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <div v-if="customFields.length" class="card">
                        <div class="card-head">
                            <h3>{{ $t('items.show.custom_fields') }}</h3>
                        </div>
                        <div class="card-pad">
                            <dl class="kv">
                                <template v-for="field in customFields" :key="field.custom_field_id">
                                    <dt>{{ field.name }}</dt>
                                    <dd v-if="field.type === 'boolean'">{{ field.value ? $t('common.yes') : $t('common.no') }}</dd>
                                    <dd v-else-if="field.type === 'url'">
                                        <a :href="String(field.value)" target="_blank" rel="noopener noreferrer" style="color: var(--accent)">{{
                                            field.value
                                        }}</a>
                                    </dd>
                                    <dd v-else>{{ field.value }}</dd>
                                </template>
                            </dl>
                        </div>
                    </div>

                    <div v-if="isSold" class="card">
                        <div class="card-head">
                            <h3>{{ $t('items.show.sold') }}</h3>
                        </div>
                        <div class="card-pad">
                            <dl class="kv">
                                <template v-for="row in soldRows" :key="row.label">
                                    <dt>{{ row.label }}</dt>
                                    <dd :class="{ mono: row.mono }">{{ row.value }}</dd>
                                </template>
                            </dl>
                            <p v-if="item.sold_notes" style="margin: 12px 0 0; font-size: 13px; color: var(--fg-muted)">
                                {{ item.sold_notes }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Contents | Related side by side on wide screens — they're
                 sibling collections of the same shape, and pairing them
                 halves the scroll distance to Maintenance/Activity below. -->
            <div class="section-split mt-8">
                <section>
                    <div class="mb-3 flex items-center justify-between gap-3">
                        <h3 class="section-label" style="margin: 0">{{ $t('items.show.contents') }}</h3>
                        <div class="flex items-center gap-2">
                            <BulkSelectToggle v-if="children.length" />
                            <ItemViewToggle v-if="children.length" v-model="contentsView" />
                            <Link :href="itemRoutes.create({ query: { parent: item.id } }).url" class="btn-pill">
                                <Plus :size="14" />
                                {{ $t('items.show.add_child') }}
                            </Link>
                        </div>
                    </div>

                    <div v-if="children.length === 0" class="card card-pad" style="text-align: center; color: var(--fg-muted)">
                        {{ $t('items.show.empty_contents', { type: item.type.label.toLowerCase() }) }}
                    </div>

                    <!-- `selectable` participates in the same bulk-select store as
                         Items/Index and Search — clicking a child in select mode
                         toggles selection instead of navigating into it. -->
                    <ItemCollection v-else :items="children" :view="contentsView" selectable />
                </section>

                <!-- Related items: the durable many-to-many edge (separate from
                     the parent/child tree). Auto-populated when you create a box
                     for an item; can also be linked manually via the dialog.
                     Matches the Contents section's grid/list toggle so the two
                     sibling lists feel like the same UI element. -->
                <section data-test="related-items-section">
                    <div class="mb-3 flex items-center justify-between gap-3">
                        <h3 class="section-label" style="margin: 0">{{ $t('items.related.section_title') }}</h3>
                        <div class="flex items-center gap-2">
                            <ItemViewToggle v-if="relatedItems.length" v-model="relatedView" />
                            <LinkRelatedItemDialog :item="item" />
                        </div>
                    </div>

                    <div v-if="relatedItems.length === 0" class="card card-pad" style="text-align: center; color: var(--fg-muted); font-size: 13px">
                        {{ $t('items.related.empty') }}
                    </div>

                    <ItemCollection v-else :items="relatedItems" :view="relatedView" removable @remove="unlinkRelated" />
                </section>
            </div>

            <!-- Maintenance schedules, full width — the actionable block.
                 Below it the two audit trails (maintenance history and
                 activity) pair up side by side. -->
            <MaintenanceSection :item="item" :tasks="maintenance.tasks" />

            <div class="mt-8" :class="{ 'section-split': activities.length }">
                <MaintenanceHistory :item="item" :entries="maintenance.entries" />

                <section v-if="activities.length">
                    <h3 class="section-label mb-3" style="margin: 0 0 12px">{{ $t('activity.title') }}</h3>
                    <ActivityFeed :rows="activities" :show-subject="false" />
                </section>
            </div>
        </div>

        <BulkActionBar v-if="bulk.isSelectMode.value" :tags="tags ?? []" />
    </AppLayout>
</template>

<style scoped>
/* Contents | Related two-up on wide screens (the app's single 880px
   breakpoint); stacked below it. align-items: start keeps a short list
   from stretching to its neighbour's height. */
.section-split {
    display: grid;
    grid-template-columns: 1fr;
    gap: 32px 28px;
    align-items: start;
}
@media (min-width: 881px) {
    .section-split {
        grid-template-columns: 1fr 1fr;
    }
}

/* Paperless link chips. One row per linked doc: id + open-in-Paperless.
   Lives in its own card above the custom fields card. */
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

/* Place initials shown big when a room/container has no photo and no chosen icon. */
.main-img-initials {
    font-size: 48px;
    font-weight: 600;
    letter-spacing: -0.02em;
    line-height: 1;
    color: var(--fg-muted);
}
/* Box hugs the photo's real shape, capped so it can't dominate the screen. */
.gallery-img {
    display: block;
    max-width: 100%;
    max-height: 60vh;
    width: auto;
    height: auto;
    background: var(--bg-sunken);
}
@media (max-width: 880px) {
    .gallery-img {
        max-height: 40vh;
    }
}
.gallery-row {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(70px, 1fr));
    gap: 8px;
    margin-top: 8px;
}
.gallery-mini {
    aspect-ratio: 1 / 1;
    border-radius: var(--radius-sm);
    overflow: hidden;
    border: 1px solid var(--border);
    background: var(--bg-sunken);
    padding: 0;
    cursor: pointer;
}
.gallery-mini img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}
.gallery-mini-active {
    outline: 2px solid var(--fg);
    outline-offset: -2px;
}
</style>
