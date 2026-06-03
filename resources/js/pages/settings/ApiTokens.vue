<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { trans } from '@/composables/useTranslations';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { destroy, store } from '@/routes/api-tokens';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

interface ApiToken {
    id: number;
    name: string;
    abilities: string[];
    last_used_at: string | null;
    created_at: string | null;
}

interface Props {
    tokens: ApiToken[];
    plainTextToken: string | null;
}

defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: trans('settings.api_tokens.breadcrumb'),
        href: '/settings/api-tokens',
    },
];

const form = useForm<{ name: string; abilities: string[] }>({
    name: '',
    abilities: ['read'],
});

const toggleAbility = (ability: string, checked: boolean) => {
    const set = new Set(form.abilities);
    if (checked) {
        set.add(ability);
    } else {
        set.delete(ability);
    }
    form.abilities = [...set];
};

const createToken = () => {
    form.post(store().url, {
        preserveScroll: true,
        onSuccess: () => form.reset('name'),
    });
};

const revoke = (token: ApiToken) => {
    router.delete(destroy(token.id).url, { preserveScroll: true });
};

const copied = ref(false);
const copyToken = async (token: string) => {
    await navigator.clipboard.writeText(token);
    copied.value = true;
    window.setTimeout(() => (copied.value = false), 2000);
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="$t('settings.api_tokens.breadcrumb')" />

        <SettingsLayout>
            <div class="space-y-6">
                <HeadingSmall :title="$t('settings.api_tokens.title')" :description="$t('settings.api_tokens.description')" />

                <!-- One-time plaintext token, shown immediately after creation. -->
                <div
                    v-if="plainTextToken"
                    class="space-y-2 rounded-lg border border-green-500/40 bg-green-500/5 p-4"
                    data-test="api-token-plaintext"
                >
                    <p class="text-sm font-medium">{{ $t('settings.api_tokens.created_title') }}</p>
                    <div class="flex items-center gap-2">
                        <code class="flex-1 break-all rounded bg-muted px-3 py-2 font-mono text-sm">{{ plainTextToken }}</code>
                        <Button type="button" variant="secondary" data-test="api-token-copy" @click="copyToken(plainTextToken)">
                            {{ copied ? $t('settings.api_tokens.copied') : $t('settings.api_tokens.copy') }}
                        </Button>
                    </div>
                    <p class="text-xs text-muted-foreground">{{ $t('settings.api_tokens.created_hint') }}</p>
                </div>

                <!-- Create a new token. -->
                <form @submit.prevent="createToken" class="space-y-4">
                    <div class="grid gap-2">
                        <Label for="token_name">{{ $t('settings.api_tokens.name_label') }}</Label>
                        <Input
                            id="token_name"
                            v-model="form.name"
                            type="text"
                            class="mt-1 block w-full"
                            :placeholder="$t('settings.api_tokens.name_placeholder')"
                        />
                        <InputError :message="form.errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <span class="text-sm font-medium">{{ $t('settings.api_tokens.abilities_label') }}</span>
                        <Label for="ability_read" class="flex items-center space-x-3 font-normal">
                            <Checkbox
                                id="ability_read"
                                :checked="form.abilities.includes('read')"
                                @update:checked="(v: boolean) => toggleAbility('read', v)"
                            />
                            <span>{{ $t('settings.api_tokens.ability_read') }}</span>
                        </Label>
                        <Label for="ability_write" class="flex items-center space-x-3 font-normal">
                            <Checkbox
                                id="ability_write"
                                :checked="form.abilities.includes('write')"
                                @update:checked="(v: boolean) => toggleAbility('write', v)"
                            />
                            <span>{{ $t('settings.api_tokens.ability_write') }}</span>
                        </Label>
                        <InputError :message="form.errors.abilities" />
                    </div>

                    <Button :disabled="form.processing" data-test="api-token-create">
                        {{ $t('settings.api_tokens.create') }}
                    </Button>
                </form>

                <!-- Active tokens. -->
                <div class="space-y-3">
                    <p class="text-sm font-medium">{{ $t('settings.api_tokens.existing_title') }}</p>

                    <p v-if="tokens.length === 0" class="text-sm text-muted-foreground">
                        {{ $t('settings.api_tokens.empty') }}
                    </p>

                    <ul v-else class="divide-y rounded-lg border">
                        <li v-for="token in tokens" :key="token.id" class="flex items-center justify-between gap-4 p-4">
                            <div class="min-w-0">
                                <p class="truncate font-medium">{{ token.name }}</p>
                                <p class="text-xs text-muted-foreground">
                                    <span v-for="ability in token.abilities" :key="ability" class="mr-1 rounded bg-muted px-1.5 py-0.5">
                                        {{ ability }}
                                    </span>
                                    <span class="ml-1">
                                        {{ token.last_used_at ? $t('settings.api_tokens.last_used', { time: token.last_used_at }) : $t('settings.api_tokens.never_used') }}
                                    </span>
                                </p>
                            </div>
                            <Button type="button" variant="ghost" data-test="api-token-revoke" @click="revoke(token)">
                                {{ $t('settings.api_tokens.revoke') }}
                            </Button>
                        </li>
                    </ul>
                </div>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
