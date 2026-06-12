<script setup lang="ts">
/**
 * Sits next to the auth form on login / register / forgot-password screens
 * to give first-time visitors a quick "what is this and who made it" hit
 * without putting it in a modal or hiding it behind a menu.
 *
 * Two-column on md+ (form left, this on the right); stacks above the form
 * on mobile so the pitch is the first thing visible without scrolling.
 *
 * Pure presentation — no auth state, no Inertia events. Version + commit
 * come from shared props (see App\Support\AppVersion); the chip hides
 * itself when both fields are null (e.g. fresh clone without a git tag).
 */
import { useAppVersion } from '@/composables/useAppVersion';
import { trans } from '@/composables/useTranslations';
import { Github, Heart, Scale } from 'lucide-vue-next';

// Version chip — shared with the authenticated user menu (see useAppVersion).
// Hidden when both tag and sha are null (e.g. fresh clone without a git tag).
const { show: showVersion, label: versionLabel } = useAppVersion();
</script>

<template>
    <aside class="auth-context" data-test="auth-context">
        <h2 class="auth-context__pitch">{{ trans('auth_context.pitch') }}</h2>
        <p class="auth-context__status">{{ trans('auth_context.status') }}</p>

        <ul class="auth-context__links">
            <li>
                <Heart :size="14" />
                <span>{{ trans('auth_context.built_by') }}</span>
            </li>
            <li>
                <Github :size="14" />
                <a href="https://github.com/JeffreyDissmann/stockroom" target="_blank" rel="noopener">
                    {{ trans('auth_context.github') }}
                </a>
            </li>
            <li>
                <Scale :size="14" />
                <a href="https://github.com/JeffreyDissmann/stockroom/blob/main/LICENSE" target="_blank" rel="noopener">
                    {{ trans('auth_context.license') }}
                </a>
            </li>
        </ul>

        <p v-if="showVersion" class="auth-context__version" data-test="auth-version">
            {{ versionLabel }}
        </p>
    </aside>
</template>

<style scoped>
.auth-context {
    display: flex;
    flex-direction: column;
    gap: 14px;
    padding: 24px;
    max-width: 360px;
    border-radius: 12px;
    background: var(--bg-elev);
    border: 1px solid var(--border);
    color: var(--fg);
}
.auth-context__pitch {
    margin: 0;
    font-size: 15px;
    font-weight: 600;
    line-height: 1.45;
}
.auth-context__status {
    margin: 0;
    font-size: 13px;
    color: var(--fg-muted);
    line-height: 1.5;
}
.auth-context__links {
    list-style: none;
    margin: 6px 0 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 8px;
    font-size: 13px;
}
.auth-context__links li {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: var(--fg-muted);
}
.auth-context__links a {
    color: var(--fg);
    text-decoration: none;
}
.auth-context__links a:hover {
    color: var(--accent);
    text-decoration: underline;
}
.auth-context__version {
    margin: 4px 0 0;
    font-size: 11px;
    color: var(--fg-subtle);
    font-family: var(--font-mono, ui-monospace, monospace);
    letter-spacing: 0.02em;
}
</style>
