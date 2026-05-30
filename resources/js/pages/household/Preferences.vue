<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { useIsAdmin } from '@/composables/useIsAdmin';
import { trans } from '@/composables/useTranslations';
import AppLayout from '@/layouts/AppLayout.vue';
import HouseholdLayout from '@/layouts/household/Layout.vue';
import householdPreferences from '@/routes/household/preferences';
import type { BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { watchDebounced } from '@vueuse/core';
import { Save, Search, X } from 'lucide-vue-next';
import { ref, watch } from 'vue';

interface TagOption {
    id: number;
    name: string;
    color: string | null;
}

interface ParentOption {
    id: number;
    name: string;
    type: 'room' | 'container';
}

interface Preferences {
    box_tag_id: number | null;
    paperless_parent_id: number | null;
}

const props = defineProps<{ preferences: Preferences; tags: TagOption[]; selectedParent: ParentOption | null }>();

const isAdmin = useIsAdmin();

const breadcrumbItems: BreadcrumbItem[] = [{ title: trans('household.nav.preferences'), href: '/household/preferences' }];

// `null` is a valid choice for either field — admin opts out of auto-tagging
// or chooses to drop intake at top level — so we preserve it through the
// form rather than coercing to 0 or undefined.
const form = useForm<{ box_tag_id: number | null; paperless_parent_id: number | null }>({
    box_tag_id: props.preferences.box_tag_id,
    paperless_parent_id: props.preferences.paperless_parent_id,
});

// Local picker state. Hydrated from props so the page renders the current
// selection without an extra fetch; once the user opens the picker we lazy-
// load candidates and search the server for further keystrokes.
const selected = ref<ParentOption | null>(props.selectedParent);
const pickerOpen = ref(false);
const pickerQuery = ref('');
const pickerResults = ref<ParentOption[]>([]);
const pickerLoading = ref(false);

async function fetchTargets(): Promise<void> {
    pickerLoading.value = true;
    try {
        const url = householdPreferences.paperlessParentTargets({ query: { q: pickerQuery.value } }).url;
        const response = await fetch(url, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        pickerResults.value = (await response.json()).targets ?? [];
    } finally {
        pickerLoading.value = false;
    }
}

watch(pickerOpen, (open) => {
    if (open) {
        pickerQuery.value = '';
        fetchTargets();
    }
});

watchDebounced(pickerQuery, () => fetchTargets(), { debounce: 250 });

function choose(option: ParentOption) {
    selected.value = option;
    form.paperless_parent_id = option.id;
    pickerOpen.value = false;
}

function clearSelection() {
    selected.value = null;
    form.paperless_parent_id = null;
    pickerOpen.value = false;
}

function submit() {
    form.put(householdPreferences.update().url, { preserveScroll: true });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="$t('household.nav.preferences')" />

        <HouseholdLayout>
            <div class="space-y-6">
                <HeadingSmall :title="$t('household.nav.preferences')" :description="$t('household.preferences.description')" />

                <p v-if="!isAdmin" class="text-sm" style="color: var(--fg-muted)">{{ $t('common.admin_only') }}</p>

                <form v-if="isAdmin" class="form" @submit.prevent="submit" data-test="preferences-form">
                    <div class="form-row">
                        <label for="box-tag">{{ $t('household.preferences.box_tag') }}</label>
                        <select id="box-tag" v-model="form.box_tag_id" class="field" data-test="box-tag-select">
                            <!-- `null` is a deliberate option: no auto-tagging when boxes are created. -->
                            <option :value="null">{{ $t('household.preferences.box_tag_none') }}</option>
                            <option v-for="tag in tags" :key="tag.id" :value="tag.id">{{ tag.name }}</option>
                        </select>
                        <InputError :message="form.errors.box_tag_id" />
                        <p style="font-size: 12px; color: var(--fg-muted)">{{ $t('household.preferences.box_tag_help') }}</p>
                    </div>

                    <div class="form-row">
                        <label>{{ $t('household.preferences.paperless_parent') }}</label>

                        <div class="parent-picker" data-test="paperless-parent-picker">
                            <!-- Current selection (or "none" placeholder). Clicking opens the search. -->
                            <button
                                v-if="!pickerOpen"
                                type="button"
                                class="field parent-picker-trigger"
                                data-test="paperless-parent-trigger"
                                @click="pickerOpen = true"
                            >
                                <span v-if="selected" class="parent-picker-label">
                                    {{ selected.name }}
                                    <span class="parent-picker-type">
                                        ({{ selected.type === 'room' ? $t('enums.item_type.room') : $t('enums.item_type.container') }})
                                    </span>
                                </span>
                                <span v-else class="parent-picker-empty">{{ $t('household.preferences.paperless_parent_none') }}</span>
                                <Search :size="14" class="parent-picker-icon" />
                            </button>

                            <div v-else class="parent-picker-panel">
                                <div class="parent-picker-input">
                                    <Search :size="14" :style="{ color: 'var(--fg-muted)' }" />
                                    <input
                                        v-model="pickerQuery"
                                        type="search"
                                        autofocus
                                        :placeholder="$t('household.preferences.paperless_parent_search')"
                                        data-test="paperless-parent-search"
                                        @keydown.escape="pickerOpen = false"
                                    />
                                </div>

                                <ul class="parent-picker-results" data-test="paperless-parent-results">
                                    <li>
                                        <button type="button" class="parent-picker-option" @click="clearSelection">
                                            <span class="parent-picker-empty">{{ $t('household.preferences.paperless_parent_none') }}</span>
                                        </button>
                                    </li>
                                    <li v-if="pickerLoading" class="parent-picker-status">
                                        {{ $t('common.loading') }}
                                    </li>
                                    <li v-else-if="pickerResults.length === 0" class="parent-picker-status">
                                        {{ $t('household.preferences.paperless_parent_no_match') }}
                                    </li>
                                    <li v-for="option in pickerResults" v-else :key="option.id">
                                        <button
                                            type="button"
                                            class="parent-picker-option"
                                            :data-test="`paperless-parent-option-${option.id}`"
                                            @click="choose(option)"
                                        >
                                            {{ option.name }}
                                            <span class="parent-picker-type">
                                                ({{ option.type === 'room' ? $t('enums.item_type.room') : $t('enums.item_type.container') }})
                                            </span>
                                        </button>
                                    </li>
                                </ul>

                                <button
                                    type="button"
                                    class="parent-picker-cancel"
                                    @click="pickerOpen = false"
                                >
                                    <X :size="12" />
                                    {{ $t('common.cancel') }}
                                </button>
                            </div>
                        </div>

                        <InputError :message="form.errors.paperless_parent_id" />
                        <p style="font-size: 12px; color: var(--fg-muted)">{{ $t('household.preferences.paperless_parent_help') }}</p>
                    </div>

                    <div>
                        <button type="submit" class="btn-primary" :disabled="form.processing" data-test="preferences-save">
                            <Save :size="14" />
                            {{ $t('common.save') }}
                        </button>
                        <span
                            v-if="form.recentlySuccessful"
                            class="ml-3 text-sm"
                            style="color: var(--pos)"
                        >{{ $t('common.saved') }}</span>
                    </div>
                </form>
            </div>
        </HouseholdLayout>
    </AppLayout>
</template>

<style scoped>
.parent-picker { position: relative; }
.parent-picker-trigger {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    width: 100%;
    text-align: left;
    cursor: pointer;
}
.parent-picker-label { color: var(--fg); }
.parent-picker-type { color: var(--fg-muted); font-size: 12px; margin-left: 4px; }
.parent-picker-empty { color: var(--fg-muted); }
.parent-picker-icon { color: var(--fg-muted); flex-shrink: 0; }

.parent-picker-panel {
    border: 1px solid var(--border);
    border-radius: var(--radius);
    background: var(--bg-elev);
    padding: 8px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.parent-picker-input {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 6px 10px;
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    background: var(--bg);
}
.parent-picker-input input {
    flex: 1;
    background: transparent;
    border: 0;
    outline: 0;
    font-size: 13px;
    color: var(--fg);
}
.parent-picker-results {
    list-style: none;
    margin: 0;
    padding: 0;
    max-height: 220px;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.parent-picker-option {
    width: 100%;
    text-align: left;
    background: transparent;
    border: 0;
    padding: 6px 10px;
    border-radius: var(--radius-sm);
    cursor: pointer;
    color: var(--fg);
    font-size: 13px;
}
.parent-picker-option:hover { background: var(--bg-hover); }
.parent-picker-status {
    padding: 6px 10px;
    color: var(--fg-muted);
    font-size: 13px;
}
.parent-picker-cancel {
    align-self: flex-end;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: transparent;
    border: 0;
    color: var(--fg-muted);
    font-size: 12px;
    padding: 4px 6px;
    cursor: pointer;
}
.parent-picker-cancel:hover { color: var(--fg); }
</style>
