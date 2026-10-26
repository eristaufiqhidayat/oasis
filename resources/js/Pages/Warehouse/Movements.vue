<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Badge from '@/Components/ui/atoms/Badge.vue';
import FilterBar from '@/Components/ui/molecules/FilterBar.vue';
import PageHeader from '@/Components/ui/molecules/PageHeader.vue';
import DataTable from '@/Components/ui/organisms/DataTable.vue';
import { angka } from '@/Support/format.js';

defineProps({
    filters: { type: Object, required: true },
    depots: { type: Array, default: () => [] },
    types: { type: Array, default: () => [] },
    products: { type: Array, default: () => [] },
    movements: { type: Object, required: true },
});

const appName = usePage().props.appName;
</script>

<template>
    <Head :title="`Mutasi Stok · ${appName}`" />
    <AppLayout>
        <PageHeader title="Mutasi Stok" subtitle="Buku besar persediaan: setiap perpindahan barang antar lokasi (— = di luar sistem: pemasok atau pelanggan)." />
        <DataTable :columns="{ at: 'Waktu', type: 'Jenis', product: 'Produk', quantity: 'Qty', from: 'Dari', to: 'Ke', note: 'Catatan', user: 'Oleh' }" :rows="movements.data" :total="movements.total" :links="movements.links" :numeric="['quantity']" row-key="id">
            <template #header>
                <FilterBar url="/mutasi-stok" :fields="[
                    { name: 'depot', label: 'Depo', type: 'select', options: depots, placeholder: '— Pilih —' },
                    { name: 'dari', label: 'Dari tanggal', type: 'date' },
                    { name: 'sampai', label: 'Sampai', type: 'date' },
                    { name: 'produk', label: 'Produk', type: 'combobox', options: products },
                    { name: 'jenis', label: 'Jenis', type: 'select', options: types },
                ]" :values="filters" />
            </template>
            <template #cell-type="{ row }"><Badge variant="light">{{ row.type }}</Badge></template>
            <template #cell-quantity="{ row }">{{ angka(row.quantity) }}</template>
            <template #cell-note="{ row }">{{ row.note ?? '' }}</template>
            <template #cell-user="{ row }">{{ row.user ?? 'HP' }}</template>
        </DataTable>
    </AppLayout>
</template>
