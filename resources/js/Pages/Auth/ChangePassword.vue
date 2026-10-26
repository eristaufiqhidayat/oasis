<script setup>
import { Link, router, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import Button from '@/Components/ui/atoms/Button.vue';
import FieldError from '@/Components/ui/atoms/FieldError.vue';
import Label from '@/Components/ui/atoms/Label.vue';
import Alert from '@/Components/ui/molecules/Alert.vue';
import Card from '@/Components/ui/molecules/Card.vue';
import PasswordInput from '@/Components/ui/molecules/PasswordInput.vue';

// forced: akun dengan password sementara dari admin harus menggantinya sebelum memakai aplikasi.
defineProps({ forced: { type: Boolean, default: false } });

const form = useForm({ current_password: '', password: '', password_confirmation: '' });

function simpan() {
    form.put('/ganti-password', { onFinish: () => form.reset() });
}
</script>

<template>
    <AuthLayout title="Ganti Password">
        <Card>
            <template #header>Ganti Password</template>

            <form class="space-y-3" @submit.prevent="simpan">
                <Alert v-if="forced" variant="info">Anda masuk dengan password sementara. Buat password baru sebelum melanjutkan.</Alert>

                <div>
                    <Label for="current_password">Password saat ini</Label>
                    <PasswordInput id="current_password" v-model="form.current_password" autocomplete="current-password" required />
                    <FieldError v-if="form.errors.current_password">{{ form.errors.current_password }}</FieldError>
                </div>
                <div>
                    <Label for="password">Password baru (minimal 8 karakter)</Label>
                    <PasswordInput id="password" v-model="form.password" autocomplete="new-password" required />
                    <FieldError v-if="form.errors.password">{{ form.errors.password }}</FieldError>
                </div>
                <div>
                    <Label for="password_confirmation">Ulangi password baru</Label>
                    <PasswordInput id="password_confirmation" v-model="form.password_confirmation" autocomplete="new-password" required />
                </div>

                <div class="flex items-center justify-between gap-2">
                    <Button type="submit" :loading="form.processing">Simpan password</Button>
                    <Button v-if="!forced" :tag="Link" variant="light" href="/home">Batal</Button>
                </div>
            </form>

            <template #footer>
                <Button variant="outline-secondary" @click="router.post('/logout')">Keluar</Button>
            </template>
        </Card>
    </AuthLayout>
</template>
