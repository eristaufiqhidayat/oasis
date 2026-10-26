<script setup>
// Satu batang 100% bertumpuk (bagian dari keseluruhan, mis. hasil kunjungan). segments: [{ label, value }].
// Celah 2px antar potongan, legenda selalu ada dengan nilai & persen.
import { computed } from 'vue';
import { bagian, warnaSeri } from '@/Support/chart.js';
import { desimal } from '@/Support/format.js';

const props = defineProps({
    title: { type: String, default: null },
    segments: { type: Array, required: true },
    format: { type: Function, default: (v) => String(v) },
});

const total = computed(() => props.segments.reduce((a, s) => a + s.value, 0));
</script>

<template>
    <div class="stacked">
        <p v-if="title" class="stacked-title">{{ title }}</p>
        <div v-if="total" class="stacked-bar" role="img" :aria-label="segments.map((s) => `${s.label} ${desimal(bagian(s.value, total))}%`).join(', ')">
            <template v-for="(s, i) in segments" :key="s.label">
                <span
                    v-if="s.value > 0"
                    class="stacked-segment"
                    :style="{ flexGrow: s.value, background: warnaSeri(i) }"
                    :title="`${s.label}: ${format(s.value)} (${desimal(bagian(s.value, total))}%)`"
                ></span>
            </template>
        </div>
        <div v-else class="stacked-bar stacked-bar-empty"></div>
        <ul class="chart-legend">
            <li v-for="(s, i) in segments" :key="s.label">
                <span class="chart-key-swatch" :style="{ background: warnaSeri(i) }"></span>
                {{ s.label }} <strong class="tabular-nums">{{ format(s.value) }}</strong>
                <span class="tabular-nums text-gray-500">{{ desimal(bagian(s.value, total)) }}%</span>
            </li>
        </ul>
    </div>
</template>
