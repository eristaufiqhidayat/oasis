<script setup>
// Peta area penjualan satu depo: lihat batas semua area, gambar ulang batas satu area dengan klik titik di peta.
import { computed, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Badge from '@/Components/ui/atoms/Badge.vue';
import Button from '@/Components/ui/atoms/Button.vue';
import Checkbox from '@/Components/ui/atoms/Checkbox.vue';
import Alert from '@/Components/ui/molecules/Alert.vue';
import Card from '@/Components/ui/molecules/Card.vue';
import FilterBar from '@/Components/ui/molecules/FilterBar.vue';
import PageHeader from '@/Components/ui/molecules/PageHeader.vue';
import { useLeafletMap } from '@/Composables/useLeafletMap.js';
import { escapeHtml } from '@/Support/peta.js';

const props = defineProps({
    depot: { type: Number, default: null },
    depots: { type: Array, default: () => [] },
    territories: { type: Array, default: () => [] },
    outlets: { type: Array, default: () => [] },
    canManage: { type: Boolean, default: false },
});

const appName = usePage().props.appName;
const wadah = ref(null);
const dipilih = ref(null);
const menggambar = ref(false);
const titik = ref([]);
const pindahkanOutlet = ref(true);
const menyimpan = ref(false);

let L = null;
let lapisanArea = null;
let lapisanGambar = null;

const { peta } = useLeafletMap(wadah, {
    pusat: [-6.9, 107.6],
    zoom: 12,
    onSiap: (map, leaflet) => {
        L = leaflet;
        lapisanArea = L.layerGroup().addTo(map);
        lapisanGambar = L.layerGroup().addTo(map);
        props.outlets.forEach((o) => L.circleMarker([o.latitude, o.longitude], { radius: 3, color: '#293694', weight: 1, fillOpacity: 0.6 })
            .bindTooltip(escapeHtml(o.name)).addTo(map));
        map.on('click', (e) => {
            if (!menggambar.value) return;
            titik.value = [...titik.value, [e.latlng.lat, e.latlng.lng]];
        });
        gambarArea(true);
    },
});

// GeoJSON memakai [lng, lat]; Leaflet memakai [lat, lng].
const keLeaflet = (boundary) => {
    if (!boundary) return [];
    const polys = boundary.type === 'MultiPolygon' ? boundary.coordinates : [boundary.coordinates];
    return polys.map((rings) => rings.map((ring) => ring.map(([lng, lat]) => [lat, lng])));
};

function gambarArea(sesuaikan = false) {
    const map = peta();
    if (!map || !L) return;
    lapisanArea.clearLayers();
    const batas = [];

    props.territories.forEach((t) => {
        keLeaflet(t.boundary).forEach((rings) => {
            const poly = L.polygon(rings, { color: t.color, weight: dipilih.value === t.id ? 3 : 1.5, fillOpacity: dipilih.value === t.id ? 0.25 : 0.12 })
                .bindTooltip(escapeHtml(t.name), { sticky: true })
                .on('click', () => { if (!menggambar.value) dipilih.value = t.id; })
                .addTo(lapisanArea);
            batas.push(poly.getBounds());
        });
    });

    if (sesuaikan) {
        const semua = [...batas, ...props.outlets.map((o) => L.latLngBounds([[o.latitude, o.longitude], [o.latitude, o.longitude]]))];
        if (semua.length) map.fitBounds(semua.reduce((a, b) => a.extend(b), L.latLngBounds([])), { padding: [20, 20] });
    }
}

watch(dipilih, () => gambarArea());
watch(titik, (pts) => {
    if (!lapisanGambar) return;
    lapisanGambar.clearLayers();
    pts.forEach((p) => L.circleMarker(p, { radius: 4, color: '#dc3545' }).addTo(lapisanGambar));
    if (pts.length > 1) L.polygon(pts, { color: '#dc3545', dashArray: '4 4', fillOpacity: 0.1 }).addTo(lapisanGambar);
});

const area = computed(() => props.territories.find((t) => t.id === dipilih.value) ?? null);

function mulai() {
    titik.value = [];
    menggambar.value = true;
}

function batal() {
    menggambar.value = false;
    titik.value = [];
}

function simpan(hapus = false) {
    const ring = titik.value.map(([lat, lng]) => [lng, lat]);
    const boundary = hapus ? null : { type: 'Polygon', coordinates: [[...ring, ring[0]]] };
    menyimpan.value = true;
    router.put(`/master/area/${dipilih.value}/batas`, { boundary, assign_outlets: pindahkanOutlet.value }, {
        preserveScroll: true,
        onSuccess: () => { batal(); gambarArea(); },
        onFinish: () => { menyimpan.value = false; },
    });
}
</script>

<template>
    <Head :title="`Peta Area · ${appName}`" />
    <AppLayout breadcrumb-slug="master/area">
        <PageHeader title="Peta Area Penjualan" subtitle="Klik area di peta atau daftar untuk memilih. Gambar ulang batas dengan mengeklik titik-titik sudut di peta.">
            <template #actions>
                <Button :tag="Link" href="/master/area" variant="light">Daftar area</Button>
            </template>
        </PageHeader>

        <div class="grid grid-cols-1 gap-3 lg:grid-cols-12">
            <div class="lg:col-span-4 space-y-3">
                <Card>
                    <FilterBar url="/master/area/peta" :fields="[{ name: 'depot', label: 'Depo', type: 'select', options: depots, placeholder: '— Pilih depo —' }]" :values="{ depot }" />
                </Card>
                <Card body-class="card-body-compact">
                    <template #header><h6 class="mb-0 font-semibold">Area ({{ territories.length }})</h6></template>
                    <p v-if="!territories.length" class="text-gray-500 mb-0">Belum ada area untuk depo ini. Tambahkan di daftar area.</p>
                    <button v-for="t in territories" :key="t.id" type="button" class="settings-side-nav-link w-full text-left" :class="{ active: t.id === dipilih }" @click="dipilih = t.id">
                        <span class="inline-block h-3 w-3 rounded-sm mr-2 align-middle" :style="{ background: t.color }"></span>{{ t.name }}
                        <Badge class="ml-1" :variant="t.boundary ? 'success' : 'light'">{{ t.boundary ? 'Ada batas' : 'Belum' }}</Badge>
                    </button>
                </Card>
                <Card v-if="area && canManage">
                    <template #header><h6 class="mb-0 font-semibold">{{ area.name }}</h6></template>
                    <template v-if="!menggambar">
                        <div class="flex flex-wrap gap-2">
                            <Button @click="mulai">{{ area.boundary ? 'Gambar ulang batas' : 'Gambar batas' }}</Button>
                            <Button v-if="area.boundary" variant="outline-danger" :loading="menyimpan" @click="simpan(true)">Hapus batas</Button>
                        </div>
                    </template>
                    <template v-else>
                        <Alert variant="info">Klik peta untuk menambah titik sudut ({{ titik.length }} titik). Minimal 3 titik.</Alert>
                        <label class="form-check mb-2"><Checkbox v-model="pindahkanOutlet" /><span>Pindahkan outlet di dalam batas ke area ini</span></label>
                        <div class="flex flex-wrap gap-2">
                            <Button :disabled="titik.length < 3" :loading="menyimpan" @click="simpan()">Simpan batas</Button>
                            <Button variant="light" :disabled="!titik.length" @click="titik = titik.slice(0, -1)">Hapus titik terakhir</Button>
                            <Button variant="light" @click="batal">Batal</Button>
                        </div>
                    </template>
                </Card>
            </div>
            <div class="lg:col-span-8">
                <Card body-class="p-0">
                    <!-- Kelas mode gambar dipasang di pembungkus: :class di elemen peta akan menghapus kelas milik Leaflet. -->
                    <div class="peta-area" :class="{ 'is-menggambar': menggambar }"><div ref="wadah" class="map-box"></div></div>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
