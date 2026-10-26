<script setup>
// Insentif bulanan: perkiraan dihitung langsung; pilih baris → setujui (nilai dibekukan) → tandai dibayar.
import { computed, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Badge from '@/Components/ui/atoms/Badge.vue';
import Button from '@/Components/ui/atoms/Button.vue';
import Checkbox from '@/Components/ui/atoms/Checkbox.vue';
import FilterBar from '@/Components/ui/molecules/FilterBar.vue';
import PageHeader from '@/Components/ui/molecules/PageHeader.vue';
import StatCard from '@/Components/ui/molecules/StatCard.vue';
import DataTable from '@/Components/ui/organisms/DataTable.vue';
import { angka, persen, rupiah, varianCapaian } from '@/Support/format.js';

const props = defineProps({
    filters: { type: Object, required: true },
    depots: { type: Array, default: () => [] },
    schemes: { type: Array, default: () => [] },
    rows: { type: Array, default: () => [] },
    totals: { type: Object, required: true },
    can: { type: Object, default: () => ({}) },
});

const appName = usePage().props.appName;
const pilih = ref([]);
const status = { estimate: ['Perkiraan', 'secondary'], approved: ['Disetujui', 'warning'], paid: ['Dibayar', 'success'] };
const can = computed(() => props.can);
const terpilih = computed(() => props.rows.filter((r) => pilih.value.includes(r.key)));
const bisaSetujui = computed(() => can.value.approve && terpilih.value.length && terpilih.value.every((r) => r.status === 'estimate'));
const bisaBayar = computed(() => can.value.pay && terpilih.value.length && terpilih.value.every((r) => r.status === 'approved'));
const semua = computed({
    get: () => props.rows.length > 0 && pilih.value.length === props.rows.length,
    set: (v) => { pilih.value = v ? props.rows.map((r) => r.key) : []; },
});

function aksi(url) {
    router.post(url, { bulan: props.filters.bulan, keys: pilih.value }, { preserveScroll: true, onSuccess: () => { pilih.value = []; } });
}
</script>

<template>
    <Head :title="`Insentif · ${appName}`" />
    <AppLayout>
        <PageHeader title="Insentif Bulanan" subtitle="Dihitung dari skema insentif aktif, realisasi penjualan, EC, dan target. Potongan berasal dari rekonsiliasi dengan keputusan “potong insentif”.">
            <template #actions><Button :tag="Link" href="/pengaturan/skema-insentif" variant="light">Skema insentif</Button></template>
        </PageHeader>

        <div class="stat-grid mb-3">
            <StatCard label="Perkiraan" :value="rupiah(totals.estimate)" icon="calculator" />
            <StatCard label="Disetujui (belum dibayar)" :value="rupiah(totals.approved)" icon="badge-check" variant="warning" />
            <StatCard label="Dibayar" :value="rupiah(totals.paid)" icon="banknote" variant="success" />
        </div>

        <DataTable :columns="{ pick: '', employee: 'Nama', scheme: 'Skema', revenue: 'Penjualan', achievement: 'Capaian', ec: 'EC', ec_amount: 'Insentif EC', commission: 'Komisi', tier_bonus: 'Bonus', deduction: 'Potongan', net: 'Diterima', status: 'Status' }" :rows="rows" :total="rows.length" :show-footer="false" :numeric="['revenue', 'ec', 'ec_amount', 'commission', 'tier_bonus', 'deduction', 'net']" row-key="key" empty-text="Belum ada skema aktif atau motoris.">
            <template #header>
                <div class="flex flex-wrap items-end justify-between gap-2">
                    <FilterBar url="/insentif" :fields="[{ name: 'bulan', label: 'Bulan', type: 'month' }, { name: 'depot', label: 'Depo', type: 'select', options: depots }, { name: 'skema', label: 'Skema', type: 'select', options: schemes }]" :values="filters" />
                    <div class="flex gap-2">
                        <label class="form-check"><Checkbox v-model="semua" /><span>Pilih semua</span></label>
                        <Button v-if="can.approve" variant="outline-primary" :disabled="!bisaSetujui" @click="aksi('/insentif/setujui')">Setujui ({{ terpilih.length }})</Button>
                        <Button v-if="can.pay" variant="success" :disabled="!bisaBayar" @click="aksi('/insentif/bayar')">Tandai dibayar</Button>
                    </div>
                </div>
            </template>
            <template #cell-pick="{ row }"><Checkbox v-model="pilih" :value="row.key" :aria-label="`Pilih ${row.employee}`" /></template>
            <template #cell-employee="{ row }">{{ row.employee }}<small class="block text-gray-500">{{ row.depot }}{{ row.applies_to === 'coordinator' ? ` · tim ${row.team_size} motoris` : '' }}</small></template>
            <template #cell-revenue="{ row }">{{ rupiah(row.revenue) }}<small class="block text-gray-500">target {{ rupiah(row.target) }}</small></template>
            <template #cell-achievement="{ row }"><Badge :variant="varianCapaian(row.achievement)">{{ persen(row.achievement) }}</Badge></template>
            <template #cell-ec="{ row }">{{ angka(row.ec) }}</template>
            <template #cell-ec_amount="{ row }">{{ rupiah(row.ec_amount) }}</template>
            <template #cell-commission="{ row }">{{ rupiah(row.commission) }}</template>
            <template #cell-tier_bonus="{ row }">{{ rupiah(row.tier_bonus) }}</template>
            <template #cell-deduction="{ row }"><span :class="{ 'text-variance-minus': row.deduction }">{{ row.deduction ? rupiah(-row.deduction) : '-' }}</span></template>
            <template #cell-net="{ row }"><strong>{{ rupiah(row.net) }}</strong></template>
            <template #cell-status="{ row }"><Badge :variant="status[row.status][1]">{{ status[row.status][0] }}</Badge></template>
        </DataTable>
    </AppLayout>
</template>
