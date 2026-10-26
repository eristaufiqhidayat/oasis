<script setup>
// Baris filter halaman daftar. fields: [{ name, label, type: 'date'|'month'|'select'|'combobox'|'search', options?, placeholder? }].
// placeholder: false = select tanpa pilihan "Semua". Pilihan select/tanggal langsung diterapkan; kolom cari diterapkan dengan Enter atau tombol Tampilkan.
import { reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import Button from '@/Components/ui/atoms/Button.vue';
import Input from '@/Components/ui/atoms/Input.vue';
import Label from '@/Components/ui/atoms/Label.vue';
import Select from '@/Components/ui/atoms/Select.vue';
import Combobox from '@/Components/ui/molecules/Combobox.vue';
import { bersihkanQuery } from '@/Support/query.js';

const props = defineProps({
    url: { type: String, required: true },
    fields: { type: Array, required: true },
    values: { type: Object, default: () => ({}) },
    // Query lain yang ikut dipertahankan (mis. tab aktif).
    keep: { type: Object, default: () => ({}) },
    // Bawaan false: halaman dipasang ulang dengan data baru, jadi form/peta yang dibangun dari props ikut segar.
    preserveState: { type: Boolean, default: false },
});

const nilai = reactive(Object.fromEntries(props.fields.map((f) => [f.name, props.values[f.name] ?? ''])));

function terapkan() {
    router.get(props.url, bersihkanQuery({ ...props.keep, ...nilai }), { preserveScroll: true, preserveState: props.preserveState });
}
</script>

<template>
    <form class="filter-bar" @submit.prevent="terapkan">
        <div v-for="f in fields" :key="f.name" class="filter-field" :class="{ 'filter-field-wide': f.type === 'search' || f.type === 'combobox' }">
            <Label :for="`filter-${f.name}`">{{ f.label }}</Label>
            <Select v-if="f.type === 'select'" :id="`filter-${f.name}`" v-model="nilai[f.name]" :options="f.options" :placeholder="f.placeholder === false ? null : (f.placeholder ?? 'Semua')" @update:model-value="terapkan" />
            <Combobox v-else-if="f.type === 'combobox'" :id="`filter-${f.name}`" v-model="nilai[f.name]" :options="f.options" :placeholder="f.placeholder ?? 'Semua'" @update:model-value="terapkan" />
            <Input v-else-if="f.type === 'date' || f.type === 'month'" :id="`filter-${f.name}`" v-model="nilai[f.name]" :type="f.type" @change="terapkan" />
            <Input v-else :id="`filter-${f.name}`" v-model="nilai[f.name]" type="search" :placeholder="f.placeholder ?? 'Cari...'" />
        </div>
        <Button type="submit" variant="outline-secondary">Tampilkan</Button>
        <slot />
    </form>
</template>
