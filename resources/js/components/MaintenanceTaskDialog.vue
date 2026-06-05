<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { localToday } from '@/lib/date';
import maintenanceTasks from '@/routes/items/maintenance-tasks';
import type { ItemSummary, MaintenanceIntervalUnit, MaintenanceSchedulePreset, MaintenanceTaskRow, SharedData } from '@/types';
import { useForm, usePage } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

/**
 * Create/edit dialog for a maintenance task. The schedule TYPE is the first
 * choice ("after completion" / "fixed calendar" / "once") because "every 6
 * months" exists in both of the first two with different semantics — the
 * user must pick the behaviour before the cadence.
 *
 * Open state is owned by the parent (one dialog instance serves every task
 * card); `task` null means create.
 */
const props = defineProps<{
    item: ItemSummary;
    task: MaintenanceTaskRow | null;
}>();

const open = defineModel<boolean>('open', { required: true });

const page = usePage<SharedData>();

const form = useForm({
    title: '',
    description: '',
    schedule_type: 'interval' as MaintenanceTaskRow['schedule_type'],
    interval_value: 6 as number | null,
    interval_unit: 'months' as MaintenanceIntervalUnit,
    preset: 'every' as NonNullable<MaintenanceSchedulePreset['preset']>,
    preset_interval: 3 as number | null,
    preset_unit: 'months' as MaintenanceIntervalUnit,
    preset_month: 1,
    preset_day: 1,
    preset_ordinal: 1,
    preset_weekday: 'MO' as NonNullable<MaintenanceSchedulePreset['weekday']>,
    // null = "every month" for the nth-weekday preset.
    preset_in_month: null as number | null,
    next_due_at: localToday(),
    reminder_lead_days: 7 as number | null,
});

// A calendar task whose stored rule the presets cannot express: the builder
// is hidden and the form omits schedule_preset, which the server reads as
// "keep the stored rule".
const isCustomRule = computed(
    () => props.task !== null && props.task.schedule_type === 'calendar' && props.task.schedule_preset === null && form.schedule_type === 'calendar',
);

// The submit button stays inert until the chosen schedule type has every
// field it needs — cleared number inputs surface as '' via v-model.number,
// hence the typeof checks.
const isPositiveInt = (value: unknown): boolean => typeof value === 'number' && Number.isInteger(value) && value >= 1;

const canSubmit = computed(() => {
    if (!form.title.trim()) return false;
    if (typeof form.reminder_lead_days !== 'number' || form.reminder_lead_days < 0 || form.reminder_lead_days > 365) return false;

    switch (form.schedule_type) {
        case 'interval':
            return isPositiveInt(form.interval_value) && (form.interval_value as number) <= 999;
        case 'one_off':
            return form.next_due_at !== '';
        case 'calendar':
            if (isCustomRule.value) return true;
            if (form.preset === 'every') return isPositiveInt(form.preset_interval) && (form.preset_interval as number) <= 999;
            if (form.preset === 'yearly_on') return isPositiveInt(form.preset_day) && form.preset_day <= 31;
            // nth_weekday: every field is a select that always holds a value.
            return true;
    }
    return false;
});

form.transform((data) => {
    const base = {
        title: data.title,
        description: data.description || null,
        schedule_type: data.schedule_type,
        reminder_lead_days: data.reminder_lead_days,
    };

    if (data.schedule_type === 'interval') {
        return { ...base, interval_value: data.interval_value, interval_unit: data.interval_unit };
    }
    if (data.schedule_type === 'one_off') {
        return { ...base, next_due_at: data.next_due_at };
    }
    if (isCustomRule.value) {
        return base;
    }

    const preset: MaintenanceSchedulePreset =
        data.preset === 'every'
            ? { preset: 'every', interval: data.preset_interval ?? 1, unit: data.preset_unit }
            : data.preset === 'yearly_on'
              ? { preset: 'yearly_on', month: data.preset_month, day: data.preset_day }
              : { preset: 'nth_weekday', ordinal: data.preset_ordinal, weekday: data.preset_weekday, month: data.preset_in_month };

    return { ...base, schedule_preset: preset };
});

// Re-prime whenever the dialog opens: hydrate from the task being edited,
// or reset to create-defaults.
watch(open, (isOpen) => {
    if (!isOpen) return;
    form.clearErrors();

    const task = props.task;
    form.title = task?.title ?? '';
    form.description = task?.description ?? '';
    form.schedule_type = task?.schedule_type ?? 'interval';
    form.interval_value = task?.interval_value ?? 6;
    form.interval_unit = task?.interval_unit ?? 'months';
    form.next_due_at = (task?.schedule_type === 'one_off' ? task.next_due_at : null) ?? localToday();
    form.reminder_lead_days = task?.reminder_lead_days ?? 7;

    const preset = task?.schedule_preset;
    form.preset = preset?.preset ?? 'every';
    form.preset_interval = preset?.preset === 'every' ? (preset.interval ?? 1) : 3;
    form.preset_unit = preset?.preset === 'every' ? (preset.unit ?? 'months') : 'months';
    form.preset_month = preset?.preset === 'yearly_on' ? (preset.month ?? 1) : 1;
    form.preset_day = preset?.preset === 'yearly_on' ? (preset.day ?? 1) : 1;
    form.preset_ordinal = preset?.preset === 'nth_weekday' ? (preset.ordinal ?? 1) : 1;
    form.preset_weekday = preset?.preset === 'nth_weekday' ? (preset.weekday ?? 'MO') : 'MO';
    form.preset_in_month = preset?.preset === 'nth_weekday' ? (preset.month ?? null) : null;
});

// First nested schedule_preset.* error, surfaced under the builder as one
// message — the individual sub-fields are too small to each carry their own.
const presetError = computed(() => {
    const errors = form.errors as Record<string, string>;
    const key = Object.keys(errors).find((k) => k.startsWith('schedule_preset'));
    return key ? errors[key] : undefined;
});

const monthNames = computed(() =>
    Array.from({ length: 12 }, (_, i) => new Date(2000, i, 1).toLocaleDateString(page.props.locale, { month: 'long' })),
);

const units: MaintenanceIntervalUnit[] = ['days', 'weeks', 'months', 'years'];
const ordinals = [1, 2, 3, 4, -1];
const weekdays: NonNullable<MaintenanceSchedulePreset['weekday']>[] = ['MO', 'TU', 'WE', 'TH', 'FR', 'SA', 'SU'];

function submit() {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
        },
    };

    if (props.task) {
        form.patch(maintenanceTasks.update([props.item.id, props.task.id]).url, options);
    } else {
        form.post(maintenanceTasks.store(props.item.id).url, options);
    }
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ task ? $t('maintenance.dialog.edit_title') : $t('maintenance.dialog.create_title') }}</DialogTitle>
                <DialogDescription>{{ $t('maintenance.dialog.description', { name: item.name }) }}</DialogDescription>
            </DialogHeader>

            <form class="form" @submit.prevent="submit">
                <div class="form-row">
                    <label for="task-title">{{ $t('maintenance.dialog.title_label') }}</label>
                    <input
                        id="task-title"
                        v-model="form.title"
                        type="text"
                        class="field"
                        :placeholder="$t('maintenance.dialog.title_placeholder')"
                        data-test="maintenance-task-title"
                    />
                    <InputError :message="form.errors.title" />
                </div>

                <div class="form-row">
                    <label for="task-description">{{ $t('maintenance.dialog.notes_label') }}</label>
                    <textarea id="task-description" v-model="form.description" rows="2" class="field" data-test="maintenance-task-description" />
                    <InputError :message="form.errors.description" />
                </div>

                <!-- Schedule behaviour first; the cadence fields below adapt. -->
                <div class="form-row">
                    <label for="task-schedule-type">{{ $t('maintenance.dialog.schedule_type_label') }}</label>
                    <select id="task-schedule-type" v-model="form.schedule_type" class="field" data-test="maintenance-task-type">
                        <option value="interval">{{ $t('enums.maintenance_schedule_type.interval') }}</option>
                        <option value="calendar">{{ $t('enums.maintenance_schedule_type.calendar') }}</option>
                        <option value="one_off">{{ $t('enums.maintenance_schedule_type.one_off') }}</option>
                    </select>
                    <p class="hint">{{ $t(`maintenance.dialog.type_hint.${form.schedule_type}`) }}</p>
                </div>

                <!-- interval: value + unit -->
                <div v-if="form.schedule_type === 'interval'" class="form-row">
                    <label for="task-interval-value">{{ $t('maintenance.dialog.interval_label') }}</label>
                    <div class="flex gap-2">
                        <input
                            id="task-interval-value"
                            v-model.number="form.interval_value"
                            type="number"
                            min="1"
                            max="999"
                            class="field"
                            style="max-width: 90px"
                            data-test="maintenance-task-interval-value"
                        />
                        <select
                            v-model="form.interval_unit"
                            class="field"
                            style="flex: 1"
                            data-test="maintenance-task-interval-unit"
                            :aria-label="$t('maintenance.dialog.interval_label')"
                        >
                            <option v-for="unit in units" :key="unit" :value="unit">{{ $t(`enums.maintenance_interval_unit.${unit}`) }}</option>
                        </select>
                    </div>
                    <InputError :message="form.errors.interval_value || form.errors.interval_unit" />
                </div>

                <!-- calendar: preset builder (or the custom-rule note) -->
                <template v-else-if="form.schedule_type === 'calendar'">
                    <p v-if="isCustomRule" class="hint" data-test="maintenance-custom-rule-note">
                        {{ $t('maintenance.dialog.custom_rule_note', { summary: task?.schedule_summary ?? '' }) }}
                    </p>
                    <template v-else>
                        <div class="form-row">
                            <label for="task-preset">{{ $t('maintenance.dialog.preset_label') }}</label>
                            <select id="task-preset" v-model="form.preset" class="field" data-test="maintenance-task-preset">
                                <option value="every">{{ $t('maintenance.dialog.preset_every') }}</option>
                                <option value="yearly_on">{{ $t('maintenance.dialog.preset_yearly_on') }}</option>
                                <option value="nth_weekday">{{ $t('maintenance.dialog.preset_nth_weekday') }}</option>
                            </select>
                        </div>

                        <div v-if="form.preset === 'every'" class="form-row">
                            <label for="task-preset-interval">{{ $t('maintenance.dialog.interval_label') }}</label>
                            <div class="flex gap-2">
                                <input
                                    id="task-preset-interval"
                                    v-model.number="form.preset_interval"
                                    type="number"
                                    min="1"
                                    max="999"
                                    class="field"
                                    style="max-width: 90px"
                                    data-test="maintenance-task-preset-interval"
                                />
                                <select
                                    v-model="form.preset_unit"
                                    class="field"
                                    style="flex: 1"
                                    :aria-label="$t('maintenance.dialog.interval_label')"
                                >
                                    <option v-for="unit in units" :key="unit" :value="unit">
                                        {{ $t(`enums.maintenance_interval_unit.${unit}`) }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        <div v-else-if="form.preset === 'yearly_on'" class="flex gap-2">
                            <div class="form-row" style="flex: 1">
                                <label for="task-preset-month">{{ $t('maintenance.dialog.month_label') }}</label>
                                <select
                                    id="task-preset-month"
                                    v-model.number="form.preset_month"
                                    class="field"
                                    data-test="maintenance-task-preset-month"
                                >
                                    <option v-for="(name, i) in monthNames" :key="i" :value="i + 1">{{ name }}</option>
                                </select>
                            </div>
                            <div class="form-row" style="max-width: 90px">
                                <label for="task-preset-day">{{ $t('maintenance.dialog.day_label') }}</label>
                                <input
                                    id="task-preset-day"
                                    v-model.number="form.preset_day"
                                    type="number"
                                    min="1"
                                    max="31"
                                    class="field"
                                    data-test="maintenance-task-preset-day"
                                />
                            </div>
                        </div>

                        <template v-else>
                            <div class="flex gap-2">
                                <div class="form-row" style="flex: 1">
                                    <label for="task-preset-ordinal">{{ $t('maintenance.dialog.ordinal_label') }}</label>
                                    <select
                                        id="task-preset-ordinal"
                                        v-model.number="form.preset_ordinal"
                                        class="field"
                                        data-test="maintenance-task-preset-ordinal"
                                    >
                                        <option v-for="ordinal in ordinals" :key="ordinal" :value="ordinal">
                                            {{ $t(`maintenance.schedule.ordinals.${ordinal}`) }}
                                        </option>
                                    </select>
                                </div>
                                <div class="form-row" style="flex: 1">
                                    <label for="task-preset-weekday">{{ $t('maintenance.dialog.weekday_label') }}</label>
                                    <select
                                        id="task-preset-weekday"
                                        v-model="form.preset_weekday"
                                        class="field"
                                        data-test="maintenance-task-preset-weekday"
                                    >
                                        <option v-for="day in weekdays" :key="day" :value="day">
                                            {{ $t(`maintenance.schedule.weekdays.${day}`) }}
                                        </option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-row">
                                <label for="task-preset-in-month">{{ $t('maintenance.dialog.month_label') }}</label>
                                <select
                                    id="task-preset-in-month"
                                    v-model="form.preset_in_month"
                                    class="field"
                                    data-test="maintenance-task-preset-in-month"
                                >
                                    <option :value="null">{{ $t('maintenance.dialog.every_month_option') }}</option>
                                    <option v-for="(name, i) in monthNames" :key="i" :value="i + 1">{{ name }}</option>
                                </select>
                            </div>
                        </template>
                    </template>
                    <InputError :message="presetError" />
                </template>

                <!-- one-off: a single due date -->
                <div v-else class="form-row">
                    <label for="task-due-date">{{ $t('maintenance.dialog.due_date_label') }}</label>
                    <input id="task-due-date" v-model="form.next_due_at" type="date" class="field" data-test="maintenance-task-due-date" />
                    <InputError :message="form.errors.next_due_at" />
                </div>

                <div class="form-row">
                    <label for="task-lead-days">{{ $t('maintenance.dialog.lead_label') }}</label>
                    <input
                        id="task-lead-days"
                        v-model.number="form.reminder_lead_days"
                        type="number"
                        min="0"
                        max="365"
                        class="field"
                        style="max-width: 90px"
                        data-test="maintenance-task-lead-days"
                    />
                    <InputError :message="form.errors.reminder_lead_days" />
                </div>

                <DialogFooter>
                    <DialogClose as-child>
                        <button type="button" class="btn-ghost">{{ $t('common.cancel') }}</button>
                    </DialogClose>
                    <button type="submit" class="btn-primary" :disabled="form.processing || !canSubmit" data-test="maintenance-task-submit">
                        {{ task ? $t('maintenance.dialog.submit_save') : $t('maintenance.dialog.submit_create') }}
                    </button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
