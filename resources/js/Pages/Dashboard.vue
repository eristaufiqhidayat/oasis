<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Card from '@/Components/ui/molecules/Card.vue';
import FilterBar from '@/Components/ui/molecules/FilterBar.vue';
import PageHeader from '@/Components/ui/molecules/PageHeader.vue';
import StatCard from '@/Components/ui/molecules/StatCard.vue';
import ColumnChart from '@/Components/ui/molecules/ColumnChart.vue';
import BarList from '@/Components/ui/molecules/BarList.vue';
import DonutChart from '@/Components/ui/molecules/DonutChart.vue';
import StackedBar from '@/Components/ui/molecules/StackedBar.vue';
import ChartCard from '@/Components/ui/organisms/ChartCard.vue';
import { angka, desimal, rupiah } from '@/Support/format.js';
import { bagian, rupiahRingkas, selisihPersen } from '@/Support/chart.js';

const props = defineProps({
    depot: { type: Number, default: null },
    depots: { type: Array, default: () => [] },
    today: { type: String, required: true },
    period: { type: String, required: true },
    periods: { type: Array, default: () => [] },
    range: { type: Object, required: true },
    charts: { type: Object, required: true },
    kpi: { type: Object, required: true },
    links: { type: Object, default: () => ({}) },
});

const appName = usePage().props.appName;
const user = computed(() => usePage().props.auth?.user);
const tgl = (s, opsi) => new Date(`${s}T00:00:00`).toLocaleDateString('id-ID', opsi);
const tanggalPanjang = tgl(props.today, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
const rentang = `${tgl(props.range.from, { day: 'numeric', month: 'short' })} – ${tgl(props.range.to, { day: 'numeric', month: 'short', year: 'numeric' })}`;

const filterFields = computed(() => [
    { name: 'periode', label: 'Periode', type: 'select', options: props.periods, placeholder: false },
    ...(props.depots.length > 1 ? [{ name: 'depot', label: 'Depo', type: 'select', options: props.depots }] : []),
]);

const s = computed(() => props.charts.summary);
const strikeRate = computed(() => (props.kpi.visits ? Math.round((props.kpi.effective_calls / props.kpi.visits) * 100) : 0));
const delta = computed(() => selisihPersen(s.value.revenue, s.value.previous_revenue));
const rataHarian = computed(() => Math.round(s.value.revenue / Math.max(1, s.value.days)));
const ecRate = computed(() => bagian(s.value.effective_calls, s.value.visits));

const daily = computed(() => props.charts.daily.map((d) => ({
    key: d.date,
    label: tgl(d.date, { weekday: 'short', day: 'numeric', month: 'short' }),
    short: tgl(d.date, { day: 'numeric', month: 'short' }),
    value: d.total,
    detail: [{ label: 'Transaksi', value: angka(d.transactions) }],
})));

const hours = computed(() => props.charts.hours.map((h) => ({
    key: h.hour,
    label: `${String(h.hour).padStart(2, '0')}.00–${String(h.hour).padStart(2, '0')}.59`,
    short: String(h.hour).padStart(2, '0'),
    value: h.transactions,
    detail: [{ label: 'Omzet', value: rupiah(h.total) }],
})));
const jamTersibuk = computed(() => props.charts.hours.reduce((a, h) => (h.transactions > (a?.transactions ?? -1) ? h : a), null));

const areaJudul = computed(() => (props.charts.byArea.dimension === 'area' ? 'Omzet per area' : 'Omzet per wilayah'));
const areaItems = computed(() => props.charts.byArea.items.map((a) => ({
    key: a.name, label: a.name, value: a.total,
    sub: a.depots ? `${a.depots} depo` : `${angka(a.transactions)} trx`,
})));
const salesmen = computed(() => props.charts.topSalesmen.map((m) => ({ key: `${m.name}-${m.depot}`, label: m.name, sub: m.depot, value: m.total })));
// Semua produk bermerek Oasis: awalan "Oasis" dibuang di legenda supaya nama yang membedakan tidak terpotong.
const products = computed(() => props.charts.products.map((p) => ({ label: p.name.replace(/^Oasis\s+/i, ''), value: p.total, sub: `${angka(p.qty)} terjual` })));
const outcomes = computed(() => props.charts.outcomes.map((o) => ({ label: o.label, value: o.value })));
const payments = computed(() => props.charts.payments.map((p) => ({ label: p.label, value: p.value })));

// Baris tampilan tabel tiap grafik.
const tabelHarian = computed(() => props.charts.daily.map((d) => ({ tanggal: tgl(d.date, { weekday: 'short', day: 'numeric', month: 'short' }), omzet: rupiah(d.total), transaksi: angka(d.transactions) })));
const tabelArea = computed(() => props.charts.byArea.items.map((a) => ({ nama: a.name, omzet: rupiah(a.total), transaksi: angka(a.transactions), porsi: `${desimal(bagian(a.total, s.value.revenue))}%` })));
const tabelMotoris = computed(() => props.charts.topSalesmen.map((m, i) => ({ no: i + 1, nama: m.name, depo: m.depot, omzet: rupiah(m.total), transaksi: angka(m.transactions) })));
const tabelProduk = computed(() => props.charts.products.map((p) => ({ produk: p.name, qty: angka(p.qty), omzet: rupiah(p.total), porsi: `${desimal(bagian(p.total, s.value.revenue))}%` })));
const tabelKunjungan = computed(() => [
    ...props.charts.outcomes.map((o) => ({ kelompok: 'Hasil kunjungan', nama: o.label, nilai: angka(o.value) })),
    ...props.charts.payments.map((p) => ({ kelompok: 'Cara bayar', nama: p.label, nilai: `${rupiah(p.value)} (${angka(p.transactions)} trx)` })),
]);
const tabelJam = computed(() => props.charts.hours.map((h) => ({ jam: `${String(h.hour).padStart(2, '0')}.00`, transaksi: angka(h.transactions), omzet: rupiah(h.total) })));
</script>

<template>
    <Head :title="`Beranda · ${appName}`" />
    <AppLayout>
        <PageHeader :title="`Selamat datang, ${user?.name ?? ''}`" :subtitle="tanggalPanjang">
            <template #actions>
                <FilterBar url="/home" :fields="filterFields" :values="{ periode: period, depot }" />
            </template>
        </PageHeader>

        <!-- Kondisi hari ini -->
        <div class="stat-grid mb-3">
            <StatCard label="Penjualan hari ini" :value="rupiah(kpi.revenue)" :hint="`${angka(kpi.transactions)} transaksi`" icon="shopping-cart" variant="success" />
            <StatCard label="Kunjungan hari ini" :value="angka(kpi.visits)" :hint="`${angka(kpi.effective_calls)} effective call · ${strikeRate}%`" icon="map-pin" />
            <StatCard label="Motoris hadir" :value="`${kpi.attendance} / ${kpi.salesmen}`" :hint="`${kpi.trips_on_route} muatan di lapangan`" icon="user-check" />
            <StatCard label="Perlu tindakan" :value="angka(kpi.trips_to_settle + kpi.outlets_pending)" :hint="`${kpi.trips_to_settle} setoran · ${kpi.outlets_pending} outlet baru`" icon="bell" :variant="kpi.trips_to_settle + kpi.outlets_pending ? 'warning' : 'success'" />
        </div>

        <div class="grid grid-cols-1 gap-3 xl:grid-cols-3">
            <!-- Tren omzet periode: angka utama halaman -->
            <ChartCard
                class="xl:col-span-2"
                title="Omzet harian"
                :subtitle="rentang"
                :columns="{ tanggal: 'Tanggal', omzet: 'Omzet', transaksi: 'Transaksi' }"
                :rows="tabelHarian"
                :numeric="['omzet', 'transaksi']"
            >
                <template #summary>
                    <div class="mb-3 flex flex-wrap items-end justify-between gap-3">
                        <div>
                            <p class="hero-figure">{{ rupiah(s.revenue) }}</p>
                            <div class="hero-meta">
                                <span v-if="delta !== null" :class="delta >= 0 ? 'text-variance-plus' : 'text-variance-minus'">
                                    {{ delta >= 0 ? '▲' : '▼' }} {{ desimal(Math.abs(delta)) }}%
                                    <span class="font-normal text-gray-500">vs {{ s.days }} hari sebelumnya</span>
                                </span>
                                <span>{{ angka(s.transactions) }} transaksi</span>
                                <span>{{ s.active_salesmen }} motoris</span>
                            </div>
                        </div>
                        <dl class="grid grid-cols-3 gap-4 text-xs">
                            <div><dt class="text-gray-500">Rata-rata / hari</dt><dd class="mb-0 font-semibold text-gray-800">{{ rupiahRingkas(rataHarian) }}</dd></div>
                            <div><dt class="text-gray-500">Per transaksi</dt><dd class="mb-0 font-semibold text-gray-800">{{ rupiahRingkas(s.average_ticket) }}</dd></div>
                            <div><dt class="text-gray-500">Effective call</dt><dd class="mb-0 font-semibold text-gray-800">{{ desimal(ecRate) }}%</dd></div>
                        </dl>
                    </div>
                </template>
                <ColumnChart :items="daily" :format="rupiah" :axis-format="rupiahRingkas" series-label="Omzet" :highlight-key="range.to" highlight-label="Hari terakhir" average :height="240" />
            </ChartCard>

            <ChartCard
                title="Komposisi produk"
                subtitle="Porsi omzet per produk"
                :columns="{ produk: 'Produk', qty: 'Qty', omzet: 'Omzet', porsi: 'Porsi' }"
                :rows="tabelProduk"
                :numeric="['qty', 'omzet', 'porsi']"
            >
                <DonutChart :segments="products" :format="rupiahRingkas" center-label="Omzet" :center-value="rupiahRingkas(s.revenue)" />
            </ChartCard>

            <ChartCard
                :title="areaJudul"
                :subtitle="rentang"
                :columns="{ nama: charts.byArea.dimension === 'area' ? 'Area' : 'Wilayah', omzet: 'Omzet', transaksi: 'Transaksi', porsi: 'Porsi' }"
                :rows="tabelArea"
                :numeric="['omzet', 'transaksi', 'porsi']"
            >
                <BarList :items="areaItems" :format="rupiahRingkas" empty-text="Belum ada penjualan." />
            </ChartCard>

            <ChartCard
                title="Motoris teratas"
                subtitle="10 omzet tertinggi"
                :columns="{ no: '#', nama: 'Motoris', depo: 'Depo', omzet: 'Omzet', transaksi: 'Transaksi' }"
                :rows="tabelMotoris"
                :numeric="['omzet', 'transaksi']"
            >
                <BarList :items="salesmen" :format="rupiahRingkas" empty-text="Belum ada penjualan." />
            </ChartCard>

            <ChartCard
                title="Kunjungan & pembayaran"
                :subtitle="`${angka(s.visits)} kunjungan · ${desimal(ecRate)}% beli`"
                :columns="{ kelompok: 'Kelompok', nama: 'Rincian', nilai: 'Jumlah' }"
                :rows="tabelKunjungan"
                :numeric="['nilai']"
            >
                <StackedBar title="Hasil kunjungan" :segments="outcomes" :format="angka" />
                <StackedBar title="Cara bayar (omzet)" :segments="payments" :format="rupiahRingkas" />
            </ChartCard>

            <ChartCard
                class="xl:col-span-3"
                title="Jam transaksi"
                :subtitle="jamTersibuk?.transactions ? `Paling ramai pukul ${String(jamTersibuk.hour).padStart(2, '0')}.00` : 'Jumlah transaksi per jam'"
                :columns="{ jam: 'Jam', transaksi: 'Transaksi', omzet: 'Omzet' }"
                :rows="tabelJam"
                :numeric="['transaksi', 'omzet']"
            >
                <ColumnChart :items="hours" :format="angka" series-label="Transaksi" :highlight-key="jamTersibuk?.hour" :height="180" />
            </ChartCard>
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
