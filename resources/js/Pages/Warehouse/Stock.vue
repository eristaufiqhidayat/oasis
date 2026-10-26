<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Button from '@/Components/ui/atoms/Button.vue';
import FilterBar from '@/Components/ui/molecules/FilterBar.vue';
import PageHeader from '@/Components/ui/molecules/PageHeader.vue';
import DataTable from '@/Components/ui/organisms/DataTable.vue';
import { angka, qtyDus } from '@/Support/format.js';

const props = defineProps({
    depot: { type: Number, default: null },
    depots: { type: Array, default: () => [] },
    locations: { type: Array, default: () => [] },
    products: { type: Array, default: () => [] },
    canManage: { type: Boolean, default: false },
});

const appName = usePage().props.appName;
const kolom = computed(() => ({ sku: 'SKU', name: 'Produk', ...Object.fromEntries(props.locations.map((l) => [`loc${l.id}`, l.name])), total: 'Total' }));
const baris = computed(() => props.products.map((p) => ({
    ...p,
    ...Object.fromEntries(props.locations.map((l) => [`loc${l.id}`, p.balances[l.id] ?? 0])),
    total: Object.values(p.balances).reduce((a, b) => a + b, 0),
})));
const kolomAngka = computed(() => Object.keys(kolom.value).filter((k) => k.startsWith('loc') || k === 'total'));
const slotLokasi = (l) => `cell-loc${l.id}`;
</script>

<template>
    <Head :title="`Stok · ${appName}`" />
    <AppLayout>
        <PageHeader title="Stok" subtitle="Saldo terkini per lokasi: gudang, barang rusak, dan kendaraan motoris yang sedang membawa barang.">
            <template #actions>
                <Button :tag="Link" href="/mutasi-stok" variant="light">Mutasi stok</Button>
                <Button v-if="canManage" :tag="Link" href="/dokumen-stok">Penerimaan & opname</Button>
            </template>
        </PageHeader>

        <DataTable :columns="kolom" :rows="baris" :total="baris.length" :show-footer="false" :numeric="kolomAngka" row-key="id" empty-text="Belum ada produk.">
            <template #header>
                <FilterBar url="/stok" :fields="[{ name: 'depot', label: 'Depo', type: 'select', options: depots, placeholder: '— Pilih depo —' }]" :values="{ depot }" />
            </template>
            <template v-for="l in locations" :key="l.id" #[slotLokasi(l)]="{ row }">
                <span :class="{ 'text-variance-minus': row[`loc${l.id}`] < 0, 'text-gray-400': row[`loc${l.id}`] === 0 }">{{ angka(row[`loc${l.id}`]) }}</span>
            </template>
            <template #cell-total="{ row }">
                <strong>{{ angka(row.total) }}</strong> <small class="block text-gray-500">{{ qtyDus(row.total, row.units_per_case) }}</small>
            </template>
        </DataTable>
    </AppLayout>
</template>
