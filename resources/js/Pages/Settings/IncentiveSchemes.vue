<script setup>
// Skema insentif: per effective call, komisi % penjualan, dan bonus bertingkat dari pencapaian target.
import { ref } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Badge from '@/Components/ui/atoms/Badge.vue';
import Button from '@/Components/ui/atoms/Button.vue';
import Checkbox from '@/Components/ui/atoms/Checkbox.vue';
import FieldError from '@/Components/ui/atoms/FieldError.vue';
import Input from '@/Components/ui/atoms/Input.vue';
import Label from '@/Components/ui/atoms/Label.vue';
import Select from '@/Components/ui/atoms/Select.vue';
import Card from '@/Components/ui/molecules/Card.vue';
import Modal from '@/Components/ui/molecules/Modal.vue';
import PageHeader from '@/Components/ui/molecules/PageHeader.vue';
import { angka, rupiah } from '@/Support/format.js';

defineProps({
    schemes: { type: Array, default: () => [] },
    targets: { type: Array, default: () => [] },
    canManage: { type: Boolean, default: false },
});

const appName = usePage().props.appName;
const terbuka = ref(false);
const sedang = ref(null);
const form = useForm({ name: '', applies_to: 'salesman', is_active: true, rules: { per_effective_call: 0, revenue_percent: 0, tiers: [] } });

function buka(s = null) {
    sedang.value = s;
    form.name = s?.name ?? '';
    form.applies_to = s?.applies_to ?? 'salesman';
    form.is_active = s?.is_active ?? true;
    form.rules = s ? JSON.parse(JSON.stringify(s.rules)) : { per_effective_call: 0, revenue_percent: 0, tiers: [] };
    form.clearErrors();
    terbuka.value = true;
}

function simpan() {
    const opsi = { preserveScroll: true, onSuccess: () => { terbuka.value = false; } };
    sedang.value ? form.put(`/pengaturan/skema-insentif/${sedang.value.id}`, opsi) : form.post('/pengaturan/skema-insentif', opsi);
}
</script>

<template>
    <Head :title="`Skema Insentif · ${appName}`" />
    <AppLayout>
        <PageHeader title="Skema Insentif" subtitle="Insentif = (EC × tarif) + (% × penjualan) + bonus tingkat tertinggi yang dicapai. Koordinator dihitung dari penjualan dan target timnya.">
            <template v-if="canManage" #actions><Button @click="buka()">Tambah skema</Button></template>
        </PageHeader>

        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
            <Card v-for="s in schemes" :key="s.id">
                <template #header>
                    <div class="flex items-center justify-between gap-2">
                        <h6 class="mb-0 font-semibold">{{ s.name }}</h6>
                        <span><Badge variant="primary">{{ s.applies_to_label }}</Badge> <Badge :variant="s.is_active ? 'success' : 'secondary'">{{ s.is_active ? 'Aktif' : 'Nonaktif' }}</Badge></span>
                    </div>
                </template>
                <div class="stat-row"><span>Per effective call</span><strong>{{ rupiah(s.rules.per_effective_call) }}</strong></div>
                <div class="stat-row"><span>Komisi penjualan</span><strong>{{ s.rules.revenue_percent }}%</strong></div>
                <p class="stat-label">Bonus pencapaian target</p>
                <div v-for="t in s.rules.tiers" :key="t.min_achievement" class="stat-row"><span>≥ {{ angka(t.min_achievement) }}%</span><strong>{{ rupiah(t.amount) }}</strong></div>
                <p v-if="!s.rules.tiers.length" class="text-xs text-gray-500 mb-0">Tanpa bonus bertingkat.</p>
                <template v-if="canManage" #footer><Button variant="light" @click="buka(s)">Ubah</Button></template>
            </Card>
        </div>

        <Modal :show="terbuka" :title="sedang ? 'Ubah skema' : 'Tambah skema'" @close="terbuka = false">
            <form id="form-skema" class="space-y-3" @submit.prevent="simpan">
                <div class="form-grid">
                    <div><Label for="s-name">Nama</Label><Input id="s-name" v-model="form.name" /><FieldError v-if="form.errors.name">{{ form.errors.name }}</FieldError></div>
                    <div><Label for="s-to">Untuk</Label><Select id="s-to" v-model="form.applies_to" :options="targets" /></div>
                    <div><Label for="s-ec">Rupiah per effective call</Label><Input id="s-ec" v-model="form.rules.per_effective_call" type="number" min="0" /></div>
                    <div><Label for="s-pct">Komisi % dari penjualan</Label><Input id="s-pct" v-model="form.rules.revenue_percent" type="number" min="0" max="100" step="0.01" /></div>
                </div>
                <div>
                    <p class="stat-label">Bonus bertingkat (pencapaian target penjualan)</p>
                    <div v-for="(t, i) in form.rules.tiers" :key="i" class="flex items-end gap-2 mb-2">
                        <div><Label :for="`t-min-${i}`">Minimal %</Label><Input :id="`t-min-${i}`" v-model="t.min_achievement" type="number" min="0" class="qty-input" /></div>
                        <div class="grow"><Label :for="`t-amt-${i}`">Bonus (Rp)</Label><Input :id="`t-amt-${i}`" v-model="t.amount" type="number" min="0" /></div>
                        <Button variant="outline-danger" @click="form.rules.tiers.splice(i, 1)">Hapus</Button>
                    </div>
                    <Button variant="light" @click="form.rules.tiers.push({ min_achievement: 100, amount: 0 })">Tambah tingkat</Button>
                    <FieldError v-if="Object.keys(form.errors).some((k) => k.startsWith('rules'))">Periksa isian tarif dan tingkat bonus.</FieldError>
                </div>
                <label class="form-check"><Checkbox v-model="form.is_active" /><span>Aktif</span></label>
            </form>
            <template #footer>
                <Button variant="light" @click="terbuka = false">Batal</Button>
                <Button type="submit" form="form-skema" :loading="form.processing">Simpan</Button>
            </template>
        </Modal>
    </AppLayout>
</template>
