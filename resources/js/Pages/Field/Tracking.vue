<script setup>
// Posisi terakhir semua motoris satu depo, atau jejak GPS + titik kunjungan satu motoris pada tanggal terpilih.
import { ref } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Badge from '@/Components/ui/atoms/Badge.vue';
import Card from '@/Components/ui/molecules/Card.vue';
import FilterBar from '@/Components/ui/molecules/FilterBar.vue';
import PageHeader from '@/Components/ui/molecules/PageHeader.vue';
import { useLeafletMap } from '@/Composables/useLeafletMap.js';
import { escapeHtml } from '@/Support/peta.js';

const props = defineProps({
    filters: { type: Object, required: true },
    depots: { type: Array, default: () => [] },
    salesmen: { type: Array, default: () => [] },
    track: { type: Array, default: () => [] },
    visits: { type: Array, default: () => [] },
});

const appName = usePage().props.appName;
const wadah = ref(null);

useLeafletMap(wadah, {
    zoom: 12,
    onSiap: (map, L) => {
        const batas = [];

        if (props.filters.motoris) {
            if (props.track.length) {
                const garis = props.track.map(([lat, lng]) => [lat, lng]);
                L.polyline(garis, { color: '#0d6efd', weight: 3 }).addTo(map);
                L.circleMarker(garis[0], { radius: 6, color: '#198754', fillOpacity: 1 }).bindTooltip(`Mulai ${props.track[0][2]}`).addTo(map);
                L.circleMarker(garis.at(-1), { radius: 6, color: '#dc3545', fillOpacity: 1 }).bindTooltip(`Terakhir ${props.track.at(-1)[2]}`).addTo(map);
                batas.push(...garis);
            }
            props.visits.forEach((v, i) => {
                L.circleMarker([v.lat, v.lng], { radius: 7, color: v.sale ? '#18a6b1' : '#6b7280', fillOpacity: 0.85 })
                    .bindPopup(`<strong>${i + 1}. ${escapeHtml(v.outlet)}</strong><br>${v.time} · ${escapeHtml(v.outcome)}`).addTo(map);
                batas.push([v.lat, v.lng]);
            });
        } else {
            props.salesmen.filter((s) => s.last).forEach((s) => {
                L.circleMarker([s.last.lat, s.last.lng], { radius: 8, color: '#293694', fillOpacity: 0.9 })
                    .bindTooltip(`${escapeHtml(s.label)} · ${s.last.time}${s.last.battery ? ` · 🔋${s.last.battery}%` : ''}`, { permanent: true, direction: 'top' }).addTo(map);
                batas.push([s.last.lat, s.last.lng]);
            });
        }

        if (batas.length) map.fitBounds(batas, { padding: [30, 30], maxZoom: 16 });
    },
});
</script>

<template>
    <Head :title="`Pelacakan GPS · ${appName}`" />
    <AppLayout>
        <PageHeader title="Pelacakan GPS" subtitle="Tanpa memilih motoris: posisi terakhir semua motoris. Pilih motoris untuk melihat jejak perjalanan dan urutan kunjungannya." />
        <div class="grid grid-cols-1 gap-3 lg:grid-cols-12">
            <div class="space-y-3 lg:col-span-4">
                <Card>
                    <FilterBar url="/pelacakan" :fields="[
                        { name: 'tanggal', label: 'Tanggal', type: 'date' },
                        { name: 'depot', label: 'Depo', type: 'select', options: depots, placeholder: '— Pilih —' },
                        { name: 'motoris', label: 'Motoris', type: 'select', options: salesmen.map((s) => ({ value: s.value, label: s.label })) },
                    ]" :values="filters" />
                </Card>
                <Card body-class="card-body-compact">
                    <template #header><h6 class="mb-0 font-semibold">{{ filters.motoris ? `Kunjungan (${visits.length})` : 'Posisi terakhir' }}</h6></template>
                    <template v-if="filters.motoris">
                        <div v-for="(v, i) in visits" :key="i" class="stat-row"><span>{{ i + 1 }}. {{ v.outlet }}</span><span><small class="text-gray-500">{{ v.time }}</small> <Badge :variant="v.sale ? 'success' : 'light'">{{ v.outcome }}</Badge></span></div>
                        <p v-if="!visits.length" class="text-gray-500 mb-0">Belum ada kunjungan.</p>
                    </template>
                    <template v-else>
                        <div v-for="s in salesmen" :key="s.value" class="stat-row"><span>{{ s.label }}</span><small class="text-gray-500">{{ s.last ? s.last.time : 'tidak ada sinyal' }}</small></div>
                    </template>
                </Card>
            </div>
            <div class="lg:col-span-8">
                <Card body-class="p-0"><div ref="wadah" class="map-box"></div></Card>
            </div>
        </div>
    </AppLayout>
</template>
