<script setup>
// Rekonsiliasi satu muatan: kasir mencatat uang diterima, kepala depo memutuskan selisih dan menutup muatan.
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Badge from '@/Components/ui/atoms/Badge.vue';
import Button from '@/Components/ui/atoms/Button.vue';
import FieldError from '@/Components/ui/atoms/FieldError.vue';
import Input from '@/Components/ui/atoms/Input.vue';
import Label from '@/Components/ui/atoms/Label.vue';
import Select from '@/Components/ui/atoms/Select.vue';
import Alert from '@/Components/ui/molecules/Alert.vue';
import Card from '@/Components/ui/molecules/Card.vue';
import PageHeader from '@/Components/ui/molecules/PageHeader.vue';
import StatCard from '@/Components/ui/molecules/StatCard.vue';
import DataTable from '@/Components/ui/organisms/DataTable.vue';
import { angka, rupiah } from '@/Support/format.js';

const props = defineProps({
    trip: { type: Object, required: true },
    summary: { type: Object, required: true },
    hasVariance: { type: Boolean, default: false },
    settlement: { type: Object, default: null },
    resolutions: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
});

const appName = usePage().props.appName;
const terima = useForm({ cash_received: props.summary.cash_received ?? props.summary.cash_expected, note: props.settlement?.note ?? '' });
const setujui = useForm({ resolution: '', note: '' });
const varianUang = (v) => (v === null ? 'secondary' : v === 0 ? 'success' : 'danger');
</script>

<template>
    <Head :title="`Setoran ${trip.number} · ${appName}`" />
    <AppLayout breadcrumb-slug="setoran">
        <PageHeader :title="`Setoran ${trip.number}`" :subtitle="`${trip.employee} · ${trip.depot} · ${trip.date}`">
            <template #actions>
                <Badge :variant="trip.status.variant">{{ trip.status.label }}</Badge>
                <Button :tag="Link" :href="`/muatan/${trip.id}`" variant="light">Detail muatan</Button>
                <Button :tag="Link" href="/setoran" variant="light">Kembali</Button>
            </template>
        </PageHeader>

        <Alert v-if="!trip.unloaded" variant="info" class="mb-3">Muatan belum dibongkar gudang. Kasir sudah bisa mencatat uang, tetapi rekonsiliasi baru bisa disetujui setelah bongkar.</Alert>

        <div class="stat-grid mb-3">
            <StatCard label="Wajib setor (tunai)" :value="rupiah(summary.cash_expected)" :hint="`Transfer ${rupiah(summary.transfer_total)} tidak disetor`" icon="calculator" />
            <StatCard label="Diterima kasir" :value="rupiah(summary.cash_received)" :hint="settlement?.received_by ? `${settlement.received_by}, ${settlement.received_at}` : 'Belum dicatat'" icon="banknote" variant="success" />
            <StatCard label="Selisih uang" :value="rupiah(summary.cash_variance)" icon="arrow-left-right" :variant="summary.cash_variance ? 'danger' : 'success'" />
            <StatCard label="Kurang barang" :value="rupiah(summary.stock_shortage_value)" :hint="summary.container_shortage ? `${summary.container_shortage} galon kosong kurang` : 'Galon kosong sesuai'" icon="boxes" :variant="summary.stock_shortage_value || summary.container_shortage ? 'danger' : 'success'" />
        </div>

        <DataTable class="mb-3" title="Rekonsiliasi barang" :columns="{ name: 'Produk', loaded: 'Dimuat', sold: 'Terjual', customer_returns: 'Retur outlet', expected_remaining: 'Seharusnya kembali', returned_good: 'Kembali layak', returned_damaged: 'Rusak', shortage: 'Selisih', shortage_value: 'Nilai selisih' }" :rows="summary.lines" :total="summary.lines.length" :show-footer="false" :numeric="['loaded', 'sold', 'customer_returns', 'expected_remaining', 'returned_good', 'returned_damaged', 'shortage', 'shortage_value']" row-key="product_id">
            <template #cell-returned_good="{ row }">{{ row.returned_good ?? '-' }}</template>
            <template #cell-returned_damaged="{ row }">{{ row.returned_damaged ?? '-' }}</template>
            <template #cell-shortage="{ row }"><span :class="{ 'text-variance-minus': row.shortage }">{{ row.shortage ?? '-' }}</span></template>
            <template #cell-shortage_value="{ row }">{{ row.shortage_value ? rupiah(row.shortage_value) : '-' }}</template>
            <template #tfoot>
                <tr>
                    <td colspan="8" class="text-end">Galon kosong: ditarik {{ angka(summary.containers_collected) }}, diterima gudang {{ summary.containers_received ?? '-' }}</td>
                    <td class="text-end font-semibold">{{ rupiah(summary.stock_shortage_value) }}</td>
                </tr>
            </template>
        </DataTable>

        <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
            <Card v-if="can.receive">
                <template #header><h6 class="mb-0 font-semibold">Catat uang setoran (kasir)</h6></template>
                <form class="space-y-3" @submit.prevent="terima.post(`/setoran/${trip.id}/terima`, { preserveScroll: true })">
                    <div class="form-grid">
                        <div><Label for="cash">Uang tunai diterima (Rp)</Label><Input id="cash" v-model="terima.cash_received" type="number" min="0" /><FieldError v-if="terima.errors.cash_received">{{ terima.errors.cash_received }}</FieldError></div>
                        <div><Label for="cnote">Catatan</Label><Input id="cnote" v-model="terima.note" /></div>
                    </div>
                    <p class="text-xs mb-0">Selisih bila disimpan: <Badge :variant="varianUang(Number(terima.cash_received) - summary.cash_expected)">{{ rupiah(Number(terima.cash_received) - summary.cash_expected) }}</Badge></p>
                    <Button type="submit" :loading="terima.processing">Simpan setoran</Button>
                </form>
            </Card>

            <Card v-if="can.approve">
                <template #header><h6 class="mb-0 font-semibold">Setujui rekonsiliasi (kepala depo)</h6></template>
                <form class="space-y-3" @submit.prevent="setujui.post(`/setoran/${trip.id}/setujui`, { preserveScroll: true })">
                    <Alert v-if="hasVariance" variant="danger">Ada selisih. Pilih keputusan dan tulis catatan sebelum menyetujui.</Alert>
                    <Alert v-else variant="success">Barang dan uang cocok.</Alert>
                    <div v-if="hasVariance" class="form-grid">
                        <div><Label for="res">Keputusan selisih</Label><Select id="res" v-model="setujui.resolution" :options="resolutions" placeholder="— Pilih —" /><FieldError v-if="setujui.errors.resolution">{{ setujui.errors.resolution }}</FieldError></div>
                        <div><Label for="anote">Catatan</Label><Input id="anote" v-model="setujui.note" /></div>
                    </div>
                    <Button type="submit" variant="success" :loading="setujui.processing">Setujui & tutup muatan</Button>
                </form>
            </Card>

            <Card v-if="settlement?.approved_at">
                <template #header><h6 class="mb-0 font-semibold">Disetujui</h6></template>
                <dl class="detail-list mb-0">
                    <div><dt>Oleh</dt><dd>{{ settlement.approved_by }}</dd></div>
                    <div><dt>Waktu</dt><dd>{{ settlement.approved_at }}</dd></div>
                    <div><dt>Keputusan</dt><dd>{{ settlement.resolution ?? '-' }}</dd></div>
                    <div><dt>Catatan</dt><dd>{{ settlement.note ?? '-' }}</dd></div>
                </dl>
            </Card>
        </div>
    </AppLayout>
</template>
