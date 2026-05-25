<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import type { TagSummary } from '@/types';
import { Check, ChevronsUpDown, Tag as TagIcon } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{ tags: TagSummary[] }>();
const selected = defineModel<number[]>({ required: true });

const search = ref('');

const filtered = computed(() => {
    const q = search.value.trim().toLowerCase();
    return q ? props.tags.filter((tag) => tag.name.toLowerCase().includes(q)) : props.tags;
});

const label = computed(() => {
    if (selected.value.length === 0) {
        return 'All tags';
    }
    if (selected.value.length === 1) {
        return props.tags.find((tag) => tag.id === selected.value[0])?.name ?? '1 tag';
    }
    return `${selected.value.length} tags`;
});

function isSelected(id: number): boolean {
    return selected.value.includes(id);
}

function toggle(id: number): void {
    selected.value = isSelected(id) ? selected.value.filter((tagId) => tagId !== id) : [...selected.value, id];
}

function clear(): void {
    selected.value = [];
}
</script>

<template>
    <Popover>
        <PopoverTrigger as-child>
            <Button variant="outline" size="sm" class="h-8 justify-between gap-2 font-normal" :class="selected.length ? '' : 'text-muted-foreground'">
                <span class="flex items-center gap-2 truncate">
                    <TagIcon class="size-3.5 shrink-0" />
                    {{ label }}
                </span>
                <ChevronsUpDown class="size-3.5 shrink-0 opacity-50" />
            </Button>
        </PopoverTrigger>
        <PopoverContent align="start" class="w-60 p-0">
            <div class="border-b p-2">
                <Input v-model="search" placeholder="Filter tags…" class="h-8" />
            </div>
            <div class="max-h-64 overflow-y-auto p-1">
                <p v-if="filtered.length === 0" class="px-2 py-3 text-center text-sm text-muted-foreground">No tags found.</p>
                <button
                    v-for="tag in filtered"
                    :key="tag.id"
                    type="button"
                    class="flex w-full items-center gap-2 rounded-sm px-2 py-1.5 text-left text-sm hover:bg-accent hover:text-accent-foreground"
                    @click="toggle(tag.id)"
                >
                    <Checkbox :checked="isSelected(tag.id)" tabindex="-1" class="pointer-events-none size-4" />
                    <span class="size-2.5 shrink-0 rounded-full" :style="{ background: tag.color ?? 'transparent', border: tag.color ? 'none' : '1px solid var(--border)' }" />
                    <span class="truncate">{{ tag.name }}</span>
                </button>
            </div>
            <div v-if="selected.length" class="border-t p-1">
                <button type="button" class="flex w-full items-center justify-center gap-1.5 rounded-sm px-2 py-1.5 text-xs text-muted-foreground hover:bg-accent hover:text-accent-foreground" @click="clear">
                    <Check class="size-3" />
                    Clear {{ selected.length }} selected
                </button>
            </div>
        </PopoverContent>
    </Popover>
</template>
