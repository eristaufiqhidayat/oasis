<script setup>
// Kartu grafik: judul, ringkasan opsional, isi grafik (slot), dan tombol "Tabel" untuk melihat angka yang sama
// sebagai tabel (setiap nilai tetap bisa dibaca tanpa hover/warna).
import { ref } from 'vue';
import Card from '@/Components/ui/molecules/Card.vue';
import Icon from '@/Components/ui/atoms/Icon.vue';

defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: null },
    // Tampilan tabel: columns { kunci: judul }, rows [{ kunci: teks }], numeric [kunci rata kanan].
    columns: { type: Object, default: null },
    rows: { type: Array, default: () => [] },
    numeric: { type: Array, default: () => [] },
});

const tabel = ref(false);
</script>

<template>
    <Card class="chart-card">
        <template #header>
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <h6 class="mb-0 font-semibold">{{ title }}</h6>
                    <p v-if="subtitle" class="mb-0 text-xs text-gray-500">{{ subtitle }}</p>
                </div>
                <button
                    v-if="columns"
                    type="button"
                    class="chart-view-toggle"
                    :aria-pressed="tabel ? 'true' : 'false'"
                    :title="tabel ? 'Tampilkan grafik' : 'Tampilkan tabel'"
                    @click="tabel = !tabel"
                >
                    <Icon :name="tabel ? 'chart-bar' : 'clipboard-list'" />
                    <span>{{ tabel ? 'Grafik' : 'Tabel' }}</span>
                </button>
            </div>
        </template>

        <slot name="summary" />

        <div v-if="tabel && columns" class="max-h-80 overflow-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th v-for="(label, key) in columns" :key="key" :class="{ 'text-end': numeric.includes(key) }">{{ label }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(row, i) in rows" :key="i">
                        <td v-for="key in Object.keys(columns)" :key="key" :class="{ 'text-end tabular-nums': numeric.includes(key) }">{{ row[key] }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <slot v-else />
    </Card>
</template>
