<script setup>
import { ref } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Badge from '@/Components/ui/atoms/Badge.vue';
import Button from '@/Components/ui/atoms/Button.vue';
import FieldError from '@/Components/ui/atoms/FieldError.vue';
import Input from '@/Components/ui/atoms/Input.vue';
import Label from '@/Components/ui/atoms/Label.vue';
import FilterBar from '@/Components/ui/molecules/FilterBar.vue';
import Modal from '@/Components/ui/molecules/Modal.vue';
import PageHeader from '@/Components/ui/molecules/PageHeader.vue';
import DataTable from '@/Components/ui/organisms/DataTable.vue';

defineProps({
    outlets: { type: Object, required: true },
    filters: { type: Object, required: true },
    depots: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => [] },
});

const appName = usePage().props.appName;
const kolom = { code: 'Kode', name: 'Outlet', owner: 'Pemilik', address: 'Alamat', territory: 'Area', registered_by: 'Didaftarkan', status: 'Status' };
const pilih = ref(null);
const form = useForm({ decision: 'approve', note: '' });

function buka(outlet, decision) {
    pilih.value = outlet;
    form.decision = decision;
    form.note = '';
    form.clearErrors();
}

function kirim() {
    form.post(`/outlet-baru/${pilih.value.id}`, { preserveScroll: true, onSuccess: () => { pilih.value = null; } });
}
</script>

<template>
    <Head :title="`Verifikasi Outlet Baru · ${appName}`" />
    <AppLayout>
        <PageHeader title="Verifikasi Outlet Baru" subtitle="Outlet yang didaftarkan motoris dari HP. Periksa titik lokasi sebelum menyetujui; motoris sudah bisa berjualan ke outlet ini selama menunggu." />

        <DataTable :columns="kolom" :rows="outlets.data" :total="outlets.total" :links="outlets.links" row-key="id" empty-text="Tidak ada outlet baru.">
            <template #header>
                <FilterBar url="/outlet-baru" :fields="[{ name: 'status', label: 'Status', type: 'select', options: statuses, placeholder: false }, { name: 'depot', label: 'Depo', type: 'select', options: depots }]" :values="filters" />
            </template>
            <template #cell-name="{ row }">
                {{ row.name }}<br><small class="text-gray-500">{{ row.phone ?? '' }}</small>
            </template>
            <template #cell-registered_by="{ row }">
                {{ row.registered_by }}<br><small class="text-gray-500">{{ row.registered_at }}</small>
            </template>
            <template #cell-status="{ row }">
                <Badge :variant="row.status.variant">{{ row.status.label }}</Badge>
                <small v-if="row.note" class="block text-gray-500">{{ row.note }}</small>
            </template>
            <template #aksi="{ row }">
                <div class="flex gap-1">
                    <Button v-if="row.latitude" tag="a" variant="light" :href="`https://maps.google.com/?q=${row.latitude},${row.longitude}`" target="_blank" rel="noopener">Peta</Button>
                    <template v-if="filters.status === 'pending'">
                        <Button variant="success" @click="buka(row, 'approve')">Setujui</Button>
                        <Button variant="outline-danger" @click="buka(row, 'reject')">Tolak</Button>
                    </template>
                </div>
            </template>
        </DataTable>

        <Modal :show="pilih !== null" :title="form.decision === 'approve' ? 'Setujui outlet' : 'Tolak outlet'" @close="pilih = null">
            <p>{{ pilih?.name }} — {{ pilih?.address ?? 'tanpa alamat' }}</p>
            <Label for="note">{{ form.decision === 'approve' ? 'Catatan (opsional)' : 'Alasan penolakan' }}</Label>
            <Input id="note" v-model="form.note" />
            <FieldError v-if="form.errors.note">{{ form.errors.note }}</FieldError>
            <template #footer>
                <Button variant="light" @click="pilih = null">Batal</Button>
                <Button :variant="form.decision === 'approve' ? 'success' : 'outline-danger'" :loading="form.processing" @click="kirim">{{ form.decision === 'approve' ? 'Setujui' : 'Tolak' }}</Button>
            </template>
        </Modal>
    </AppLayout>
</template>
