<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthBase from '@/layouts/AuthLayout.vue';
import { login } from '@/routes';
import register from '@/routes/register';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';

const props = defineProps<{
    token: string;
    invitedBy?: string | null;
    invitedEmail?: string | null;
}>();

const form = useForm({
    name: '',
    // Emailed invites prefill the address they were sent to — editable,
    // the invite is not locked to it.
    email: props.invitedEmail ?? '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(register.store(props.token).url, {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <AuthBase
        :title="$t('auth_form.register.title')"
        :description="invitedBy ? $t('auth_form.register.description_invited', { name: invitedBy }) : $t('auth_form.register.description')"
    >
        <Head :title="$t('auth_form.register.meta')" />

        <form @submit.prevent="submit" class="flex flex-col gap-6">
            <div class="grid gap-6">
                <div class="grid gap-2">
                    <Label for="name">{{ $t('auth_form.fields.name') }}</Label>
                    <Input
                        id="name"
                        type="text"
                        required
                        autofocus
                        tabindex="1"
                        autocomplete="name"
                        v-model="form.name"
                        :placeholder="$t('auth_form.placeholders.name')"
                    />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="email">{{ $t('auth_form.fields.email') }}</Label>
                    <Input
                        id="email"
                        type="email"
                        required
                        tabindex="2"
                        autocomplete="email"
                        v-model="form.email"
                        :placeholder="$t('auth_form.placeholders.email')"
                    />
                    <InputError :message="form.errors.email" />
                </div>

                <div class="grid gap-2">
                    <Label for="password">{{ $t('auth_form.fields.password') }}</Label>
                    <Input
                        id="password"
                        type="password"
                        required
                        tabindex="3"
                        autocomplete="new-password"
                        v-model="form.password"
                        :placeholder="$t('auth_form.placeholders.password')"
                    />
                    <InputError :message="form.errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation">{{ $t('auth_form.fields.confirm_password') }}</Label>
                    <Input
                        id="password_confirmation"
                        type="password"
                        required
                        tabindex="4"
                        autocomplete="new-password"
                        v-model="form.password_confirmation"
                        :placeholder="$t('auth_form.placeholders.confirm_password')"
                    />
                    <InputError :message="form.errors.password_confirmation" />
                </div>

                <Button type="submit" class="mt-2 w-full" tabindex="5" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                    {{ $t('auth_form.register.submit') }}
                </Button>
            </div>

            <div class="text-center text-sm text-muted-foreground">
                {{ $t('auth_form.register.have_account') }}
                <TextLink :href="login().url" class="underline underline-offset-4" tabindex="6">{{ $t('auth_form.register.log_in') }}</TextLink>
            </div>
        </form>
    </AuthBase>
</template>
