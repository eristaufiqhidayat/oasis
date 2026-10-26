<script setup>
// Donut komposisi (≤ 5 potong) + legenda berisi nilai & persen. segments: [{ label, value, sub? }].
// Warna mengikuti urutan palet tetap; teks tidak pernah memakai warna seri.
import { computed } from 'vue';
import ChartTooltip from '@/Components/ui/atoms/ChartTooltip.vue';
import { useChartTooltip } from '@/Composables/useChartTooltip.js';
import { bagian, potonganDonut, warnaSeri } from '@/Support/chart.js';
import { desimal } from '@/Support/format.js';

const props = defineProps({
    segments: { type: Array, required: true },
    format: { type: Function, default: (v) => String(v) },
    centerLabel: { type: String, default: 'Total' },
    centerValue: { type: String, default: null },
});

const { box, tip, show, hide } = useChartTooltip();
const total = computed(() => props.segments.reduce((a, s) => a + s.value, 0));
const potongan = computed(() => potonganDonut(props.segments.map((s) => s.value)));

function tampilkan(event, i) {
    const s = props.segments[i];
    show(event, s.label, [
        { label: 'Omzet', value: props.format(s.value), color: warnaSeri(i) },
        { label: 'dari total', value: `${desimal(bagian(s.value, total.value))}%` },
        ...(s.sub ? [{ label: '', value: s.sub }] : []),
    ]);
}
</script>

<template>
    <p v-if="!total" class="chart-empty">Belum ada penjualan.</p>
    <div v-else class="donut">
        <div ref="box" class="chart-box donut-figure">
            <svg viewBox="0 0 160 160" width="160" height="160" role="img" :aria-label="`Komposisi, ${centerLabel} ${centerValue ?? format(total)}`">
                <path
                    v-for="p in potongan.filter((x) => x.path)"
                    :key="p.index"
                    :d="p.path"
                    :fill="warnaSeri(p.index)"
                    class="donut-slice"
                    tabindex="0"
                    :aria-label="`${segments[p.index].label}: ${format(segments[p.index].value)}`"
                    @pointermove="tampilkan($event, p.index)"
                    @focus="tampilkan($event, p.index)"
                    @pointerleave="hide"
                    @blur="hide"
                />
                <text x="80" y="74" text-anchor="middle" class="donut-center-label">{{ centerLabel }}</text>
                <text x="80" y="94" text-anchor="middle" class="donut-center-value">{{ centerValue ?? format(total) }}</text>
            </svg>
            <ChartTooltip :tip="tip" />
        </div>
        <ul class="chart-legend chart-legend-stacked">
            <li v-for="(s, i) in segments" :key="s.label">
                <span class="chart-key-swatch" :style="{ background: warnaSeri(i) }"></span>
                <span class="min-w-0 flex-1 truncate">{{ s.label }}</span>
                <strong class="tabular-nums">{{ format(s.value) }}</strong>
                <span class="w-12 text-end tabular-nums text-gray-500">{{ desimal(bagian(s.value, total)) }}%</span>
            </li>
        </ul>
    </div>
</template>
