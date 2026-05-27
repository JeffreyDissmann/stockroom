import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';

type Replacements = Record<string, string | number>;

function fill(message: string, replace?: Replacements): string {
    if (!replace) {
        return message;
    }

    return Object.entries(replace).reduce((out, [key, value]) => out.replaceAll(`:${key}`, String(value)), message);
}

function lookup(key: string): string {
    // Falls back to the key itself when a translation is missing, so a missing
    // string is at least legible (and surfaces the offending key in dev).
    return usePage<SharedData>().props.translations?.[key] ?? key;
}

/**
 * Translate a dot-keyed string, e.g. t('items.add') or t('dashboard.welcome', { name }).
 */
export function trans(key: string, replace?: Replacements): string {
    return fill(lookup(key), replace);
}

/**
 * Pick a "singular|plural" form by count and interpolate :count.
 */
export function transChoice(key: string, count: number, replace?: Replacements): string {
    const parts = lookup(key).split('|');
    const message = count === 1 ? parts[0] : (parts[1] ?? parts[0]);

    return fill(message, { count, ...replace });
}

export function useTranslations() {
    return { t: trans, tChoice: transChoice };
}
