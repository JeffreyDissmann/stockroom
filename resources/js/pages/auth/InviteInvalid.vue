<script setup lang="ts">
import TextLink from '@/components/TextLink.vue';
import AuthBase from '@/layouts/AuthLayout.vue';
import { login } from '@/routes';
import { Head } from '@inertiajs/vue3';
</script>

<template>
    <AuthBase :title="$t('auth_form.invite_invalid.title')" :description="$t('auth_form.invite_invalid.description')">
        <Head :title="$t('auth_form.invite_invalid.meta')" />

        <div class="text-center text-sm text-muted-foreground">
            {{ $t('auth_form.invite_invalid.have_account') }}
            <TextLink :href="login().url" class="underline underline-offset-4">{{ $t('auth_form.invite_invalid.log_in') }}</TextLink>
        </div>
    </AuthBase>
</template>
