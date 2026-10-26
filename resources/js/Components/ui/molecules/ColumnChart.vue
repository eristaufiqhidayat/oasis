<script setup>
// Grafik kolom satu seri (mis. omzet per hari). items: [{ key, label, value, detail? }].
// highlightKey menandai satu kolom (warna aksen), average menggambar garis rata-rata.
import { computed } from 'vue';
import ChartTooltip from '@/Components/ui/atoms/ChartTooltip.vue';
import { useChartTooltip, useElementWidth } from '@/Composables/useChartTooltip.js';
import { pathKolom, tickBulat } from '@/Support/chart.js';

const props = defineProps({
    items: { type: Array, required: true },
    format: { type: Function, default: (v) => String(v) },
    axisFormat: { type: Function, default: null },
    seriesLabel: { type: String, default: 'Nilai' },
    highlightKey: { type: [String, Number], default: null },
    highlightLabel: { type: String, default: 'Disorot' },
    average: { type: Boolean, default: false },
    height: { type: Number, default: 220 },
    // Tampilkan label sumbu-x setiap n kolom (otomatis bila null).
    labelEvery: { type: Number, default: null },
});

const { el, width } = useElementWidth();
const { box, tip, show, hide } = useChartTooltip();

const pad = { top: 16, right: 8, bottom: 28, left: 64 };
const plotW = computed(() => Math.max(1, width.value - pad.left - pad.right));
const plotH = computed(() => props.height - pad.top - pad.bottom);
const skala = computed(() => tickBulat(Math.max(0, ...props.items.map((i) => i.value))));
const y = (v) => pad.top + plotH.value - (v / skala.value.maks) * plotH.value;
const slot = computed(() => plotW.value / Math.max(1, props.items.length));
const lebarBatang = computed(() => Math.max(4, Math.min(24, slot.value * 0.6)));
const rata = computed(() => (props.items.length ? props.items.reduce((a, i) => a + i.value, 0) / props.items.length : 0));
const setiap = computed(() => props.labelEvery ?? Math.max(1, Math.ceil(props.items.length / Math.max(1, Math.floor(plotW.value / 56)))));
const fmtSumbu = computed(() => props.axisFormat ?? props.format);

const kolom = computed(() => props.items.map((item, i) => {
    const x = pad.left + slot.value * i + (slot.value - lebarBatang.value) / 2;
    const top = y(item.value);

    return {
        ...item,
        x,
        tengah: pad.left + slot.value * i + slot.value / 2,
        path: pathKolom(x, top, lebarBatang.value, pad.top + plotH.value - top),
        // Hitung dari kolom terakhir supaya kolom terbaru selalu berlabel dan label tidak bertumpuk di ujung.
        tampilLabel: (props.items.length - 1 - i) % setiap.value === 0,
    };
}));

function tampilkan(event, item) {
    show(event, item.label, [
        { label: props.seriesLabel, value: props.format(item.value) },
        ...(item.detail ?? []),
    ]);
}
</script>

<template>
    <div ref="box" class="chart-box">
        <div ref="el">
            <svg :width="width" :height="height" role="img" :aria-label="`Grafik ${seriesLabel}`" class="block">
                <g class="chart-grid">
                    <line v-for="t in skala.ticks" :key="t" :x1="pad.left" :x2="width - pad.right" :y1="y(t)" :y2="y(t)" />
                </g>
                <g class="chart-axis-text" text-anchor="end">
                    <text v-for="t in skala.ticks" :key="t" :x="pad.left - 8" :y="y(t)" dy="0.32em">{{ fmtSumbu(t) }}</text>
                </g>
                <g>
                    <path
                        v-for="k in kolom"
                        :key="k.key"
                        :d="k.path"
                        class="chart-column"
                        :class="{ 'chart-column-accent': k.key === highlightKey }"
                    />
                </g>
                <line v-if="average && rata > 0" class="chart-average" :x1="pad.left" :x2="width - pad.right" :y1="y(rata)" :y2="y(rata)" />
                <g class="chart-axis-text" text-anchor="middle">
                    <template v-for="k in kolom" :key="k.key">
                        <text v-if="k.tampilLabel" :x="k.tengah" :y="height - 8">{{ k.short ?? k.label }}</text>
                    </template>
                </g>
                <!-- Area sentuh selebar slot (lebih besar dari batangnya). -->
                <g>
                    <rect
                        v-for="(k, i) in kolom"
                        :key="k.key"
                        :x="pad.left + slot * i"
                        :y="pad.top"
                        :width="slot"
                        :height="plotH"
                        class="chart-hit"
                        tabindex="0"
                        :aria-label="`${k.label}: ${format(k.value)}`"
                        @pointermove="tampilkan($event, k)"
                        @focus="tampilkan($event, k)"
                        @pointerleave="hide"
                        @blur="hide"
                    />
                </g>
            </svg>
        </div>
        <ul v-if="average && rata > 0" class="chart-legend">
            <li><span class="chart-key-swatch" style="background: var(--chart-single)"></span>{{ seriesLabel }}</li>
            <li v-if="highlightKey !== null && items.some((i) => i.key === highlightKey)"><span class="chart-key-swatch" style="background: var(--chart-accent)"></span>{{ highlightLabel }}</li>
            <li><span class="chart-key-line chart-key-average"></span>Rata-rata {{ fmtSumbu(Math.round(rata)) }}</li>
        </ul>
        <ChartTooltip :tip="tip" />
    </div>
</template>
