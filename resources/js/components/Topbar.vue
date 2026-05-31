<script setup lang="ts">
/**
 * App-shell topbar: breadcrumb on the left, action slot on the right.
 *
 * Truncation: when the chain has more than `MAX_VISIBLE_CRUMBS` entries,
 * the middle is collapsed into a `…` button that opens a dropdown with
 * the hidden crumbs. Last two crumbs always stay visible because the
 * parent + current item are the most navigationally useful. First crumb
 * also stays so the user can jump home in one click.
 *
 * `min-width: 0` on `.crumb` lets the breadcrumb shrink-to-fit rather
 * than push the action row off-screen when the chain is long but doesn't
 * cross the truncation threshold. Single long crumb names also ellipsis
 * via `.crumb-link`.
 */
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import type { BreadcrumbItemType } from '@/types';
import { Link } from '@inertiajs/vue3';
import { ChevronRight, MoreHorizontal } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    breadcrumbs?: BreadcrumbItemType[];
}>();

const MAX_VISIBLE_CRUMBS = 4;

interface CrumbView {
    type: 'crumb';
    crumb: BreadcrumbItemType;
    isCurrent: boolean;
}

interface EllipsisView {
    type: 'ellipsis';
    hidden: BreadcrumbItemType[];
}

const visibleCrumbs = computed<(CrumbView | EllipsisView)[]>(() => {
    const all = props.breadcrumbs ?? [];
    if (all.length <= MAX_VISIBLE_CRUMBS) {
        return all.map((c, i) => ({ type: 'crumb' as const, crumb: c, isCurrent: i === all.length - 1 }));
    }

    // Show: first · … · second-to-last · last. Anything else collapses
    // into the ellipsis dropdown. This keeps Home + parent + current
    // always visible no matter how deep the tree gets.
    const first = all[0];
    const last = all[all.length - 1];
    const secondToLast = all[all.length - 2];
    const hidden = all.slice(1, all.length - 2);

    return [
        { type: 'crumb', crumb: first, isCurrent: false },
        { type: 'ellipsis', hidden },
        { type: 'crumb', crumb: secondToLast, isCurrent: false },
        { type: 'crumb', crumb: last, isCurrent: true },
    ];
});
</script>

<template>
    <div class="topbar">
        <nav class="crumb" :aria-label="$t('common.breadcrumb')">
            <template v-for="(entry, i) in visibleCrumbs" :key="i">
                <ChevronRight v-if="i > 0" class="sep" :size="12" />

                <template v-if="entry.type === 'crumb'">
                    <Link
                        v-if="!entry.isCurrent"
                        :href="entry.crumb.href"
                        class="crumb-link"
                    >{{ entry.crumb.title }}</Link>
                    <span v-else class="current crumb-link">{{ entry.crumb.title }}</span>
                </template>

                <DropdownMenu v-else>
                    <DropdownMenuTrigger as-child>
                        <button
                            type="button"
                            class="crumb-ellipsis"
                            :aria-label="$t('common.more')"
                            data-test="breadcrumb-ellipsis"
                        >
                            <MoreHorizontal :size="12" />
                        </button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="start">
                        <DropdownMenuItem
                            v-for="(crumb, idx) in entry.hidden"
                            :key="idx"
                            as-child
                        >
                            <Link :href="crumb.href">{{ crumb.title }}</Link>
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </template>
        </nav>
        <div class="grow" />
        <div class="topbar-actions">
            <slot name="actions" />
        </div>
    </div>
</template>

<style scoped>
.crumb {
    /* Without `min-width: 0` the breadcrumb refuses to shrink below its
       content width, which then pushes the topbar-actions off-screen on
       deeply nested items. */
    min-width: 0;
    flex: 1 1 auto;
    overflow: hidden;
}
.crumb-link {
    /* Per-crumb truncation so a single very long item name doesn't take
       over the row. The flex container above takes care of overall width. */
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    max-width: 24ch;
}
.crumb-ellipsis {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 22px;
    height: 22px;
    padding: 0;
    border: 1px solid var(--border);
    border-radius: 4px;
    background: var(--bg-elev);
    color: var(--fg-muted);
    cursor: pointer;
}
.crumb-ellipsis:hover { color: var(--fg); border-color: var(--border-strong); }
</style>
