<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Badge from '@/Components/ui/atoms/Badge.vue';
import FilterBar from '@/Components/ui/molecules/FilterBar.vue';
import PageHeader from '@/Components/ui/molecules/PageHeader.vue';
import DataTable from '@/Components/ui/organisms/DataTable.vue';
import { persen, varianCapaian } from '@/Support/format.js';

defineProps({
    filters: { type: Object, required: true },
    depots: { type: Array, default: () => [] },
    radius: { type: Number, default: 150 },
    rows: { type: Array, default: () => [] },
});

const appName = usePage().props.appName;
</script>

<template>
    <Head :title="`Kinerja Kunjungan · ${appName}`" />
    <AppLayout>
        <PageHeader title="Kinerja Kunjungan" :subtitle="`Rencana = outlet di rute aktif. Strike rate = kunjungan berhasil jual ÷ kunjungan. Kunjungan jauh = check-in lebih dari ${radius} m dari outlet.`" />
        <DataTable :columns="{ name: 'Motoris', planned: 'Rencana', visits: 'Kunjungan', compliance: 'Kepatuhan rute', outlets: 'Outlet unik', effective: 'Effective call', strike_rate: 'Strike rate', far: 'Kunjungan jauh' }" :rows="rows" :total="rows.length" :show-footer="false" :numeric="['planned', 'visits', 'outlets', 'effective', 'far']" row-key="id">
            <template #header>
                <FilterBar url="/laporan/kunjungan" :fields="[{ name: 'dari', label: 'Dari', type: 'date' }, { name: 'sampai', label: 'Sampai', type: 'date' }, { name: 'depot', label: 'Depo', type: 'select', options: depots, placeholder: '— Pilih —' }]" :values="filters" />
            </template>
            <template #cell-compliance="{ row }"><Badge :variant="varianCapaian(row.compliance)">{{ persen(row.compliance) }}</Badge></template>
            <template #cell-strike_rate="{ row }">{{ persen(row.strike_rate) }}</template>
            <template #cell-far="{ row }"><span :class="{ 'text-variance-minus': row.far }">{{ row.far }}</span></template>
        </DataTable>
    </AppLayout>
</template>
