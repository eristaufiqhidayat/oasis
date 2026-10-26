<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Badge from '@/Components/ui/atoms/Badge.vue';
import FilterBar from '@/Components/ui/molecules/FilterBar.vue';
import PageHeader from '@/Components/ui/molecules/PageHeader.vue';
import DataTable from '@/Components/ui/organisms/DataTable.vue';

defineProps({
    filters: { type: Object, required: true },
    depots: { type: Array, default: () => [] },
    returns: { type: Object, required: true },
});

const appName = usePage().props.appName;
</script>

<template>
    <Head :title="`Retur Outlet · ${appName}`" />
    <AppLayout>
        <PageHeader title="Retur Outlet" subtitle="Barang rusak/kedaluwarsa yang ditarik motoris dari outlet. Barang ikut di kendaraan dan dihitung gudang saat bongkar." />
        <DataTable :columns="{ number: 'Nomor', time: 'Jam', outlet: 'Outlet', employee: 'Motoris', lines: 'Barang', trip: 'Muatan' }" :rows="returns.data" :total="returns.total" :links="returns.links" row-key="id">
            <template #header>
                <FilterBar url="/retur" :fields="[{ name: 'tanggal', label: 'Tanggal', type: 'date' }, { name: 'depot', label: 'Depo', type: 'select', options: depots }]" :values="filters" />
            </template>
            <template #cell-lines="{ row }">
                <span v-for="(l, i) in row.lines" :key="i" class="block">{{ l.name }} × {{ l.quantity }} <Badge variant="light">{{ l.reason }}</Badge></span>
                <small v-if="row.note" class="text-gray-500">{{ row.note }}</small>
            </template>
            <template #cell-trip="{ row }"><Link class="link-strong" :href="`/muatan/${row.trip.id}`">{{ row.trip.number }}</Link></template>
        </DataTable>
    </AppLayout>
</template>
