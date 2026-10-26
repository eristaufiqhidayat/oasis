<script setup>
// Detail muatan: barang dimuat/terjual/sisa, penjualan & retur selama trip, dan form bongkar gudang.
import { computed, ref } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Badge from '@/Components/ui/atoms/Badge.vue';
import Button from '@/Components/ui/atoms/Button.vue';
import FieldError from '@/Components/ui/atoms/FieldError.vue';
import Input from '@/Components/ui/atoms/Input.vue';
import Label from '@/Components/ui/atoms/Label.vue';
import Card from '@/Components/ui/molecules/Card.vue';
import Modal from '@/Components/ui/molecules/Modal.vue';
import PageHeader from '@/Components/ui/molecules/PageHeader.vue';
import StatCard from '@/Components/ui/molecules/StatCard.vue';
import DataTable from '@/Components/ui/organisms/DataTable.vue';
import { angka, rupiah } from '@/Support/format.js';

const props = defineProps({
    trip: { type: Object, required: true },
    summary: { type: Object, required: true },
    sales: { type: Array, default: () => [] },
    returns: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
});

const appName = usePage().props.appName;
const bongkar = ref(false);
const batal = ref(false);

// Isian bongkar diawali sisa menurut sistem, gudang mengoreksi bila hitungan berbeda.
const form = useForm({
    counts: Object.fromEntries(props.summary.lines.map((l) => [l.product_id, { good: l.in_vehicle, damaged: 0 }])),
    empty_containers: props.summary.containers_collected,
    note: '',
});

const selisih = (l) => l.in_vehicle - (Number(form.counts[l.product_id]?.good) || 0) - (Number(form.counts[l.product_id]?.damaged) || 0);
const kolom = computed(() => ({
    name: 'Produk', loaded: 'Dimuat', sold: 'Terjual', customer_returns: 'Retur outlet', expected_remaining: 'Sisa (sistem)',
    ...(props.trip.status.value === 'unloaded' || props.trip.status.value === 'closed' ? { returned_good: 'Kembali layak', returned_damaged: 'Rusak', shortage: 'Selisih' } : {}),
}));

function simpanBongkar() {
    form.post(`/muatan/${props.trip.id}/bongkar`, { preserveScroll: true });
}

function batalkan() {
    router.post(`/muatan/${props.trip.id}/batal`, {}, { preserveScroll: true, onFinish: () => { batal.value = false; } });
}
</script>

<template>
    <Head :title="`${trip.number} · ${appName}`" />
    <AppLayout breadcrumb-slug="muatan">
        <PageHeader :title="`Muatan ${trip.number}`" :subtitle="`${trip.employee} · ${trip.depot} · ${trip.date}`">
            <template #actions>
                <Badge :variant="trip.status.variant">{{ trip.status.label }}</Badge>
                <Button :tag="Link" href="/muatan" variant="light">Kembali</Button>
                <Button v-if="can.cancel" variant="outline-danger" @click="batal = true">Batalkan muatan</Button>
                <Button v-if="can.unload" @click="bongkar = true">Bongkar muatan</Button>
                <Button v-if="can.settle" :tag="Link" :href="`/setoran/${trip.id}`" variant="outline-primary">Setoran & rekonsiliasi</Button>
            </template>
        </PageHeader>

        <div class="stat-grid mb-3">
            <StatCard label="Penjualan tunai" :value="rupiah(summary.cash_expected)" :hint="`Transfer ${rupiah(summary.transfer_total)}`" icon="banknote" variant="success" />
            <StatCard label="Transaksi" :value="angka(sales.filter((s) => s.status === 'completed').length)" :hint="`${returns.length} retur outlet`" icon="shopping-cart" />
            <StatCard label="Galon kosong" :value="angka(summary.containers_collected)" :hint="`ditarik · ${angka(summary.containers_delivered)} galon isi terkirim`" icon="undo" />
            <StatCard label="Waktu" :value="trip.departed_at ? `${trip.departed_at} – ${trip.returned_at ?? '…'}` : 'Belum berangkat'" :hint="`Dimuat ${trip.loaded_at}`" icon="clock" />
        </div>

        <DataTable class="mb-3" title="Barang" :columns="kolom" :rows="summary.lines" :total="summary.lines.length" :show-footer="false" :numeric="Object.keys(kolom).filter((k) => k !== 'name')" row-key="product_id">
            <template #cell-returned_good="{ row }">{{ row.returned_good ?? '-' }}</template>
            <template #cell-returned_damaged="{ row }">{{ row.returned_damaged ?? '-' }}</template>
            <template #cell-shortage="{ row }"><span :class="{ 'text-variance-minus': row.shortage > 0 }">{{ row.shortage ?? '-' }}</span></template>
        </DataTable>

        <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
            <DataTable title="Penjualan" :columns="{ time: 'Jam', outlet: 'Outlet', payment: 'Bayar', total: 'Total' }" :rows="sales" :total="sales.length" :show-footer="false" :numeric="['total']" row-key="id" empty-text="Belum ada penjualan.">
                <template #cell-time="{ row }"><Link class="link-strong" :href="`/penjualan/${row.id}`">{{ row.time }}</Link></template>
                <template #cell-total="{ row }"><span :class="{ 'row-muted': row.status === 'void' }">{{ rupiah(row.total) }}</span></template>
            </DataTable>
            <DataTable title="Retur outlet" :columns="{ number: 'Nomor', outlet: 'Outlet', lines: 'Barang' }" :rows="returns" :total="returns.length" :show-footer="false" row-key="id" empty-text="Tidak ada retur." />
        </div>

        <Modal :show="bongkar" title="Bongkar muatan" @close="bongkar = false">
            <p class="text-xs text-gray-500">Hitung fisik barang yang kembali. Isian awal = sisa menurut sistem (termasuk barang retur outlet). Yang tidak terhitung dicatat sebagai selisih.</p>
            <table class="data-table mb-3">
                <thead><tr><th>Produk</th><th class="text-end">Sistem</th><th>Layak</th><th>Rusak</th><th class="text-end">Selisih</th></tr></thead>
                <tbody>
                    <tr v-for="l in summary.lines" :key="l.product_id">
                        <td>{{ l.name }}</td>
                        <td class="text-end">{{ angka(l.in_vehicle) }}</td>
                        <td><Input v-model="form.counts[l.product_id].good" type="number" min="0" class="qty-input" :aria-label="`Layak ${l.name}`" /></td>
                        <td><Input v-model="form.counts[l.product_id].damaged" type="number" min="0" class="qty-input" :aria-label="`Rusak ${l.name}`" /></td>
                        <td class="text-end" :class="{ 'text-variance-minus': selisih(l) !== 0 }">{{ selisih(l) }}</td>
                    </tr>
                </tbody>
            </table>
            <FieldError v-for="(msg, key) in form.errors" :key="key">{{ msg }}</FieldError>
            <div class="form-grid">
                <div>
                    <Label for="empty">Galon kosong diterima (ditarik {{ summary.containers_collected }})</Label>
                    <Input id="empty" v-model="form.empty_containers" type="number" min="0" />
                </div>
                <div><Label for="unote">Catatan</Label><Input id="unote" v-model="form.note" /></div>
            </div>
            <template #footer>
                <Button variant="light" @click="bongkar = false">Batal</Button>
                <Button :loading="form.processing" @click="simpanBongkar">Simpan hasil bongkar</Button>
            </template>
        </Modal>

        <Modal :show="batal" title="Batalkan muatan" @close="batal = false">
            <p class="mb-0">Semua barang di kendaraan dikembalikan ke stok gudang. Lanjutkan?</p>
            <template #footer>
                <Button variant="light" @click="batal = false">Tidak</Button>
                <Button variant="outline-danger" @click="batalkan">Ya, batalkan</Button>
            </template>
        </Modal>
    </AppLayout>
</template>
