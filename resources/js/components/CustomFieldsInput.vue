<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import type { CustomFieldDefinition } from '@/types';

type ValueMap = Record<number, string | number | boolean | null>;

const props = defineProps<{
    fields: CustomFieldDefinition[];
    modelValue: ValueMap;
    errors?: Record<string, string>;
}>();

const emit = defineEmits<{ 'update:modelValue': [ValueMap] }>();

function set(id: number, value: string | number | boolean | null) {
    emit('update:modelValue', { ...props.modelValue, [id]: value });
}

function onInput(id: number, event: Event) {
    set(id, (event.target as HTMLInputElement).value);
}

function onCheckbox(id: number, event: Event) {
    set(id, (event.target as HTMLInputElement).checked);
}
</script>

<template>
    <div v-if="fields.length" class="form-row">
        <div class="form-grid">
            <div v-for="field in fields" :key="field.id" class="form-row">
                <label v-if="field.type !== 'boolean'" :for="`cf-${field.id}`">{{ field.name }}</label>

                <label v-if="field.type === 'boolean'" class="flex items-center gap-2" style="font-size: 13px; cursor: pointer">
                    <input
                        :id="`cf-${field.id}`"
                        type="checkbox"
                        :checked="modelValue[field.id] === true"
                        @change="onCheckbox(field.id, $event)"
                    />
                    {{ field.name }}
                </label>
                <input
                    v-else-if="field.type === 'number'"
                    :id="`cf-${field.id}`"
                    type="number"
                    step="any"
                    class="field"
                    :value="modelValue[field.id] ?? ''"
                    @input="onInput(field.id, $event)"
                />
                <input
                    v-else-if="field.type === 'date'"
                    :id="`cf-${field.id}`"
                    type="date"
                    class="field"
                    :value="modelValue[field.id] ?? ''"
                    @input="onInput(field.id, $event)"
                />
                <input
                    v-else-if="field.type === 'url'"
                    :id="`cf-${field.id}`"
                    type="url"
                    class="field"
                    placeholder="https://…"
                    :value="modelValue[field.id] ?? ''"
                    @input="onInput(field.id, $event)"
                />
                <input
                    v-else
                    :id="`cf-${field.id}`"
                    type="text"
                    class="field"
                    :value="modelValue[field.id] ?? ''"
                    @input="onInput(field.id, $event)"
                />

                <InputError :message="errors?.[`custom_fields.${field.id}`]" />
            </div>
        </div>
    </div>
</template>
