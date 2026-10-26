<script setup>
import { ref } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Badge from '@/Components/ui/atoms/Badge.vue';
import Button from '@/Components/ui/atoms/Button.vue';
import FieldError from '@/Components/ui/atoms/FieldError.vue';
import Input from '@/Components/ui/atoms/Input.vue';
import Label from '@/Components/ui/atoms/Label.vue';
import Alert from '@/Components/ui/molecules/Alert.vue';
import Card from '@/Components/ui/molecules/Card.vue';
import Modal from '@/Components/ui/molecules/Modal.vue';
import PageHeader from '@/Components/ui/molecules/PageHeader.vue';
import DataTable from '@/Components/ui/organisms/DataTable.vue';
import { angka, rupiah } from '@/Support/format.js';

const props = defineProps({
    sale: { type: Object, required: true },
    canVoid: { type: Boolean, default: false },
});

const appName = usePage().props.appName;
const terbuka = ref(false);
const form = useForm({ reason: '' });

function batalkan() {
    form.post(`/penjualan/${props.sale.id}/batal`, { preserveScroll: true, onSuccess: () => { terbuka.value = false; } });
}
</script>

<template>
    <Head :title="`${sale.number} · ${appName}`" />
    <AppLayout breadcrumb-slug="penjualan">
        <PageHeader :title="`Penjualan ${sale.number}`" :subtitle="`${sale.outlet} · ${sale.sold_at}`">
            <template #actions>
                <Badge :variant="sale.status === 'void' ? 'danger' : 'success'">{{ sale.status_label }}</Badge>
                <Button :tag="Link" href="/penjualan" variant="light">Kembali</Button>
                <Button v-if="canVoid" variant="outline-danger" @click="terbuka = true">Batalkan penjualan</Button>
            </template>
        </PageHeader>

        <Alert v-if="sale.status === 'void'" variant="danger" class="mb-3">Dibatalkan {{ sale.voided_at }}: {{ sale.void_reason }}</Alert>

        <Card class="mb-3">
            <dl class="detail-list mb-0">
                <div><dt>Outlet</dt><dd>{{ sale.outlet }} ({{ sale.outlet_code }})</dd></div>
                <div><dt>Motoris</dt><dd>{{ sale.employee }}</dd></div>
                <div><dt>Depo</dt><dd>{{ sale.depot }}</dd></div>
                <div><dt>Muatan</dt><dd><Link class="link-strong" :href="`/muatan/${sale.trip.id}`">{{ sale.trip.number }}</Link></dd></div>
                <div><dt>Cara bayar</dt><dd>{{ sale.payment }}</dd></div>
                <div><dt>Total</dt><dd>{{ rupiah(sale.total) }}</dd></div>
            </dl>
        </Card>

        <DataTable :columns="{ sku: 'SKU', name: 'Produk', quantity: 'Qty', unit_price: 'Harga', line_total: 'Jumlah' }" :rows="sale.lines" :total="sale.lines.length" :show-footer="false" :numeric="['quantity', 'unit_price', 'line_total']">
            <template #cell-quantity="{ row }">{{ angka(row.quantity) }}</template>
            <template #cell-unit_price="{ row }">{{ rupiah(row.unit_price) }}</template>
            <template #cell-line_total="{ row }">{{ rupiah(row.line_total) }}</template>
            <template #tfoot><tr><td colspan="4" class="text-end font-semibold">Total</td><td class="text-end font-semibold">{{ rupiah(sale.total) }}</td></tr></template>
        </DataTable>

        <Modal :show="terbuka" title="Batalkan penjualan" @close="terbuka = false">
            <p class="text-xs text-gray-500">Barang dikembalikan ke stok kendaraan motoris dan uang setoran berkurang. Hanya bisa sebelum muatan dibongkar.</p>
            <Label for="reason">Alasan</Label>
            <Input id="reason" v-model="form.reason" />
            <FieldError v-if="form.errors.reason">{{ form.errors.reason }}</FieldError>
            <template #footer>
                <Button variant="light" @click="terbuka = false">Tidak</Button>
                <Button variant="outline-danger" :loading="form.processing" @click="batalkan">Batalkan penjualan</Button>
            </template>
        </Modal>
    </AppLayout>
</template>
