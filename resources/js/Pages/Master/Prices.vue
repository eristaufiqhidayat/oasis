<script setup>
// Harga jual: nasional atau khusus depo, berlaku mulai tanggal tertentu (riwayat harga tetap tersimpan).
import { computed } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Badge from '@/Components/ui/atoms/Badge.vue';
import Button from '@/Components/ui/atoms/Button.vue';
import FieldError from '@/Components/ui/atoms/FieldError.vue';
import Input from '@/Components/ui/atoms/Input.vue';
import Label from '@/Components/ui/atoms/Label.vue';
import Alert from '@/Components/ui/molecules/Alert.vue';
import Card from '@/Components/ui/molecules/Card.vue';
import FilterBar from '@/Components/ui/molecules/FilterBar.vue';
import PageHeader from '@/Components/ui/molecules/PageHeader.vue';
import DataTable from '@/Components/ui/organisms/DataTable.vue';
import { rupiah } from '@/Support/format.js';

const props = defineProps({
    depot: { type: Number, default: null },
    date: { type: String, required: true },
    depots: { type: Array, default: () => [] },
    products: { type: Array, default: () => [] },
    canManage: { type: Boolean, default: false },
});

const appName = usePage().props.appName;
const namaDepo = computed(() => props.depots.find((d) => d.value === props.depot)?.label ?? null);
const form = useForm({ depot_id: props.depot, effective_from: props.date, prices: Object.fromEntries(props.products.map((p) => [p.id, null])) });
const adaIsian = computed(() => Object.values(form.prices).some((v) => v !== null && v !== ''));
const kolom = computed(() => ({ sku: 'SKU', name: 'Produk', national: 'Harga nasional', ...(props.depot ? { price: 'Harga berlaku di depo' } : {}), upcoming: 'Terjadwal', baru: 'Harga baru' }));

function simpan() {
    form.transform((d) => ({ ...d, prices: Object.fromEntries(Object.entries(d.prices).filter(([, v]) => v !== null && v !== '')) }))
        .post('/master/harga', { preserveScroll: true, onSuccess: () => form.reset('prices') });
}

function hapusKhusus(p) {
    router.delete(`/master/harga/${props.depot}/${p.id}`, { preserveScroll: true });
}
</script>

<template>
    <Head :title="`Harga Jual · ${appName}`" />
    <AppLayout>
        <PageHeader title="Harga Jual" subtitle="Harga nasional berlaku di semua depo; harga khusus depo menimpanya. Perubahan berlaku mulai tanggal yang dipilih, transaksi lama tetap memakai harga saat itu." />

        <Alert v-if="depot" variant="info" class="mb-3">Mengatur harga khusus <strong>{{ namaDepo }}</strong>. Kosongkan depo untuk mengatur harga nasional.</Alert>

        <DataTable :columns="kolom" :rows="products" :total="products.length" :show-footer="false" :numeric="['national', 'price']" row-key="id">
            <template #header>
                <FilterBar url="/master/harga" :fields="[{ name: 'depot', label: 'Depo', type: 'select', options: depots, placeholder: 'Nasional' }, { name: 'tanggal', label: 'Per tanggal', type: 'date' }]" :values="{ depot, tanggal: date }" />
            </template>
            <template #cell-national="{ row }">{{ rupiah(row.national) }}</template>
            <template #cell-price="{ row }">
                {{ rupiah(row.price) }} <Badge v-if="row.is_override" variant="primary">khusus</Badge>
                <Button v-if="row.is_override && canManage" variant="outline-danger" class="ml-1" @click="hapusKhusus(row)">Pakai nasional</Button>
            </template>
            <template #cell-upcoming="{ row }">
                <span v-for="u in row.upcoming" :key="u.from" class="block text-xs">{{ rupiah(u.price) }} mulai {{ u.from }}</span>
                <span v-if="!row.upcoming.length" class="text-gray-400">-</span>
            </template>
            <template #cell-baru="{ row }">
                <Input v-if="canManage" v-model="form.prices[row.id]" type="number" min="0" class="qty-input" :aria-label="`Harga baru ${row.name}`" />
            </template>
        </DataTable>

        <Card v-if="canManage" class="mt-3">
            <div class="flex flex-wrap items-end gap-3">
                <div>
                    <Label for="effective_from">Berlaku mulai</Label>
                    <Input id="effective_from" v-model="form.effective_from" type="date" />
                    <FieldError v-if="form.errors.effective_from">{{ form.errors.effective_from }}</FieldError>
                </div>
                <Button :disabled="!adaIsian" :loading="form.processing" @click="simpan">Simpan harga {{ depot ? 'khusus depo' : 'nasional' }}</Button>
            </div>
        </Card>
    </AppLayout>
</template>
