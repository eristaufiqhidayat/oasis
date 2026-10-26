<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Button from '@/Components/ui/atoms/Button.vue';
import FilterBar from '@/Components/ui/molecules/FilterBar.vue';
import PageHeader from '@/Components/ui/molecules/PageHeader.vue';
import StatCard from '@/Components/ui/molecules/StatCard.vue';
import DataTable from '@/Components/ui/organisms/DataTable.vue';
import { angka, rupiah } from '@/Support/format.js';
import { bersihkanQuery } from '@/Support/query.js';

const props = defineProps({
    filters: { type: Object, required: true },
    groups: { type: Array, default: () => [] },
    depots: { type: Array, default: () => [] },
    rows: { type: Array, default: () => [] },
    total: { type: Object, required: true },
    canExport: { type: Boolean, default: false },
});

const appName = usePage().props.appName;
const unduh = `/laporan/penjualan/unduh?${new URLSearchParams(bersihkanQuery(props.filters)).toString()}`;
const judulKolom = props.groups.find((g) => g.value === props.filters.grup)?.label.replace('Per ', '') ?? '';
</script>

<template>
    <Head :title="`Laporan Penjualan · ${appName}`" />
    <AppLayout>
        <PageHeader title="Laporan Penjualan" subtitle="Rekap penjualan selesai per hari, produk, motoris, outlet, atau depo. Unduh CSV untuk diolah di Excel.">
            <template v-if="canExport" #actions><Button tag="a" :href="unduh" variant="outline-primary">Unduh CSV</Button></template>
        </PageHeader>

        <div class="stat-grid mb-3">
            <StatCard label="Nilai penjualan" :value="rupiah(total.revenue)" icon="banknote" variant="success" />
            <StatCard label="Qty terjual" :value="angka(total.quantity)" icon="boxes" />
        </div>

        <DataTable :columns="{ label: judulKolom, code: 'Kode', transactions: 'Transaksi', quantity: 'Qty', revenue: 'Nilai' }" :rows="rows" :total="rows.length" :show-footer="false" :numeric="['transactions', 'quantity', 'revenue']">
            <template #header>
                <FilterBar url="/laporan/penjualan" :fields="[
                    { name: 'grup', label: 'Kelompokkan', type: 'select', options: groups, placeholder: 'Per hari' },
                    { name: 'dari', label: 'Dari', type: 'date' },
                    { name: 'sampai', label: 'Sampai', type: 'date' },
                    { name: 'depot', label: 'Depo', type: 'select', options: depots },
                ]" :values="filters" />
            </template>
            <template #cell-code="{ row }">{{ row.code || '-' }}</template>
            <template #cell-transactions="{ row }">{{ angka(row.transactions) }}</template>
            <template #cell-quantity="{ row }">{{ angka(row.quantity) }}</template>
            <template #cell-revenue="{ row }">{{ rupiah(row.revenue) }}</template>
            <template #tfoot>
                <tr class="font-semibold"><td colspan="3">Total</td><td class="text-end">{{ angka(total.quantity) }}</td><td class="text-end">{{ rupiah(total.revenue) }}</td></tr>
            </template>
        </DataTable>
    </AppLayout>
</template>
