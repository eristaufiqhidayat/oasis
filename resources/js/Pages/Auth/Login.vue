<script setup>
import { useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import Button from '@/Components/ui/atoms/Button.vue';
import Checkbox from '@/Components/ui/atoms/Checkbox.vue';
import FieldError from '@/Components/ui/atoms/FieldError.vue';
import Input from '@/Components/ui/atoms/Input.vue';
import Label from '@/Components/ui/atoms/Label.vue';
import Card from '@/Components/ui/molecules/Card.vue';
import PasswordInput from '@/Components/ui/molecules/PasswordInput.vue';

const form = useForm({ username: '', password: '', remember: false });

function masuk() {
    form.post('/login', { onFinish: () => form.reset('password') });
}
</script>

<template>
    <AuthLayout title="Masuk">
        <Card>
            <template #header>Masuk</template>

            <form class="space-y-3" @submit.prevent="masuk">
                <div>
                    <Label for="username">Username</Label>
                    <Input id="username" v-model="form.username" autocomplete="username" autofocus required />
                    <FieldError v-if="form.errors.username">{{ form.errors.username }}</FieldError>
                </div>

                <div>
                    <Label for="password">Password</Label>
                    <PasswordInput id="password" v-model="form.password" autocomplete="current-password" required />
                    <FieldError v-if="form.errors.password">{{ form.errors.password }}</FieldError>
                </div>

                <label class="form-check">
                    <Checkbox v-model="form.remember" />
                    <span>Ingat saya</span>
                </label>

                <Button type="submit" class="w-full" :loading="form.processing">Masuk</Button>

                <p class="text-center text-xs text-gray-500">Akun motoris dipakai di aplikasi HP. Lupa password? Hubungi admin.</p>
            </form>
        </Card>
    </AuthLayout>
</template>
