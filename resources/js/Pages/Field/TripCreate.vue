<script setup>
// Gudang memuat barang ke kendaraan motoris. Stok gudang langsung berkurang.
import { computed } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Button from '@/Components/ui/atoms/Button.vue';
import FieldError from '@/Components/ui/atoms/FieldError.vue';
import Input from '@/Components/ui/atoms/Input.vue';
import Label from '@/Components/ui/atoms/Label.vue';
import Select from '@/Components/ui/atoms/Select.vue';
import Card from '@/Components/ui/molecules/Card.vue';
import Combobox from '@/Components/ui/molecules/Combobox.vue';
import PageHeader from '@/Components/ui/molecules/PageHeader.vue';
import DataTable from '@/Components/ui/organisms/DataTable.vue';
import { angka, qtyDus } from '@/Support/format.js';

const props = defineProps({
    depot: { type: Number, default: null },
    depots: { type: Array, default: () => [] },
    date: { type: String, required: true },
    salesmen: { type: Array, default: () => [] },
    products: { type: Array, default: () => [] },
});

const appName = usePage().props.appName;
const form = useForm({ employee_id: null, date: props.date, note: '', lines: Object.fromEntries(props.products.map((p) => [p.id, ''])) });
const total = computed(() => Object.values(form.lines).reduce((a, v) => a + (Number(v) || 0), 0));
const lebih = (p) => Number(form.lines[p.id] || 0) > p.stock;
const adaLebih = computed(() => props.products.some(lebih));

function gantiDepo(depot) {
    router.get('/muatan/baru', { depot }, { preserveScroll: true });
}

function simpan() {
    form.post('/muatan');
}
</script>

<template>
    <Head :title="`Buat Muatan · ${appName}`" />
    <AppLayout breadcrumb-slug="muatan">
        <PageHeader title="Buat Muatan" subtitle="Isi jumlah barang yang dimuat ke kendaraan motoris (dalam satuan terkecil). Satu motoris hanya boleh punya satu muatan yang belum ditutup.">
            <template #actions><Button :tag="Link" href="/muatan" variant="light">Batal</Button></template>
        </PageHeader>

        <Card class="mb-3">
            <div class="form-grid lg:grid-cols-4">
                <div v-if="depots.length > 1">
                    <Label for="depot">Depo</Label>
                    <Select id="depot" :model-value="depot" :options="depots" @update:model-value="gantiDepo" />
                </div>
                <div>
                    <Label for="employee">Motoris</Label>
                    <Combobox id="employee" v-model="form.employee_id" :options="salesmen" placeholder="Pilih motoris..." />
                    <FieldError v-if="form.errors.employee_id">{{ form.errors.employee_id }}</FieldError>
                </div>
                <div>
                    <Label for="date">Tanggal</Label>
                    <Input id="date" v-model="form.date" type="date" />
                    <FieldError v-if="form.errors.date">{{ form.errors.date }}</FieldError>
                </div>
                <div>
                    <Label for="note">Catatan</Label>
                    <Input id="note" v-model="form.note" />
                </div>
            </div>
        </Card>

        <DataTable :columns="{ sku: 'SKU', name: 'Produk', stock: 'Stok gudang', qty: 'Dimuat' }" :rows="products" :total="products.length" :show-footer="false" :numeric="['stock']" row-key="id">
            <template #cell-stock="{ row }">{{ angka(row.stock) }} <small class="text-gray-500">{{ row.unit }}</small></template>
            <template #cell-qty="{ row }">
                <div class="flex items-center gap-2">
                    <Input v-model="form.lines[row.id]" type="number" min="0" class="qty-input" :aria-label="`Qty ${row.name}`" />
                    <small :class="lebih(row) ? 'text-red-600' : 'text-gray-500'">{{ lebih(row) ? 'melebihi stok' : qtyDus(Number(form.lines[row.id] || 0), row.units_per_case) }}</small>
                </div>
            </template>
            <template #tfoot>
                <tr><td colspan="3" class="text-end font-semibold">Total dimuat</td><td class="font-semibold">{{ angka(total) }}</td></tr>
            </template>
        </DataTable>

        <FieldError v-if="form.errors.lines">{{ form.errors.lines }}</FieldError>
        <div class="flex justify-end mt-3">
            <Button :disabled="!form.employee_id || !total || adaLebih" :loading="form.processing" @click="simpan">Simpan muatan</Button>
        </div>
    </AppLayout>
</template>
