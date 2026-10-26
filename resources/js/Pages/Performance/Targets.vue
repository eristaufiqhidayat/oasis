<script setup>
// Target bulanan per depo (nilai & EC), per motoris (nilai & EC), atau per SKU per depo (qty).
import { computed, ref } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Button from '@/Components/ui/atoms/Button.vue';
import Input from '@/Components/ui/atoms/Input.vue';
import Alert from '@/Components/ui/molecules/Alert.vue';
import FilterBar from '@/Components/ui/molecules/FilterBar.vue';
import PageHeader from '@/Components/ui/molecules/PageHeader.vue';
import Tabs from '@/Components/ui/molecules/Tabs.vue';
import DataTable from '@/Components/ui/organisms/DataTable.vue';
import { angka, rupiah } from '@/Support/format.js';

const props = defineProps({
    filters: { type: Object, required: true },
    tabs: { type: Array, default: () => [] },
    depots: { type: Array, default: () => [] },
    rows: { type: Array, default: () => [] },
    depotTarget: { type: Object, default: null },
    canManage: { type: Boolean, default: false },
});

const appName = usePage().props.appName;
const tab = ref(props.filters.tab);
const daftarTab = props.tabs.map((t) => ({ key: t.value, label: t.label }));
const form = useForm({ bulan: props.filters.bulan, tab: props.filters.tab, depot: props.filters.depot, rows: props.rows.map((r) => ({ id: r.id, revenue: r.revenue ?? '', ec: r.ec ?? '', quantity: r.quantity ?? '' })) });
const isian = (id) => form.rows.find((r) => r.id === id);
const total = computed(() => form.rows.reduce((a, r) => a + (Number(r.revenue) || 0), 0));

const kolom = computed(() => ({
    depot: { name: 'Depo', revenue: 'Target penjualan (Rp)', ec: 'Target EC', assigned: 'Sudah dibagi ke motoris', salesmen: 'Motoris aktif' },
    salesman: { code: 'Kode', name: 'Motoris', revenue: 'Target penjualan (Rp)', ec: 'Target EC' },
    product: { sku: 'SKU', name: 'Produk', quantity: 'Target qty' },
}[props.filters.tab]));

function pindah(t) {
    router.get('/target', { bulan: props.filters.bulan, tab: t, depot: props.filters.depot ?? undefined });
}

function simpan() {
    form.transform((d) => ({ ...d, rows: d.rows.map((r) => ({ id: r.id, revenue: r.revenue === '' ? null : r.revenue, ec: r.ec === '' ? null : r.ec, quantity: r.quantity === '' ? null : r.quantity })) }))
        .post('/target', { preserveScroll: true });
}

function bagiRata() {
    if (!confirm('Bagi target penjualan dan EC depo rata ke semua motoris aktif? Target motoris di depo ini akan ditimpa.')) return;
    router.post('/target/bagi-rata', { bulan: props.filters.bulan, depot: props.filters.depot }, { preserveScroll: true });
}
</script>

<template>
    <Head :title="`Target Bulanan · ${appName}`" />
    <AppLayout>
        <PageHeader title="Target Bulanan" subtitle="Target penjualan dan effective call (EC) per depo lalu dibagi ke motoris, serta target qty per SKU. Dipakai di Pencapaian dan Insentif." />

        <Tabs v-model="tab" :tabs="daftarTab" @update:model-value="pindah" />

        <Alert v-if="filters.tab === 'salesman' && depotTarget" variant="info" class="mb-3">
            Target depo: {{ rupiah(depotTarget.revenue) }} · {{ angka(depotTarget.ec ?? 0) }} EC. Jumlah target motoris: <strong :class="{ 'text-variance-minus': depotTarget.revenue && total !== depotTarget.revenue }">{{ rupiah(total) }}</strong>.
        </Alert>

        <DataTable :columns="kolom" :rows="rows" :total="rows.length" :show-footer="false" :numeric="['assigned', 'salesmen']" row-key="id" empty-text="Tidak ada data untuk pilihan ini.">
            <template #header>
                <FilterBar url="/target" :keep="{ tab: filters.tab }" :fields="[
                    { name: 'bulan', label: 'Bulan', type: 'month' },
                    ...(filters.tab === 'depot' ? [] : [{ name: 'depot', label: 'Depo', type: 'select', options: depots, placeholder: '— Pilih depo —' }]),
                ]" :values="filters" />
            </template>
            <template #cell-revenue="{ row }"><Input v-model="isian(row.id).revenue" type="number" min="0" :disabled="!canManage" :aria-label="`Target penjualan ${row.name}`" /></template>
            <template #cell-ec="{ row }"><Input v-model="isian(row.id).ec" type="number" min="0" class="qty-input" :disabled="!canManage" :aria-label="`Target EC ${row.name}`" /></template>
            <template #cell-quantity="{ row }"><Input v-model="isian(row.id).quantity" type="number" min="0" class="qty-input" :disabled="!canManage" :aria-label="`Target qty ${row.name}`" /> <small class="text-gray-500">{{ row.unit }}</small></template>
            <template #cell-assigned="{ row }"><span :class="{ 'text-variance-minus': row.revenue && row.assigned !== row.revenue }">{{ rupiah(row.assigned) }}</span></template>
        </DataTable>

        <div v-if="canManage" class="flex justify-end gap-2 mt-3">
            <Button v-if="filters.tab === 'salesman' && depotTarget?.revenue" variant="outline-secondary" @click="bagiRata">Bagi rata dari target depo</Button>
            <Button :loading="form.processing" :disabled="filters.tab !== 'depot' && !filters.depot" @click="simpan">Simpan target</Button>
        </div>
    </AppLayout>
</template>
