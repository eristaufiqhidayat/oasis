<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import FilterBar from '@/Components/ui/molecules/FilterBar.vue';
import PageHeader from '@/Components/ui/molecules/PageHeader.vue';
import DataTable from '@/Components/ui/organisms/DataTable.vue';
import { angka } from '@/Support/format.js';

defineProps({
    filters: { type: Object, required: true },
    depots: { type: Array, default: () => [] },
    rows: { type: Object, required: true },
    totalBalance: { type: Number, default: 0 },
});

const appName = usePage().props.appName;
</script>

<template>
    <Head :title="`Saldo Galon Outlet · ${appName}`" />
    <AppLayout>
        <PageHeader title="Saldo Galon Outlet" :subtitle="`Galon isi terkirim dikurangi galon kosong yang ditarik. Total galon di outlet depo ini: ${angka(totalBalance)}.`" />
        <DataTable :columns="{ code: 'Kode', name: 'Outlet', delivered: 'Galon isi terkirim', collected: 'Kosong ditarik', balance: 'Saldo di outlet', last_at: 'Terakhir' }" :rows="rows.data" :total="rows.total" :links="rows.links" :numeric="['delivered', 'collected', 'balance']" row-key="id">
            <template #header>
                <FilterBar url="/laporan/galon" :fields="[{ name: 'depot', label: 'Depo', type: 'select', options: depots, placeholder: '— Pilih —' }, { name: 'q', label: 'Cari outlet', type: 'search' }]" :values="filters" />
            </template>
            <template #cell-balance="{ row }"><strong :class="{ 'text-variance-minus': row.balance < 0 }">{{ angka(row.balance) }}</strong></template>
        </DataTable>
    </AppLayout>
</template>
