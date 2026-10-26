<script setup>
// Penerimaan barang dari pabrik/distributor (stok bertambah) dan penyesuaian hasil opname (boleh minus).
import { ref } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Badge from '@/Components/ui/atoms/Badge.vue';
import Button from '@/Components/ui/atoms/Button.vue';
import FieldError from '@/Components/ui/atoms/FieldError.vue';
import Input from '@/Components/ui/atoms/Input.vue';
import Label from '@/Components/ui/atoms/Label.vue';
import Select from '@/Components/ui/atoms/Select.vue';
import Combobox from '@/Components/ui/molecules/Combobox.vue';
import FilterBar from '@/Components/ui/molecules/FilterBar.vue';
import Modal from '@/Components/ui/molecules/Modal.vue';
import PageHeader from '@/Components/ui/molecules/PageHeader.vue';
import DataTable from '@/Components/ui/organisms/DataTable.vue';
import { angka } from '@/Support/format.js';

const props = defineProps({
    filters: { type: Object, required: true },
    depots: { type: Array, default: () => [] },
    types: { type: Array, default: () => [] },
    documents: { type: Object, required: true },
    products: { type: Array, default: () => [] },
    canManage: { type: Boolean, default: false },
});

const appName = usePage().props.appName;
const terbuka = ref(false);
const sekarang = new Date();
const hariIni = `${sekarang.getFullYear()}-${String(sekarang.getMonth() + 1).padStart(2, '0')}-${String(sekarang.getDate()).padStart(2, '0')}`;
const form = useForm({ depot_id: props.filters.depot ?? props.depots[0]?.value ?? null, type: 'receipt', date: hariIni, reference: '', note: '', lines: [{ product_id: null, quantity: '' }] });

function buka(type) {
    form.reset();
    form.clearErrors();
    form.type = type;
    terbuka.value = true;
}

function simpan() {
    form.transform((d) => ({ ...d, lines: d.lines.filter((l) => l.product_id && l.quantity !== '') }))
        .post('/dokumen-stok', { preserveScroll: true, onSuccess: () => { terbuka.value = false; } });
}
</script>

<template>
    <Head :title="`Penerimaan & Opname · ${appName}`" />
    <AppLayout>
        <PageHeader title="Penerimaan & Opname" subtitle="Setiap dokumen langsung memperbarui stok gudang dan tercatat di mutasi stok.">
            <template v-if="canManage" #actions>
                <Button variant="outline-secondary" @click="buka('adjustment')">Penyesuaian opname</Button>
                <Button @click="buka('receipt')">Terima barang</Button>
            </template>
        </PageHeader>

        <DataTable :columns="{ number: 'Nomor', date: 'Tanggal', type: 'Jenis', depot: 'Depo', reference: 'Referensi', lines: 'Barang', user: 'Oleh' }" :rows="documents.data" :total="documents.total" :links="documents.links" row-key="id">
            <template #header>
                <FilterBar url="/dokumen-stok" :fields="[{ name: 'depot', label: 'Depo', type: 'select', options: depots }, { name: 'jenis', label: 'Jenis', type: 'select', options: types }]" :values="filters" />
            </template>
            <template #cell-type="{ row }"><Badge :variant="row.type_value === 'receipt' ? 'success' : 'warning'">{{ row.type }}</Badge></template>
            <template #cell-reference="{ row }">{{ row.reference ?? '-' }}<small v-if="row.note" class="block text-gray-500">{{ row.note }}</small></template>
            <template #cell-lines="{ row }">
                <span v-for="(l, i) in row.lines" :key="i" class="block">{{ l.name }}: <strong :class="{ 'text-variance-minus': l.quantity < 0 }">{{ angka(l.quantity) }}</strong></span>
            </template>
        </DataTable>

        <Modal :show="terbuka" :title="form.type === 'receipt' ? 'Terima barang' : 'Penyesuaian opname'" @close="terbuka = false">
            <form id="form-dokumen" class="space-y-3" @submit.prevent="simpan">
                <div class="form-grid">
                    <div><Label for="d-depot">Depo</Label><Select id="d-depot" v-model="form.depot_id" :options="depots" placeholder="— Pilih —" /><FieldError v-if="form.errors.depot_id">{{ form.errors.depot_id }}</FieldError></div>
                    <div><Label for="d-date">Tanggal</Label><Input id="d-date" v-model="form.date" type="date" /><FieldError v-if="form.errors.date">{{ form.errors.date }}</FieldError></div>
                    <div><Label for="d-ref">{{ form.type === 'receipt' ? 'No. surat jalan / DO' : 'No. berita acara' }}</Label><Input id="d-ref" v-model="form.reference" /></div>
                    <div><Label for="d-note">{{ form.type === 'receipt' ? 'Catatan' : 'Alasan penyesuaian' }}</Label><Input id="d-note" v-model="form.note" /><FieldError v-if="form.errors.note">{{ form.errors.note }}</FieldError></div>
                </div>
                <p class="text-xs text-gray-500 mb-0" v-if="form.type === 'adjustment'">Isi selisih hasil hitung: positif bila fisik lebih banyak dari sistem, negatif bila kurang.</p>
                <div v-for="(l, i) in form.lines" :key="i" class="flex items-end gap-2">
                    <div class="grow"><Label :for="`l-p-${i}`">Produk</Label><Combobox :id="`l-p-${i}`" v-model="l.product_id" :options="products" placeholder="Cari produk..." /></div>
                    <div><Label :for="`l-q-${i}`">Qty</Label><Input :id="`l-q-${i}`" v-model="l.quantity" type="number" class="qty-input" /></div>
                    <Button variant="outline-danger" :disabled="form.lines.length === 1" @click="form.lines.splice(i, 1)">Hapus</Button>
                </div>
                <Button variant="light" @click="form.lines.push({ product_id: null, quantity: '' })">Tambah baris</Button>
                <FieldError v-if="form.errors.lines">{{ form.errors.lines }}</FieldError>
            </form>
            <template #footer>
                <Button variant="light" @click="terbuka = false">Batal</Button>
                <Button type="submit" form="form-dokumen" :loading="form.processing">Simpan dokumen</Button>
            </template>
        </Modal>
    </AppLayout>
</template>
