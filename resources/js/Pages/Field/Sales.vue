<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Badge from '@/Components/ui/atoms/Badge.vue';
import FilterBar from '@/Components/ui/molecules/FilterBar.vue';
import PageHeader from '@/Components/ui/molecules/PageHeader.vue';
import DataTable from '@/Components/ui/organisms/DataTable.vue';
import { angka, rupiah } from '@/Support/format.js';

defineProps({
    filters: { type: Object, required: true },
    depots: { type: Array, default: () => [] },
    salesmen: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => [] },
    totals: { type: Object, required: true },
    sales: { type: Object, required: true },
});

const appName = usePage().props.appName;
</script>

<template>
    <Head :title="`Penjualan · ${appName}`" />
    <AppLayout>
        <PageHeader title="Penjualan" :subtitle="`${angka(totals.count)} transaksi selesai · ${rupiah(totals.total)}`" />
        <DataTable :columns="{ number: 'Nomor', time: 'Jam', outlet: 'Outlet', employee: 'Motoris', payment: 'Bayar', total: 'Total', status: 'Status' }" :rows="sales.data" :total="sales.total" :links="sales.links" :numeric="['total']" row-key="id">
            <template #header>
                <FilterBar url="/penjualan" :fields="[
                    { name: 'tanggal', label: 'Tanggal', type: 'date' },
                    { name: 'depot', label: 'Depo', type: 'select', options: depots },
                    { name: 'motoris', label: 'Motoris', type: 'combobox', options: salesmen },
                    { name: 'status', label: 'Status', type: 'select', options: statuses },
                    { name: 'q', label: 'Cari nomor/outlet', type: 'search' },
                ]" :values="filters" />
            </template>
            <template #cell-number="{ row }"><Link class="link-strong" :href="`/penjualan/${row.id}`">{{ row.number }}</Link></template>
            <template #cell-total="{ row }">{{ rupiah(row.total) }}</template>
            <template #cell-status="{ row }"><Badge :variant="row.status.variant">{{ row.status.label }}</Badge></template>
        </DataTable>
    </AppLayout>
</template>
