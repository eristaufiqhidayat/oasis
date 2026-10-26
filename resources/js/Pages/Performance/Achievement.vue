<script setup>
import { computed, ref } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Badge from '@/Components/ui/atoms/Badge.vue';
import FilterBar from '@/Components/ui/molecules/FilterBar.vue';
import PageHeader from '@/Components/ui/molecules/PageHeader.vue';
import Tabs from '@/Components/ui/molecules/Tabs.vue';
import DataTable from '@/Components/ui/organisms/DataTable.vue';
import { angka, persen, rupiah, varianCapaian } from '@/Support/format.js';

const props = defineProps({
    filters: { type: Object, required: true },
    tabs: { type: Array, default: () => [] },
    depots: { type: Array, default: () => [] },
    rows: { type: Array, default: () => [] },
    elapsed: { type: Number, required: true },
    daysInMonth: { type: Number, required: true },
    total: { type: Object, default: null },
});

const appName = usePage().props.appName;
const tab = ref(props.filters.tab);
const daftarTab = props.tabs.map((t) => ({ key: t.value, label: t.label }));
const kolom = computed(() => (props.filters.tab === 'product'
    ? { sku: 'SKU', name: 'Produk', quantity: 'Terjual', target: 'Target', percent: 'Capaian' }
    : { name: props.filters.tab === 'depot' ? 'Depo' : 'Motoris', ...(props.filters.tab === 'salesman' ? { depot: 'Depo' } : {}), revenue: 'Penjualan', target: 'Target', percent: 'Capaian', projection: 'Proyeksi akhir bulan', ec: 'EC', ec_percent: 'Capaian EC' }));
const barKelas = (p) => `progress-bar progress-bar-${varianCapaian(p) === 'light' ? 'warning' : varianCapaian(p)}`;

function pindah(t) {
    router.get('/pencapaian', { bulan: props.filters.bulan, tab: t, depot: props.filters.depot ?? undefined });
}
</script>

<template>
    <Head :title="`Pencapaian · ${appName}`" />
    <AppLayout>
        <PageHeader title="Pencapaian Target" :subtitle="`Realisasi penjualan selesai (tanpa transaksi batal). Hari berjalan ${elapsed} dari ${daysInMonth}; proyeksi = rata-rata harian × jumlah hari.`" />

        <Tabs v-model="tab" :tabs="daftarTab" @update:model-value="pindah" />

        <DataTable :columns="kolom" :rows="rows" :total="rows.length" :show-footer="false" :numeric="['revenue', 'target', 'projection', 'ec', 'quantity']" row-key="id">
            <template #header>
                <FilterBar url="/pencapaian" :keep="{ tab: filters.tab }" :fields="[
                    { name: 'bulan', label: 'Bulan', type: 'month' },
                    ...(filters.tab === 'depot' ? [] : [{ name: 'depot', label: 'Depo', type: 'select', options: depots }]),
                ]" :values="filters" />
            </template>
            <template #cell-revenue="{ row }">{{ rupiah(row.revenue) }}</template>
            <template #cell-quantity="{ row }">{{ angka(row.quantity) }}</template>
            <template #cell-target="{ row }">{{ row.target ? (filters.tab === 'product' ? angka(row.target) : rupiah(row.target)) : '-' }}</template>
            <template #cell-percent="{ row }">
                <div class="w-28">
                    <Badge :variant="varianCapaian(row.percent)">{{ row.percent === null ? 'Tanpa target' : persen(row.percent) }}</Badge>
                    <div v-if="row.percent !== null" class="progress mt-1"><div :class="barKelas(row.percent)" :style="{ width: `${Math.min(100, row.percent)}%` }"></div></div>
                </div>
            </template>
            <template #cell-projection="{ row }">{{ rupiah(row.projection) }} <small v-if="row.projection_percent !== null" class="text-gray-500">({{ persen(row.projection_percent) }})</small></template>
            <template #cell-ec="{ row }">{{ angka(row.ec) }} <small class="text-gray-500">/ {{ row.ec_target ?? '-' }}</small></template>
            <template #cell-ec_percent="{ row }"><Badge :variant="varianCapaian(row.ec_percent)">{{ persen(row.ec_percent) }}</Badge></template>
            <template v-if="total" #tfoot>
                <tr class="font-semibold">
                    <td :colspan="filters.tab === 'salesman' ? 2 : 1">Total</td>
                    <td class="text-end">{{ rupiah(total.revenue) }}</td>
                    <td class="text-end">{{ rupiah(total.target) }}</td>
                    <td>{{ persen(total.percent) }}</td>
                    <td></td>
                    <td class="text-end">{{ angka(total.ec) }} / {{ angka(total.ec_target) }}</td>
                    <td></td>
                </tr>
            </template>
        </DataTable>
    </AppLayout>
</template>
