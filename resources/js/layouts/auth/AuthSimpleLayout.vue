<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import AuthContextPanel from '@/components/AuthContextPanel.vue';
import { home } from '@/routes';
import { Link } from '@inertiajs/vue3';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div class="auth-shell">
        <div class="auth-grid">
            <!-- Context panel: above the form on mobile, to the right on
                 desktop. The order swap is the whole point of this layout
                 (vs the previous single-column centered form). -->
            <AuthContextPanel class="auth-grid__context" />

            <div class="auth-grid__form">
                <div class="flex flex-col gap-8">
                    <div class="flex flex-col items-center gap-4">
                        <Link :href="home().url" class="flex flex-col items-center gap-2 font-medium">
                            <div class="mb-1 flex h-9 w-9 items-center justify-center rounded-md">
                                <AppLogoIcon class="size-9 fill-current text-[var(--foreground)] dark:text-white" />
                            </div>
                            <span class="sr-only">{{ title }}</span>
                        </Link>
                        <div class="space-y-2 text-center">
                            <h1 class="text-xl font-medium">{{ title }}</h1>
                            <p class="text-center text-sm text-muted-foreground">{{ description }}</p>
                        </div>
                    </div>
                    <slot />
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.auth-shell {
    min-height: 100svh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px;
    background: var(--bg);
}
.auth-grid {
    display: grid;
    gap: 32px;
    width: 100%;
    max-width: 800px;
    align-items: start;

    /* Mobile-first: context above the form, single column. */
    grid-template-columns: 1fr;
    grid-template-areas:
        'context'
        'form';
}
.auth-grid__context { grid-area: context; justify-self: center; }
.auth-grid__form { grid-area: form; justify-self: center; width: 100%; max-width: 380px; }

/* Desktop: form on the left, context on the right. */
@media (min-width: 768px) {
    .auth-grid {
        grid-template-columns: minmax(0, 380px) minmax(0, 360px);
        grid-template-areas: 'form context';
        justify-content: center;
        align-items: center;
    }
    .auth-grid__context { justify-self: start; }
    .auth-grid__form { justify-self: end; }
}
</style>
