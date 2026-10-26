<script setup>
// Peran dan izin: setiap peran = kumpulan izin (config/permissions.php). Super Admin selalu boleh semuanya.
import { ref } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Badge from '@/Components/ui/atoms/Badge.vue';
import Button from '@/Components/ui/atoms/Button.vue';
import Checkbox from '@/Components/ui/atoms/Checkbox.vue';
import FieldError from '@/Components/ui/atoms/FieldError.vue';
import Input from '@/Components/ui/atoms/Input.vue';
import Label from '@/Components/ui/atoms/Label.vue';
import Card from '@/Components/ui/molecules/Card.vue';
import Modal from '@/Components/ui/molecules/Modal.vue';
import PageHeader from '@/Components/ui/molecules/PageHeader.vue';

const props = defineProps({
    roles: { type: Array, default: () => [] },
    groups: { type: Array, default: () => [] },
});

const appName = usePage().props.appName;
const terbuka = ref(false);
const sedang = ref(null);
const form = useForm({ name: '', description: '', permissions: [] });

function buka(role = null) {
    sedang.value = role;
    form.name = role?.name ?? '';
    form.description = role?.description ?? '';
    form.permissions = [...(role?.permissions ?? [])];
    form.clearErrors();
    terbuka.value = true;
}

function simpan() {
    const opsi = { preserveScroll: true, onSuccess: () => { terbuka.value = false; } };
    sedang.value ? form.put(`/pengaturan/peran/${sedang.value.id}`, opsi) : form.post('/pengaturan/peran', opsi);
}

function hapus(role) {
    if (confirm(`Hapus peran ${role.name}?`)) router.delete(`/pengaturan/peran/${role.id}`, { preserveScroll: true });
}

const semuaGrup = (g) => g.items.every((i) => form.permissions.includes(i.value));
function pilihGrup(g, on) {
    const kunci = g.items.map((i) => i.value);
    form.permissions = on ? [...new Set([...form.permissions, ...kunci])] : form.permissions.filter((p) => !kunci.includes(p));
}
</script>

<template>
    <Head :title="`Peran & Izin · ${appName}`" />
    <AppLayout>
        <PageHeader title="Peran & Izin" subtitle="Atur apa saja yang boleh dilihat dan dikerjakan tiap peran. Cakupan depo diatur per pengguna.">
            <template #actions><Button @click="buka()">Tambah peran</Button></template>
        </PageHeader>

        <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
            <Card v-for="role in roles" :key="role.id">
                <template #header>
                    <div class="flex items-center justify-between gap-2">
                        <h6 class="mb-0 font-semibold">{{ role.name }} <Badge v-if="role.is_system" variant="light">bawaan</Badge></h6>
                        <small class="text-gray-500">{{ role.users }} pengguna</small>
                    </div>
                </template>
                <p class="text-xs text-gray-500">{{ role.description }}</p>
                <p class="text-xs mb-0">
                    <template v-if="role.permissions.includes('*')">Semua izin</template>
                    <template v-else-if="!role.permissions.length">Tanpa akses web (hanya aplikasi HP)</template>
                    <template v-else>{{ role.permissions.length }} izin</template>
                </p>
                <template #footer>
                    <div class="flex gap-1">
                        <Button v-if="!role.permissions.includes('*')" variant="light" @click="buka(role)">Ubah izin</Button>
                        <Button v-if="!role.is_system && !role.users" variant="outline-danger" @click="hapus(role)">Hapus</Button>
                    </div>
                </template>
            </Card>
        </div>

        <Modal :show="terbuka" :title="sedang ? `Ubah peran ${sedang.name}` : 'Tambah peran'" @close="terbuka = false">
            <form id="form-peran" class="space-y-3" @submit.prevent="simpan">
                <div class="form-grid">
                    <div><Label for="role-name">Nama peran</Label><Input id="role-name" v-model="form.name" /><FieldError v-if="form.errors.name">{{ form.errors.name }}</FieldError></div>
                    <div><Label for="role-desc">Keterangan</Label><Input id="role-desc" v-model="form.description" /></div>
                </div>
                <div v-for="g in groups" :key="g.label">
                    <label class="form-check font-semibold mb-1">
                        <Checkbox :model-value="semuaGrup(g)" @update:model-value="(on) => pilihGrup(g, on)" /><span>{{ g.label }}</span>
                    </label>
                    <div class="grid grid-cols-1 gap-1 pl-6 sm:grid-cols-2">
                        <label v-for="item in g.items" :key="item.value" class="form-check text-xs">
                            <Checkbox v-model="form.permissions" :value="item.value" /><span>{{ item.label }}</span>
                        </label>
                    </div>
                </div>
            </form>
            <template #footer>
                <Button variant="light" @click="terbuka = false">Batal</Button>
                <Button type="submit" form="form-peran" :loading="form.processing">Simpan</Button>
            </template>
        </Modal>
    </AppLayout>
</template>
