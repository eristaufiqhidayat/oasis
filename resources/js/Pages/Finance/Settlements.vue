<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Badge from '@/Components/ui/atoms/Badge.vue';
import Button from '@/Components/ui/atoms/Button.vue';
import FilterBar from '@/Components/ui/molecules/FilterBar.vue';
import PageHeader from '@/Components/ui/molecules/PageHeader.vue';
import StatCard from '@/Components/ui/molecules/StatCard.vue';
import DataTable from '@/Components/ui/organisms/DataTable.vue';
import { rupiah } from '@/Support/format.js';

defineProps({
    filters: { type: Object, required: true },
    depots: { type: Array, default: () => [] },
    rows: { type: Array, default: () => [] },
    totals: { type: Object, required: true },
});

const appName = usePage().props.appName;
const kelasSelisih = (v) => (v === null || v === 0 ? '' : v < 0 ? 'text-variance-minus' : 'text-variance-plus');
</script>

<template>
    <Head :title="`Setoran & Rekonsiliasi · ${appName}`" />
    <AppLayout>
        <PageHeader title="Setoran & Rekonsiliasi" subtitle="Per muatan: uang tunai yang wajib disetor, uang diterima kasir, dan selisih barang setelah dibongkar. Muatan selesai setelah disetujui kepala depo." />

        <div class="stat-grid mb-3">
            <StatCard label="Wajib setor" :value="rupiah(totals.expected)" icon="calculator" />
            <StatCard label="Sudah diterima" :value="rupiah(totals.received)" icon="banknote" variant="success" />
            <StatCard label="Belum disetujui" :value="String(totals.pending)" icon="clock" :variant="totals.pending ? 'warning' : 'success'" />
        </div>

        <DataTable :columns="{ number: 'Muatan', employee: 'Motoris', status: 'Status', cash_expected: 'Wajib setor', cash_received: 'Diterima', cash_variance: 'Selisih uang', stock_shortage_value: 'Kurang barang', container_shortage: 'Kurang galon', result: 'Rekonsiliasi' }" :rows="rows" :total="rows.length" :show-footer="false" :numeric="['cash_expected', 'cash_received', 'cash_variance', 'stock_shortage_value', 'container_shortage']" row-key="id" empty-text="Tidak ada muatan pada tanggal ini.">
            <template #header>
                <FilterBar url="/setoran" :fields="[{ name: 'tanggal', label: 'Tanggal muatan', type: 'date' }, { name: 'depot', label: 'Depo', type: 'select', options: depots }]" :values="filters" />
            </template>
            <template #cell-number="{ row }"><Link class="link-strong" :href="`/setoran/${row.id}`">{{ row.number }}</Link></template>
            <template #cell-status="{ row }"><Badge :variant="row.status.variant">{{ row.status.label }}</Badge></template>
            <template #cell-cash_expected="{ row }">{{ rupiah(row.cash_expected) }}</template>
            <template #cell-cash_received="{ row }">{{ rupiah(row.cash_received) }}</template>
            <template #cell-cash_variance="{ row }"><span :class="kelasSelisih(row.cash_variance)">{{ rupiah(row.cash_variance) }}</span></template>
            <template #cell-stock_shortage_value="{ row }"><span :class="{ 'text-variance-minus': row.stock_shortage_value > 0 }">{{ rupiah(row.stock_shortage_value) }}</span></template>
            <template #cell-container_shortage="{ row }"><span :class="{ 'text-variance-minus': row.container_shortage > 0 }">{{ row.container_shortage ?? '-' }}</span></template>
            <template #cell-result="{ row }">
                <Badge v-if="row.approved" variant="success">Disetujui</Badge>
                <small v-if="row.resolution" class="block text-gray-500">{{ row.resolution }}</small>
                <span v-if="!row.approved" class="text-gray-400">-</span>
            </template>
            <template #aksi="{ row }"><Button :tag="Link" :href="`/setoran/${row.id}`" variant="light">Buka</Button></template>
        </DataTable>
    </AppLayout>
</template>
