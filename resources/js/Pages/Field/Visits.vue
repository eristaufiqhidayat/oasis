<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Badge from '@/Components/ui/atoms/Badge.vue';
import FilterBar from '@/Components/ui/molecules/FilterBar.vue';
import PageHeader from '@/Components/ui/molecules/PageHeader.vue';
import DataTable from '@/Components/ui/organisms/DataTable.vue';
import { rupiah } from '@/Support/format.js';

defineProps({
    filters: { type: Object, required: true },
    depots: { type: Array, default: () => [] },
    salesmen: { type: Array, default: () => [] },
    outcomes: { type: Array, default: () => [] },
    radius: { type: Number, default: 150 },
    visits: { type: Object, required: true },
});

const appName = usePage().props.appName;
</script>

<template>
    <Head :title="`Kunjungan · ${appName}`" />
    <AppLayout>
        <PageHeader title="Kunjungan" :subtitle="`Check-in motoris di outlet. Jarak lebih dari ${radius} m dari titik outlet ditandai merah.`" />
        <DataTable :columns="{ start: 'Masuk', employee: 'Motoris', outlet: 'Outlet', duration: 'Durasi', distance: 'Jarak', outcome: 'Hasil', total: 'Penjualan' }" :rows="visits.data" :total="visits.total" :links="visits.links" :numeric="['distance', 'total']" row-key="id">
            <template #header>
                <FilterBar url="/kunjungan" :fields="[
                    { name: 'tanggal', label: 'Tanggal', type: 'date' },
                    { name: 'depot', label: 'Depo', type: 'select', options: depots },
                    { name: 'motoris', label: 'Motoris', type: 'combobox', options: salesmen },
                    { name: 'hasil', label: 'Hasil', type: 'select', options: outcomes },
                ]" :values="filters" />
            </template>
            <template #cell-start="{ row }">{{ row.start }}<span v-if="row.end" class="text-gray-500">–{{ row.end }}</span></template>
            <template #cell-outlet="{ row }">{{ row.outlet }} <small class="text-gray-500">({{ row.outlet_code }})</small></template>
            <template #cell-duration="{ row }">{{ row.duration === null ? '-' : `${row.duration} mnt` }}</template>
            <template #cell-distance="{ row }"><span :class="{ 'text-variance-minus': row.far }">{{ row.distance === null ? '-' : `${row.distance} m` }}</span></template>
            <template #cell-outcome="{ row }"><Badge :variant="row.outcome.variant">{{ row.outcome.label }}</Badge> <small v-if="row.reason" class="text-gray-500">{{ row.reason }}</small></template>
            <template #cell-total="{ row }">{{ row.total ? rupiah(row.total) : '-' }}</template>
        </DataTable>
    </AppLayout>
</template>
