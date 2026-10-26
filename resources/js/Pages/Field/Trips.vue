<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Badge from '@/Components/ui/atoms/Badge.vue';
import Button from '@/Components/ui/atoms/Button.vue';
import Alert from '@/Components/ui/molecules/Alert.vue';
import FilterBar from '@/Components/ui/molecules/FilterBar.vue';
import PageHeader from '@/Components/ui/molecules/PageHeader.vue';
import DataTable from '@/Components/ui/organisms/DataTable.vue';
import { angka } from '@/Support/format.js';

defineProps({
    filters: { type: Object, required: true },
    depots: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => [] },
    trips: { type: Array, default: () => [] },
    withoutTrip: { type: Array, default: () => [] },
    canLoad: { type: Boolean, default: false },
});

const appName = usePage().props.appName;
</script>

<template>
    <Head :title="`Muatan Harian · ${appName}`" />
    <AppLayout>
        <PageHeader title="Muatan Harian" subtitle="Barang yang dibawa tiap motoris. Alur: dimuat gudang → berangkat (HP) → kembali → dibongkar gudang → setoran & rekonsiliasi → selesai.">
            <template v-if="canLoad" #actions><Button :tag="Link" href="/muatan/baru">Buat muatan</Button></template>
        </PageHeader>

        <Alert v-if="withoutTrip.length" variant="info" class="mb-3">Belum punya muatan pada tanggal ini: {{ withoutTrip.join(', ') }}.</Alert>

        <DataTable :columns="{ number: 'Nomor', employee: 'Motoris', depot: 'Depo', loaded_qty: 'Qty dimuat', status: 'Status' }" :rows="trips" :total="trips.length" :show-footer="false" :numeric="['loaded_qty']" row-key="id" empty-text="Belum ada muatan.">
            <template #header>
                <FilterBar url="/muatan" :fields="[{ name: 'tanggal', label: 'Tanggal', type: 'date' }, { name: 'depot', label: 'Depo', type: 'select', options: depots }, { name: 'status', label: 'Status', type: 'select', options: statuses }]" :values="filters" />
            </template>
            <template #cell-number="{ row }"><Link class="link-strong" :href="`/muatan/${row.id}`">{{ row.number }}</Link></template>
            <template #cell-loaded_qty="{ row }">{{ angka(row.loaded_qty) }}</template>
            <template #cell-status="{ row }"><Badge :variant="row.status.variant">{{ row.status.label }}</Badge></template>
            <template #aksi="{ row }">
                <Button :tag="Link" :href="`/muatan/${row.id}`" variant="light">Detail</Button>
            </template>
        </DataTable>
    </AppLayout>
</template>
