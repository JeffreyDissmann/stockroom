<script setup lang="ts">
import TextLink from '@/components/TextLink.vue';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head } from '@inertiajs/vue3';
</script>

<template>
    <AuthBase
        title="Invite link not valid"
        description="This invite link has expired, was already used, or doesn't exist. Ask whoever invited you for a fresh link."
    >
        <Head title="Invite not valid" />

        <div class="text-center text-sm text-muted-foreground">
            Already have an account?
            <TextLink :href="route('login')" class="underline underline-offset-4">Log in</TextLink>
        </div>
    </AuthBase>
</template>
