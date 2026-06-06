<script setup lang="ts">
import { TransitionRoot } from '@headlessui/vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';

import DeleteUser from '@/components/DeleteUser.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { trans } from '@/composables/useTranslations';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import profile from '@/routes/profile';
import { type BreadcrumbItem, type SharedData, type User } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: trans('settings.profile.breadcrumb'),
        href: profile.edit().url,
    },
];

const page = usePage<SharedData>();
const user = page.props.auth.user as User;

const form = useForm({
    name: user.name,
    email: user.email,
    maintenance_digest_opt_in: user.maintenance_digest_opt_in,
});

const submit = () => {
    form.patch(profile.update().url, {
        preserveScroll: true,
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="$t('settings.profile.breadcrumb')" />

        <SettingsLayout>
            <div class="flex flex-col space-y-6">
                <HeadingSmall :title="$t('settings.profile.title')" :description="$t('settings.profile.description')" />

                <form @submit.prevent="submit" class="space-y-6">
                    <div class="grid gap-2">
                        <Label for="name">{{ $t('common.name') }}</Label>
                        <Input
                            id="name"
                            class="mt-1 block w-full"
                            v-model="form.name"
                            required
                            autocomplete="name"
                            :placeholder="$t('settings.profile.name_placeholder')"
                        />
                        <InputError class="mt-2" :message="form.errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="email">{{ $t('settings.profile.email_label') }}</Label>
                        <Input
                            id="email"
                            type="email"
                            class="mt-1 block w-full"
                            v-model="form.email"
                            required
                            autocomplete="username"
                            :placeholder="$t('settings.profile.email_placeholder')"
                        />
                        <InputError class="mt-2" :message="form.errors.email" />
                    </div>

                    <div class="flex items-start gap-3">
                        <!-- radix-vue checkbox: :checked/@update:checked, not v-model
                             (same binding as ApiTokens.vue). -->
                        <Checkbox
                            id="maintenance-digest"
                            :checked="form.maintenance_digest_opt_in"
                            data-test="maintenance-digest-toggle"
                            @update:checked="(v: boolean) => (form.maintenance_digest_opt_in = v)"
                        />
                        <div class="grid gap-0.5">
                            <Label for="maintenance-digest">{{ $t('settings.profile.maintenance_digest_label') }}</Label>
                            <p class="text-sm" style="color: var(--fg-muted)">{{ $t('settings.profile.maintenance_digest_hint') }}</p>
                        </div>
                    </div>
                    <InputError class="mt-2" :message="form.errors.maintenance_digest_opt_in" />

                    <div class="flex items-center gap-4">
                        <Button :disabled="form.processing">{{ $t('common.save') }}</Button>

                        <TransitionRoot
                            :show="form.recentlySuccessful"
                            enter="transition ease-in-out"
                            enter-from="opacity-0"
                            leave="transition ease-in-out"
                            leave-to="opacity-0"
                        >
                            <p class="text-sm text-neutral-600">{{ $t('common.saved') }}</p>
                        </TransitionRoot>
                    </div>
                </form>
            </div>

            <DeleteUser />
        </SettingsLayout>
    </AppLayout>
</template>
