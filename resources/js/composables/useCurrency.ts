import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';

/**
 * Formats money amounts using the household's configured currency
 * (set centrally via the CURRENCY / CURRENCY_LOCALE env vars and shared
 * on every Inertia page as `currency`).
 */
export function useCurrency() {
    const page = usePage<SharedData>();

    function format(value: string | number | null | undefined): string | null {
        if (value === null || value === undefined || value === '') return null;
        const amount = Number(value);
        if (Number.isNaN(amount)) return null;

        const { code, locale } = page.props.currency;
        return new Intl.NumberFormat(locale, { style: 'currency', currency: code }).format(amount);
    }

    return { format };
}
