<script setup>
import { computed, ref } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Badge from '@/Components/ui/atoms/Badge.vue';
import Button from '@/Components/ui/atoms/Button.vue';
import Alert from '@/Components/ui/molecules/Alert.vue';
import Card from '@/Components/ui/molecules/Card.vue';
import FilterBar from '@/Components/ui/molecules/FilterBar.vue';
import PageHeader from '@/Components/ui/molecules/PageHeader.vue';
import DataTable from '@/Components/ui/organisms/DataTable.vue';
import { angka } from '@/Support/format.js';

const props = defineProps({
    filters: { type: Object, required: true },
    depots: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => [] },
    trips: { type: Array, default: () => [] },
    withoutTrip: { type: Array, default: () => [] },
    canLoad: { type: Boolean, default: false },
});

const appName = usePage().props.appName;
// Daftar motoris tanpa muatan bisa ratusan nama: tampilkan jumlahnya dulu, daftar per depo bila dibuka.
const tampilkanDaftar = ref(false);
const jumlahTanpaMuatan = computed(() => props.withoutTrip.reduce((a, g) => a + g.names.length, 0));
</script>

<template>
    <Head :title="`Muatan Harian · ${appName}`" />
    <AppLayout>
        <PageHeader title="Muatan Harian" subtitle="Barang yang dibawa tiap motoris. Alur: dimuat gudang → berangkat (HP) → kembali → dibongkar gudang → setoran & rekonsiliasi → selesai.">
            <template v-if="canLoad" #actions><Button :tag="Link" href="/muatan/baru">Buat muatan</Button></template>
        </PageHeader>

        <Alert v-if="jumlahTanpaMuatan" variant="info" class="mb-3">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <span><strong>{{ angka(jumlahTanpaMuatan) }}</strong> motoris aktif belum punya muatan pada tanggal ini<template v-if="withoutTrip.length > 1"> di {{ withoutTrip.length }} depo</template>.</span>
                <Button variant="light" @click="tampilkanDaftar = !tampilkanDaftar">{{ tampilkanDaftar ? 'Sembunyikan' : 'Lihat daftar' }}</Button>
            </div>
        </Alert>
        <Card v-if="tampilkanDaftar && jumlahTanpaMuatan" class="mb-3" body-class="card-body-compact scroll-panel">
            <div v-for="g in withoutTrip" :key="g.depot" class="stat-row items-start">
                <strong class="shrink-0">{{ g.depot }} ({{ g.names.length }})</strong>
                <span class="text-xs text-gray-600 text-right">{{ g.names.join(', ') }}</span>
            </div>
        </Card>

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
