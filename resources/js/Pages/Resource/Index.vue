<script setup>
// Halaman master generik (App\Http\Controllers\ResourceController): tabel, cari, filter, form tambah/ubah dalam modal.
import { computed, ref } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Badge from '@/Components/ui/atoms/Badge.vue';
import Button from '@/Components/ui/atoms/Button.vue';
import FilterBar from '@/Components/ui/molecules/FilterBar.vue';
import FormField from '@/Components/ui/molecules/FormField.vue';
import Modal from '@/Components/ui/molecules/Modal.vue';
import PageHeader from '@/Components/ui/molecules/PageHeader.vue';
import DataTable from '@/Components/ui/organisms/DataTable.vue';
import { angka, rupiah } from '@/Support/format.js';

const props = defineProps({
    resource: { type: Object, required: true },
    rows: { type: Object, required: true },
    query: { type: Object, default: () => ({}) },
});

const appName = usePage().props.appName;
const kolom = computed(() => Object.fromEntries(props.resource.columns.map((c) => [c.key, c.label])));
const jenis = computed(() => Object.fromEntries(props.resource.columns.map((c) => [c.key, c.type])));
const angkaKolom = computed(() => props.resource.columns.filter((c) => c.type === 'number' || c.type === 'money').map((c) => c.key));
const filterFields = computed(() => [
    { name: 'q', label: 'Cari', type: 'search' },
    ...props.resource.filters.map((f) => ({ ...f, type: 'select' })),
]);

// Nilai kosong form baru: checkbox "Aktif" default menyala.
function kosong() {
    return Object.fromEntries(props.resource.fields.map((f) => [f.name, f.type === 'checkbox' ? f.name === 'is_active' : f.type === 'multiselect' ? [] : f.type === 'color' ? '#18a6b1' : '']));
}

const form = useForm(kosong());
const terbuka = ref(false);
const sedangDiubah = ref(null);
const hapus = ref(null);

const terlihat = (f) => !f.show_if || Object.entries(f.show_if).every(([k, v]) => Boolean(form[k]) === v);

function tambah() {
    sedangDiubah.value = null;
    form.defaults(kosong());
    form.reset();
    form.clearErrors();
    terbuka.value = true;
}

function ubah(row) {
    sedangDiubah.value = row;
    const nilai = { ...kosong(), ...Object.fromEntries(Object.entries(row.values).map(([k, v]) => [k, v ?? (props.resource.fields.find((f) => f.name === k)?.type === 'multiselect' ? [] : '')])) };
    form.defaults(nilai);
    form.reset();
    form.clearErrors();
    terbuka.value = true;
}

function simpan() {
    const opsi = { preserveScroll: true, onSuccess: () => { terbuka.value = false; } };
    sedangDiubah.value
        ? form.put(`/${props.resource.slug}/${sedangDiubah.value.id}`, opsi)
        : form.post(`/${props.resource.slug}`, opsi);
}

function konfirmasiHapus() {
    router.delete(`/${props.resource.slug}/${hapus.value.id}`, { preserveScroll: true, onFinish: () => { hapus.value = null; } });
}

const slotSel = (key) => `cell-${key}`;

function tampil(row, key) {
    const v = row.cells[key];
    if (v === null || v === undefined || v === '') return '-';
    if (jenis.value[key] === 'money') return rupiah(v);
    if (jenis.value[key] === 'number') return angka(v);
    return v;
}
</script>

<template>
    <Head :title="`${resource.title} · ${appName}`" />
    <AppLayout>
        <PageHeader :title="resource.title" :subtitle="resource.subtitle">
            <template #actions>
                <Button v-for="a in resource.actions" :key="a.key" :tag="Link" :href="a.href" :variant="a.variant ?? 'outline-secondary'">{{ a.label }}</Button>
                <Button v-if="resource.canManage && resource.fields.length" @click="tambah">Tambah {{ resource.title.toLowerCase() }}</Button>
            </template>
        </PageHeader>

        <DataTable :columns="kolom" :rows="rows.data" :total="rows.total" :links="rows.links" :numeric="angkaKolom" row-key="id">
            <template #header>
                <FilterBar :url="`/${resource.slug}`" :fields="filterFields" :values="query" />
            </template>

            <template v-for="c in resource.columns" :key="c.key" #[slotSel(c.key)]="{ row }">
                <Badge v-if="c.type === 'badge' && row.cells[c.key]" :variant="row.cells[c.key].variant">{{ row.cells[c.key].label }}</Badge>
                <template v-else>{{ tampil(row, c.key) }}</template>
            </template>

            <template v-if="resource.canManage || rows.data.some((r) => r.actions.length)" #aksi="{ row }">
                <div class="flex gap-1">
                    <Button v-for="a in row.actions" :key="a.key" :tag="Link" :href="a.href" variant="outline-primary">{{ a.label }}</Button>
                    <Button v-if="row.values" variant="light" @click="ubah(row)">Ubah</Button>
                    <Button v-if="row.can_delete" variant="outline-danger" @click="hapus = row">Hapus</Button>
                </div>
            </template>
        </DataTable>

        <Modal :show="terbuka" :title="`${sedangDiubah ? 'Ubah' : 'Tambah'} ${resource.title.toLowerCase()}`" @close="terbuka = false">
            <form id="form-resource" class="form-grid" @submit.prevent="simpan">
                <template v-for="f in resource.fields" :key="f.name">
                    <FormField v-if="terlihat(f)" v-model="form[f.name]" :field="f" :error="form.errors[f.name]" />
                </template>
            </form>
            <template #footer>
                <Button variant="light" @click="terbuka = false">Batal</Button>
                <Button type="submit" form="form-resource" :loading="form.processing">Simpan</Button>
            </template>
        </Modal>

        <Modal :show="hapus !== null" title="Hapus data" @close="hapus = null">
            <p class="mb-0">Hapus data ini? Tindakan ini tidak bisa dibatalkan.</p>
            <template #footer>
                <Button variant="light" @click="hapus = null">Batal</Button>
                <Button variant="outline-danger" @click="konfirmasiHapus">Hapus</Button>
            </template>
        </Modal>
    </AppLayout>
</template>
