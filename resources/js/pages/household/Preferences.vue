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
import { Save } from 'lucide-vue-next';

interface TagOption {
    id: number;
    name: string;
    color: string | null;
}

interface Preferences {
    box_tag_id: number | null;
}

const props = defineProps<{ preferences: Preferences; tags: TagOption[] }>();

const isAdmin = useIsAdmin();

const breadcrumbItems: BreadcrumbItem[] = [{ title: trans('household.nav.preferences'), href: '/household/preferences' }];

// `null` is a valid choice — admin opts out of auto-tagging — so we
// preserve it through the form rather than coercing to 0 or undefined.
const form = useForm<{ box_tag_id: number | null }>({ box_tag_id: props.preferences.box_tag_id });

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
