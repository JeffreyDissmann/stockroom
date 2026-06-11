<script setup lang="ts">
import BatteryChart from '@/components/BatteryChart.vue';
import { useDateFormat } from '@/composables/useDateFormat';
import batteryChanges from '@/routes/items/battery-changes';
import type { BatteryData, ItemSummary } from '@/types';
import { router } from '@inertiajs/vue3';
import { BatteryLow, BatteryMedium, Plus } from 'lucide-vue-next';
import { computed, ref } from 'vue';

/**
 * The battery panel: current level + type, the depletion forecast and the
 * "Replace battery" reminder, plus a chart of this and previous batteries.
 * Renders nothing until the item has battery history (a level reading).
 */
const props = defineProps<{
    item: ItemSummary;
    battery: BatteryData;
}>();

const { formatDate } = useDateFormat();

const summary = computed(() => props.battery.summary);
const hasChart = computed(() => props.battery.cycles.some((c) => c.readings.length > 0));
const changing = ref(false);

function changeBattery() {
    router.post(
        batteryChanges.store(props.item.id).url,
        {},
        {
            preserveScroll: true,
            onStart: () => (changing.value = true),
            onFinish: () => (changing.value = false),
        },
    );
}
</script>

<template>
    <section v-if="summary.tracked" data-test="battery-section">
        <!-- Heading + action live outside the card, matching the Contents,
             Related and Maintenance sections. -->
        <div class="mb-3 flex flex-wrap items-center justify-between gap-x-3 gap-y-2">
            <h3 class="section-label flex items-center gap-2" style="margin: 0">
                {{ $t('items.battery.title') }}
                <span v-if="summary.battery_type" class="battery-type-chip">{{ summary.battery_type }}</span>
            </h3>
            <button type="button" class="btn-pill" data-test="battery-change" :disabled="changing" @click="changeBattery">
                <Plus :size="14" />
                {{ $t('items.battery.change') }}
            </button>
        </div>

        <div class="card card-pad">
            <div class="battery-stats">
                <div class="battery-level" :class="{ 'text-low': summary.is_low }">
                    <span class="battery-level-value">
                        <component :is="summary.is_low ? BatteryLow : BatteryMedium" :size="22" />
                        <span class="battery-percent"
                            >{{ summary.current_percent ?? '—' }}<span v-if="summary.current_percent !== null">%</span></span
                        >
                    </span>
                    <span v-if="summary.last_reading_at" class="battery-sub">
                        {{ $t('items.battery.updated', { date: formatDate(summary.last_reading_at) }) }}
                    </span>
                </div>

                <div class="battery-forecast">
                    <template v-if="summary.reminder?.next_due_at">
                        <span class="battery-sub-label">{{ $t('items.battery.replace_by') }}</span>
                        <span class="battery-due" :class="{ 'text-low': summary.reminder.is_overdue }">
                            {{ formatDate(summary.reminder.next_due_at) }}
                        </span>
                        <span v-if="summary.projection" class="battery-sub">
                            {{ $t('items.battery.confidence', { pct: Math.round(summary.projection.confidence * 100) }) }}
                        </span>
                    </template>
                    <span v-else class="battery-sub">{{ $t('items.battery.no_prediction') }}</span>
                </div>
            </div>

            <BatteryChart v-if="hasChart" :cycles="battery.cycles" :summary="summary" class="mt-5" />
        </div>
    </section>
</template>

<style scoped>
.battery-type-chip {
    font-size: 12px;
    font-weight: 500;
    padding: 2px 8px;
    border-radius: 999px;
    background: var(--bg-muted, rgba(148, 163, 184, 0.15));
    color: var(--fg-muted);
}

.battery-stats {
    display: flex;
    flex-wrap: wrap;
    gap: 32px;
}

.battery-level {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.battery-level-value {
    display: flex;
    align-items: center;
    gap: 8px;
}

.battery-percent {
    font-size: 32px;
    font-weight: 600;
    letter-spacing: -0.02em;
    line-height: 1;
}

.battery-forecast {
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 2px;
}

.battery-sub-label {
    font-size: 12px;
    color: var(--fg-muted);
}

.battery-due {
    font-size: 16px;
    font-weight: 600;
}

.battery-sub {
    font-size: 12px;
    color: var(--fg-muted);
    margin-top: 4px;
}

.text-low {
    color: #ef4444;
}
</style>
