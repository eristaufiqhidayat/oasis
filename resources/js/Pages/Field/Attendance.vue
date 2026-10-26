<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Badge from '@/Components/ui/atoms/Badge.vue';
import FilterBar from '@/Components/ui/molecules/FilterBar.vue';
import PageHeader from '@/Components/ui/molecules/PageHeader.vue';
import DataTable from '@/Components/ui/organisms/DataTable.vue';

defineProps({
    filters: { type: Object, required: true },
    depots: { type: Array, default: () => [] },
    rows: { type: Array, default: () => [] },
    summary: { type: Object, required: true },
});

const appName = usePage().props.appName;
</script>

<template>
    <Head :title="`Absensi · ${appName}`" />
    <AppLayout>
        <PageHeader title="Absensi Motoris" :subtitle="`${summary.present} dari ${summary.total} motoris aktif sudah absen masuk.`" />
        <DataTable :columns="{ code: 'Kode', name: 'Motoris', depot: 'Depo', check_in: 'Masuk', check_out: 'Pulang', status: 'Status' }" :rows="rows" :total="rows.length" :show-footer="false" row-key="id">
            <template #header>
                <FilterBar url="/absensi" :fields="[{ name: 'tanggal', label: 'Tanggal', type: 'date' }, { name: 'depot', label: 'Depo', type: 'select', options: depots }]" :values="filters" />
            </template>
            <template #cell-check_in="{ row }">{{ row.check_in ?? '-' }} <small v-if="row.check_in && !row.has_location" class="text-amber-700">tanpa GPS</small></template>
            <template #cell-check_out="{ row }">{{ row.check_out ?? '-' }}</template>
            <template #cell-status="{ row }"><Badge :variant="row.check_in ? 'success' : 'danger'">{{ row.check_in ? 'Hadir' : 'Belum absen' }}</Badge></template>
        </DataTable>
    </AppLayout>
</template>
