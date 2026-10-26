<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Card from '@/Components/ui/molecules/Card.vue';
import FilterBar from '@/Components/ui/molecules/FilterBar.vue';
import PageHeader from '@/Components/ui/molecules/PageHeader.vue';
import StatCard from '@/Components/ui/molecules/StatCard.vue';
import { angka, rupiah } from '@/Support/format.js';

const props = defineProps({
    depot: { type: Number, default: null },
    depots: { type: Array, default: () => [] },
    today: { type: String, required: true },
    kpi: { type: Object, required: true },
    trend: { type: Array, default: () => [] },
    topProducts: { type: Array, default: () => [] },
    links: { type: Object, default: () => ({}) },
});

const appName = usePage().props.appName;
const user = computed(() => usePage().props.auth?.user);
const maks = computed(() => Math.max(1, ...props.trend.map((t) => t.total)));
const strikeRate = computed(() => (props.kpi.visits ? Math.round((props.kpi.effective_calls / props.kpi.visits) * 100) : 0));
const tanggalPanjang = new Date(`${props.today}T00:00:00`).toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
const hariPendek = (tgl) => new Date(`${tgl}T00:00:00`).toLocaleDateString('id-ID', { day: 'numeric', month: 'short' });
</script>

<template>
    <Head :title="`Beranda · ${appName}`" />
    <AppLayout>
        <PageHeader :title="`Selamat datang, ${user?.name ?? ''}`" :subtitle="`Ringkasan hari ini, ${tanggalPanjang}`">
            <template v-if="depots.length > 1" #actions>
                <FilterBar url="/home" :fields="[{ name: 'depot', label: 'Depo', type: 'select', options: depots }]" :values="{ depot }" />
            </template>
        </PageHeader>

        <div class="stat-grid mb-3">
            <StatCard label="Penjualan hari ini" :value="rupiah(kpi.revenue)" :hint="`${angka(kpi.transactions)} transaksi`" icon="shopping-cart" variant="success" />
            <StatCard label="Kunjungan" :value="angka(kpi.visits)" :hint="`${angka(kpi.effective_calls)} effective call · ${strikeRate}%`" icon="map-pin" />
            <StatCard label="Motoris hadir" :value="`${kpi.attendance} / ${kpi.salesmen}`" :hint="`${kpi.trips_on_route} muatan di lapangan`" icon="user-check" />
            <StatCard label="Perlu tindakan" :value="angka(kpi.trips_to_settle + kpi.outlets_pending)" :hint="`${kpi.trips_to_settle} setoran · ${kpi.outlets_pending} outlet baru`" icon="bell" :variant="kpi.trips_to_settle + kpi.outlets_pending ? 'warning' : 'success'" />
        </div>

        <div class="grid grid-cols-1 gap-3 lg:grid-cols-3">
            <Card class="lg:col-span-2">
                <template #header><h6 class="mb-0 font-semibold">Penjualan 14 hari terakhir</h6></template>
                <div class="bar-chart" role="img" aria-label="Grafik penjualan harian">
                    <div v-for="t in trend" :key="t.date" class="bar-chart-col" :title="`${hariPendek(t.date)}: ${rupiah(t.total)}`">
                        <div class="bar-chart-bar" :style="{ height: `${Math.max(2, (t.total / maks) * 100)}%` }"></div>
                        <span class="bar-chart-label">{{ hariPendek(t.date) }}</span>
                    </div>
                </div>
            </Card>

            <Card>
                <template #header><h6 class="mb-0 font-semibold">Produk terlaris bulan ini</h6></template>
                <p v-if="!topProducts.length" class="text-center text-gray-500 py-4 mb-0">Belum ada penjualan.</p>
                <div v-for="p in topProducts" :key="p.name" class="stat-row">
                    <span>{{ p.name }}<br><small class="text-gray-500">{{ angka(p.qty) }} terjual</small></span>
                    <strong>{{ rupiah(p.total) }}</strong>
                </div>
            </Card>
        </div>

        <Card v-if="links.trips || links.settlements || links.outlets" class="mt-3">
            <div class="flex flex-wrap gap-4 text-sm">
                <Link v-if="links.trips" class="link-strong" href="/muatan">Muatan hari ini →</Link>
                <Link v-if="links.settlements" class="link-strong" href="/setoran">Setoran & rekonsiliasi →</Link>
                <Link v-if="links.outlets && kpi.outlets_pending" class="link-strong" href="/outlet-baru">{{ kpi.outlets_pending }} outlet baru menunggu verifikasi →</Link>
            </div>
        </Card>
    </AppLayout>
</template>
