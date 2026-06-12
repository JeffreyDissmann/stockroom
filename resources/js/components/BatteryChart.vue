<script setup lang="ts">
import { trans } from '@/composables/useTranslations';
import type { BatteryCycleRow, BatterySummary } from '@/types';
import {
    CategoryScale,
    Chart as ChartJS,
    Legend,
    LinearScale,
    LineElement,
    PointElement,
    Tooltip,
    type ChartData,
    type ChartOptions,
} from 'chart.js';
import { computed } from 'vue';
import { Line } from 'vue-chartjs';

ChartJS.register(LineElement, PointElement, LinearScale, CategoryScale, Tooltip, Legend);

const props = defineProps<{
    cycles: BatteryCycleRow[];
    summary: BatterySummary;
}>();

const ms = (iso: string): number => new Date(iso).getTime();

// One line per physical battery (current highlighted, past muted), drawn on a
// shared absolute-time axis so you can compare this battery to its
// predecessors. A dashed line extends the current battery to its predicted
// empty date.
const chartData = computed<ChartData<'line'>>(() => {
    const ordered = [...props.cycles].sort((a, b) => ms(a.installed_at) - ms(b.installed_at));

    const datasets = ordered
        .filter((cycle) => cycle.readings.length > 0)
        .map((cycle, index) => {
            const label = cycle.is_current
                ? trans('items.battery.chart.current')
                : trans('items.battery.chart.previous', { n: ordered.length - index });

            return {
                label,
                data: cycle.readings.map((r) => ({ x: ms(r.recorded_at), y: r.percent })),
                borderColor: cycle.is_current ? '#22c55e' : '#94a3b8',
                backgroundColor: cycle.is_current ? '#22c55e' : '#94a3b8',
                borderWidth: cycle.is_current ? 2 : 1.5,
                borderDash: cycle.is_current ? [] : [4, 3],
                pointRadius: 2,
                tension: 0.2,
            };
        });

    const projection = props.summary.projection;
    const current = ordered.find((c) => c.is_current);
    const lastReading = current?.readings.at(-1);

    if (projection && lastReading) {
        datasets.push({
            label: trans('items.battery.chart.forecast'),
            data: [
                { x: ms(lastReading.recorded_at), y: lastReading.percent },
                { x: ms(projection.predicted_empty_at), y: 0 },
            ],
            borderColor: '#ef4444',
            backgroundColor: '#ef4444',
            borderWidth: 1.5,
            borderDash: [2, 2],
            pointRadius: 0,
            tension: 0,
        });
    }

    return { datasets } as ChartData<'line'>;
});

const dateFmt = new Intl.DateTimeFormat(undefined, { month: 'short', day: 'numeric' });

const chartOptions = computed<ChartOptions<'line'>>(() => ({
    responsive: true,
    maintainAspectRatio: false,
    interaction: { mode: 'nearest', intersect: false },
    scales: {
        x: {
            type: 'linear',
            ticks: {
                callback: (value) => dateFmt.format(new Date(Number(value))),
                maxRotation: 0,
                autoSkip: true,
                color: '#94a3b8',
            },
            grid: { display: false },
        },
        y: {
            min: 0,
            max: 100,
            ticks: { callback: (value) => `${value}%`, color: '#94a3b8' },
            grid: { color: 'rgba(148, 163, 184, 0.15)' },
        },
    },
    plugins: {
        legend: { labels: { color: '#94a3b8', boxWidth: 12 } },
        tooltip: {
            callbacks: {
                title: (items) => dateFmt.format(new Date(Number(items[0]?.parsed.x))),
                label: (item) => `${item.dataset.label}: ${item.parsed.y}%`,
            },
        },
    },
}));
</script>

<template>
    <div style="height: 240px">
        <Line :data="chartData" :options="chartOptions" />
    </div>
</template>
