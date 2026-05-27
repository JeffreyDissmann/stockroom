import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { computed, type ComputedRef } from 'vue';

/**
 * Whether the current user is a household admin. Admin-only controls (tag &
 * custom-field management, invites, backup/import/reindex) are hidden when false;
 * the server enforces the same boundary via the `admin` gate.
 */
export function useIsAdmin(): ComputedRef<boolean> {
    const page = usePage<SharedData>();

    return computed(() => page.props.auth.user?.is_admin ?? false);
}
