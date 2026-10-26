<script setup>
// Daftar batang horizontal satu seri (peringkat wilayah, motoris, dst.). items: [{ key, label, sub?, value }].
// Nilai ditulis di ujung batang; batang memakai satu warna karena kategorinya tidak berurutan.
import { computed } from 'vue';

const props = defineProps({
    items: { type: Array, required: true },
    format: { type: Function, default: (v) => String(v) },
    emptyText: { type: String, default: 'Belum ada data.' },
});

const maks = computed(() => Math.max(1, ...props.items.map((i) => i.value)));
</script>

<template>
    <p v-if="!items.length" class="chart-empty">{{ emptyText }}</p>
    <ol v-else class="bar-list">
        <li v-for="(item, i) in items" :key="item.key ?? item.label" class="bar-list-row" :title="`${item.label}: ${format(item.value)}`">
            <span class="bar-list-rank">{{ i + 1 }}</span>
            <span class="bar-list-text">
                <span class="bar-list-label">{{ item.label }}<small v-if="item.sub" class="text-gray-500"> · {{ item.sub }}</small></span>
                <span class="bar-list-track">
                    <span class="bar-list-bar" :style="{ width: `${Math.max(1, (item.value / maks) * 100)}%` }"></span>
                    <span class="bar-list-value">{{ format(item.value) }}</span>
                </span>
            </span>
        </li>
    </ol>
</template>
