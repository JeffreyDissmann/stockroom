<script setup lang="ts">
import { itemIcons } from '@/lib/itemIcons';
import { onClickOutside } from '@vueuse/core';
import { Check, ChevronDown } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{ modelValue: string }>();
const emit = defineEmits<{ 'update:modelValue': [value: string] }>();

const open = ref(false);
const root = ref<HTMLElement | null>(null);
onClickOutside(root, () => (open.value = false));

const selected = computed(() => itemIcons.find((i) => i.value === props.modelValue) ?? null);

function choose(value: string) {
    emit('update:modelValue', value);
    open.value = false;
}
</script>

<template>
    <div ref="root" class="icon-picker">
        <button type="button" class="field icon-trigger" :aria-expanded="open" @click="open = !open">
            <span class="icon-trigger-label">
                <component :is="selected.icon" v-if="selected" class="size-4" />
                {{ selected?.label ?? 'No icon (initials)' }}
            </span>
            <ChevronDown :size="14" class="icon-trigger-chevron" />
        </button>

        <div v-if="open" class="icon-menu" role="listbox">
            <button type="button" class="icon-option" :class="{ 'is-selected': !modelValue }" role="option" @click="choose('')">
                <span class="icon-option-label">No icon (initials)</span>
                <Check v-if="!modelValue" :size="14" />
            </button>
            <button
                v-for="opt in itemIcons"
                :key="opt.value"
                type="button"
                class="icon-option"
                :class="{ 'is-selected': opt.value === modelValue }"
                role="option"
                @click="choose(opt.value)"
            >
                <span class="icon-option-label">
                    <component :is="opt.icon" class="size-4" />
                    {{ opt.label }}
                </span>
                <Check v-if="opt.value === modelValue" :size="14" />
            </button>
        </div>
    </div>
</template>

<style scoped>
.icon-picker {
    position: relative;
    max-width: 220px;
    width: 100%;
}
.icon-trigger {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    cursor: pointer;
    text-align: left;
}
.icon-trigger-label {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
}
.icon-trigger-chevron {
    flex-shrink: 0;
    color: var(--fg-muted);
}
.icon-menu {
    position: absolute;
    z-index: 30;
    top: calc(100% + 4px);
    left: 0;
    right: 0;
    max-height: 280px;
    overflow-y: auto;
    padding: 4px;
    border-radius: var(--radius-sm);
    border: 1px solid var(--border);
    background: var(--bg-elev);
    box-shadow: var(--shadow-md, 0 8px 24px rgba(0, 0, 0, 0.18));
}
.icon-option {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    width: 100%;
    padding: 6px 8px;
    border-radius: var(--radius-sm);
    font-size: 13px;
    color: var(--fg);
    cursor: pointer;
    transition: background 0.1s;
}
.icon-option:hover {
    background: var(--bg-hover);
}
.icon-option.is-selected {
    background: var(--bg-sunken);
}
.icon-option-label {
    display: inline-flex;
    align-items: center;
    gap: 10px;
}
</style>
