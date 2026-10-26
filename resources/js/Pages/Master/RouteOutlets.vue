<script setup>
// Susun outlet sebuah rute kunjungan: tambah, hapus, ubah urutan. Disimpan sekaligus.
import { ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Button from '@/Components/ui/atoms/Button.vue';
import Card from '@/Components/ui/molecules/Card.vue';
import Combobox from '@/Components/ui/molecules/Combobox.vue';
import PageHeader from '@/Components/ui/molecules/PageHeader.vue';
import DataTable from '@/Components/ui/organisms/DataTable.vue';

const props = defineProps({
    route: { type: Object, required: true },
    outlets: { type: Array, default: () => [] },
    available: { type: Array, default: () => [] },
    canManage: { type: Boolean, default: false },
});

const appName = usePage().props.appName;
const daftar = ref(props.outlets.map((o) => ({ ...o })));
const tambahan = ref(null);
const menyimpan = ref(false);
const berubah = ref(false);

function tambah() {
    const pilihan = props.available.find((a) => String(a.value) === String(tambahan.value));
    if (!pilihan || daftar.value.some((o) => o.id === pilihan.value)) return;
    daftar.value.push({ id: pilihan.value, code: '', name: pilihan.label, address: null, territory: null });
    tambahan.value = null;
    berubah.value = true;
}

function geser(i, arah) {
    const j = i + arah;
    if (j < 0 || j >= daftar.value.length) return;
    const salinan = [...daftar.value];
    [salinan[i], salinan[j]] = [salinan[j], salinan[i]];
    daftar.value = salinan;
    berubah.value = true;
}

function buang(i) {
    daftar.value.splice(i, 1);
    berubah.value = true;
}

function simpan() {
    menyimpan.value = true;
    router.put(`/master/rute/${props.route.id}/outlet`, { outlets: daftar.value.map((o) => o.id) }, {
        preserveScroll: true,
        onSuccess: () => { berubah.value = false; },
        onFinish: () => { menyimpan.value = false; },
    });
}
</script>

<template>
    <Head :title="`${route.name} · ${appName}`" />
    <AppLayout breadcrumb-slug="master/rute">
        <PageHeader :title="route.name" :subtitle="`${route.depot} · ${route.employee ?? 'Belum ada motoris'} · ${route.weekday}`">
            <template #actions>
                <Button :tag="Link" href="/master/rute" variant="light">Kembali</Button>
                <Button v-if="canManage" :disabled="!berubah" :loading="menyimpan" @click="simpan">Simpan urutan</Button>
            </template>
        </PageHeader>

        <Card v-if="canManage" class="mb-3">
            <div class="flex flex-wrap items-end gap-2">
                <div class="w-96 max-w-full"><Combobox v-model="tambahan" :options="available" placeholder="Cari outlet aktif di depo ini..." /></div>
                <Button variant="outline-primary" :disabled="!tambahan" @click="tambah">Tambahkan ke rute</Button>
            </div>
        </Card>

        <DataTable :columns="{ seq: 'Urutan', name: 'Outlet', address: 'Alamat', territory: 'Area' }" :rows="daftar.map((o, i) => ({ ...o, seq: i + 1, index: i }))" :total="daftar.length" :show-footer="false" row-key="id" empty-text="Rute belum berisi outlet.">
            <template #cell-name="{ row }">{{ row.name }} <small v-if="row.code" class="text-gray-500">({{ row.code }})</small></template>
            <template v-if="canManage" #aksi="{ row }">
                <div class="flex gap-1">
                    <Button variant="light" :disabled="row.index === 0" aria-label="Naik" @click="geser(row.index, -1)">↑</Button>
                    <Button variant="light" :disabled="row.index === daftar.length - 1" aria-label="Turun" @click="geser(row.index, 1)">↓</Button>
                    <Button variant="outline-danger" @click="buang(row.index)">Keluarkan</Button>
                </div>
            </template>
        </DataTable>
    </AppLayout>
</template>
