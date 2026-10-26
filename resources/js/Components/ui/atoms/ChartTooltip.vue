<script setup>
// Tooltip grafik: nilai tebal di depan, nama seri di belakang, kunci warna berupa garis pendek.
// Diposisikan di dalam pembungkus grafik (position: relative) lewat useChartTooltip().
defineProps({
    tip: { type: Object, required: true },
});
</script>

<template>
    <div v-show="tip.visible" class="chart-tooltip" role="status" :style="{ left: `${tip.x}px`, top: `${tip.y}px` }">
        <p class="chart-tooltip-title">{{ tip.title }}</p>
        <p v-for="row in tip.rows" :key="row.label" class="chart-tooltip-row">
            <span v-if="row.color" class="chart-key-line" :style="{ background: row.color }"></span>
            <strong>{{ row.value }}</strong>
            <span class="text-gray-500">{{ row.label }}</span>
        </p>
    </div>
</template>
