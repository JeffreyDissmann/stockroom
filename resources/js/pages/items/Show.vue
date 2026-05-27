<script setup lang="ts">
import ActivityFeed from '@/components/ActivityFeed.vue';
import ItemCollection from '@/components/ItemCollection.vue';
import ItemTypeIcon from '@/components/ItemTypeIcon.vue';
import ItemViewToggle from '@/components/ItemViewToggle.vue';
import MoveItemDialog from '@/components/MoveItemDialog.vue';
import SearchImageDialog from '@/components/SearchImageDialog.vue';
import TagBadge from '@/components/TagBadge.vue';
import { itemIconMap } from '@/lib/itemIcons';
import { trans } from '@/composables/useTranslations';
import { useCurrency } from '@/composables/useCurrency';
import AppLayout from '@/layouts/AppLayout.vue';
import type { ActivityRow, BreadcrumbItemType, ItemImageSummary, ItemSummary, ItemViewMode, SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ChevronRight, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    item: ItemSummary;
    breadcrumb: ItemSummary[];
    children: ItemSummary[];
    activities: ActivityRow[];
}>();

const breadcrumbs = computed<BreadcrumbItemType[]>(() => {
    const base: BreadcrumbItemType[] = [{ title: 'Inventory', href: '/items' }];
    for (const item of props.breadcrumb) base.push({ title: item.name, href: `/items/${item.id}` });
    base.push({ title: props.item.name, href: `/items/${props.item.id}` });
    return base;
});

const page = usePage<SharedData>();

const images = computed<ItemImageSummary[]>(() => props.item.images ?? []);
const initialActive = computed<ItemImageSummary | null>(() => images.value.find((i) => i.is_primary) ?? images.value[0] ?? null);
const activeImageId = ref<number | null>(initialActive.value?.id ?? null);
const activeImage = computed<ItemImageSummary | null>(
    () => images.value.find((i) => i.id === activeImageId.value) ?? initialActive.value,
);

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
    return (words.slice(0, 2).map((w) => [...w][0]).join('') || '?').toUpperCase();
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

const customFields = computed(() => (props.item.custom_fields ?? []).filter((f) => f.value !== null && f.value !== ''));

function destroyItem() {
    if (!confirm(trans('items.show.delete_confirm', { name: props.item.name }))) return;
    router.delete(`/items/${props.item.id}`);
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="item.name" />

        <template #topbar-actions>
            <Link :href="`/items/${item.id}/edit`" class="btn-pill">
                <Pencil :size="14" />
                {{ $t('common.edit') }}
            </Link>
            <MoveItemDialog :item="item" />
            <SearchImageDialog v-if="page.props.features.imageSearch" :item-id="item.id" :item-name="item.name" />
            <button class="btn-pill btn-danger" type="button" @click="destroyItem">
                <Trash2 :size="14" />
                {{ $t('common.delete') }}
            </button>
            <Link :href="`/items/create?parent=${item.id}`" class="btn-primary">
                <Plus :size="14" />
                {{ $t('items.show.add_child') }}
            </Link>
        </template>

        <div class="page">
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
                        <div v-if="item.tags?.length" class="flex flex-wrap gap-1 mt-3">
                            <TagBadge v-for="tag in item.tags" :key="tag.id" :tag="tag" />
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-head">
                            <h3>{{ $t('items.show.where') }}</h3>
                        </div>
                        <div class="card-pad">
                            <div v-if="breadcrumb.length === 0" style="color: var(--fg-muted); font-size: 13px">{{ $t('items.show.top_level_none') }}</div>
                            <div v-else class="flex items-center flex-wrap gap-1.5" style="font-size: 13px">
                                <template v-for="(crumb, i) in breadcrumb" :key="crumb.id">
                                    <ChevronRight v-if="i > 0" :size="12" style="color: var(--fg-subtle)" />
                                    <Link :href="`/items/${crumb.id}`" class="flex items-center gap-1.5">
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
                                        <a :href="String(field.value)" target="_blank" rel="noopener noreferrer" style="color: var(--accent)">{{ field.value }}</a>
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

            <section class="mt-8">
                <div class="flex items-center justify-between mb-3 gap-3">
                    <h3 class="section-label" style="margin: 0">{{ $t('items.show.contents') }}</h3>
                    <div class="flex items-center gap-2">
                        <ItemViewToggle v-if="children.length" v-model="contentsView" />
                        <Link :href="`/items/create?parent=${item.id}`" class="btn-pill">
                            <Plus :size="14" />
                            {{ $t('items.show.add_child') }}
                        </Link>
                    </div>
                </div>

                <div v-if="children.length === 0" class="card card-pad" style="text-align: center; color: var(--fg-muted)">
                    {{ $t('items.show.empty_contents', { type: item.type.label.toLowerCase() }) }}
                </div>

                <ItemCollection v-else :items="children" :view="contentsView" />
            </section>

            <section v-if="activities.length" class="mt-8">
                <h3 class="section-label mb-3" style="margin: 0 0 12px">{{ $t('activity.title') }}</h3>
                <ActivityFeed :rows="activities" :show-subject="false" />
            </section>
        </div>
    </AppLayout>
</template>

<style scoped>
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
