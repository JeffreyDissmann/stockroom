<script setup lang="ts">
/**
 * Pick one tag to attach to or detach from the bulk-selected items.
 *
 * Uses the same tag chip visual as TagBadge so the picker reads as a
 * grid of selectable chips. `direction` decides labelling and the eventual
 * action (`attach-tag` vs `detach-tag`); the server validates either way.
 */
import TagBadge from '@/components/TagBadge.vue';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import type { TagSummary } from '@/types';
import { Check, Tag } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    tags: TagSummary[];
    direction: 'attach' | 'detach';
    count: number;
}>();

const emit = defineEmits<{
    apply: [tagId: number];
    close: [];
}>();

const open = ref(true);
const search = ref('');
const selected = ref<number | null>(null);

watch(open, (isOpen) => {
    if (!isOpen) emit('close');
});

const filtered = computed(() => {
    const q = search.value.trim().toLowerCase();
    if (!q) return props.tags;
    return props.tags.filter((t) => t.name.toLowerCase().includes(q));
});

function submit() {
    if (selected.value === null) return;
    emit('apply', selected.value);
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>
                    {{ direction === 'attach' ? $t('items.bulk.attach_tag_title') : $t('items.bulk.detach_tag_title') }}
                </DialogTitle>
                <DialogDescription>
                    {{ $tChoice(direction === 'attach' ? 'items.bulk.attach_tag_description' : 'items.bulk.detach_tag_description', count) }}
                </DialogDescription>
            </DialogHeader>

            <div class="form-row">
                <input
                    v-model="search"
                    type="search"
                    class="field"
                    :placeholder="$t('tags.filter.search')"
                    autofocus
                    data-test="bulk-tag-search"
                />
            </div>

            <ul v-if="filtered.length" class="bulk-tag-grid" data-test="bulk-tag-list">
                <li v-for="tag in filtered" :key="tag.id">
                    <button
                        type="button"
                        class="bulk-tag-option"
                        :class="{ 'bulk-tag-option--on': selected === tag.id }"
                        :data-test="`bulk-tag-option-${tag.id}`"
                        @click="selected = tag.id"
                    >
                        <Check v-if="selected === tag.id" :size="12" />
                        <Tag v-else :size="12" />
                        <TagBadge :tag="tag" />
                    </button>
                </li>
            </ul>
            <p v-else class="bulk-tag-empty">{{ $t('tags.empty') }}</p>

            <DialogFooter>
                <DialogClose as-child>
                    <button type="button" class="btn-ghost">{{ $t('common.cancel') }}</button>
                </DialogClose>
                <button
                    type="button"
                    class="btn-primary"
                    :disabled="selected === null"
                    data-test="bulk-tag-submit"
                    @click="submit"
                >
                    {{ direction === 'attach' ? $t('items.bulk.attach_tag') : $t('items.bulk.detach_tag') }}
                </button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

<style scoped>
.bulk-tag-grid {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    max-height: 280px;
    overflow-y: auto;
    padding-right: 4px;
}
.bulk-tag-option {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 8px;
    border: 1px solid var(--border);
    border-radius: 999px;
    background: var(--bg);
    color: var(--fg-muted);
    cursor: pointer;
    transition: border-color 0.12s, background 0.12s;
}
.bulk-tag-option:hover { border-color: var(--border-strong); }
.bulk-tag-option--on {
    border-color: var(--accent);
    background: color-mix(in srgb, var(--accent) 8%, transparent);
    color: var(--fg);
}
.bulk-tag-empty {
    padding: 12px;
    text-align: center;
    color: var(--fg-muted);
    font-size: 13px;
    margin: 0;
}
</style>
