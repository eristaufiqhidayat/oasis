<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import FilterBar from '@/Components/ui/molecules/FilterBar.vue';
import PageHeader from '@/Components/ui/molecules/PageHeader.vue';
import DataTable from '@/Components/ui/organisms/DataTable.vue';

defineProps({
    logs: { type: Object, required: true },
    q: { type: String, default: '' },
});

const appName = usePage().props.appName;
</script>

<template>
    <Head :title="`Log Aktivitas · ${appName}`" />
    <AppLayout>
        <PageHeader title="Log Aktivitas" subtitle="Jejak perubahan data penting: siapa, kapan, apa." />
        <DataTable :columns="{ at: 'Waktu', user: 'Pengguna', action: 'Aksi', description: 'Keterangan', ip: 'IP' }" :rows="logs.data" :total="logs.total" :links="logs.links" row-key="id">
            <template #header><FilterBar url="/pengaturan/log" :fields="[{ name: 'q', label: 'Cari', type: 'search' }]" :values="{ q }" /></template>
        </DataTable>
    </AppLayout>
</template>
