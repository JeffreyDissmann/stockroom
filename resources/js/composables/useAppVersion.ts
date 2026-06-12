import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * The running build's version, shared globally via Inertia. Shown on the login
 * screen and in the authenticated sidebar. Empty in dev (no APP_VERSION /
 * APP_COMMIT build args), where `show` is false.
 */
export function useAppVersion() {
    const page = usePage<SharedData>();
    const version = computed(() => page.props.version);

    // Truthy check (not `!== null`): the prop can be momentarily undefined
    // before SharedData hydrates, which would otherwise render an empty chip.
    const show = computed(() => Boolean(version.value?.tag || version.value?.sha));

    // Prefers "<tag> · <sha>", falls back to whichever field is present.
    const label = computed(() => {
        const t = version.value?.tag;
        const s = version.value?.sha;
        if (t && s) return `${t} · ${s}`;
        return t || s || '';
    });

    return { show, label };
}
