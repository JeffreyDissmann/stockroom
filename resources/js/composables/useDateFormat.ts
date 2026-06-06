import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';

/**
 * Locale-aware display formatting for date strings, following the user's
 * locale preference (page.props.locale — the per-user setting, not the
 * browser's). Counterpart to useCurrency for money.
 *
 * Not for <input type="date"> values — those are locale-independent
 * YYYY-MM-DD wire format; see lib/date.ts localToday().
 */
export function useDateFormat() {
    const locale = usePage<SharedData>().props.locale;

    function formatDate(iso: string | null | undefined): string {
        if (!iso) return '';
        return new Date(iso).toLocaleDateString(locale);
    }

    return { formatDate };
}
