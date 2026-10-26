// Perhitungan grafik yang dipakai komponen chart (tanpa ketergantungan Vue, bisa diuji langsung).

// Palet kategori (identitas seri), urutan tetap — sudah lolos uji buta warna (CVD) untuk pasangan bersebelahan
// di latar putih. Warna ke-3 s.d. 5 kontrasnya < 3:1, jadi selalu ada legenda + nilai + tampilan tabel.
export const PALET = ['var(--chart-1)', 'var(--chart-2)', 'var(--chart-3)', 'var(--chart-4)', 'var(--chart-5)'];

export const warnaSeri = (i) => PALET[i % PALET.length];

// Batas atas sumbu yang "bulat" (1, 2, 2,5, 5 × 10^n) dan tick-nya.
export function tickBulat(maks, jumlah = 4) {
    if (!(maks > 0)) {
        return { maks: 1, ticks: [0, 1] };
    }

    const kasar = maks / jumlah;
    const pangkat = 10 ** Math.floor(Math.log10(kasar));
    const langkah = [1, 2, 2.5, 5, 10].map((k) => k * pangkat).find((k) => k >= kasar);
    const atas = Math.ceil(maks / langkah) * langkah;
    const ticks = [];

    for (let v = 0; v <= atas + langkah / 2; v += langkah) {
        ticks.push(Math.round(v * 1e6) / 1e6);
    }

    return { maks: atas, ticks };
}

const ringkasAngka = (n, d) => new Intl.NumberFormat('id-ID', { maximumFractionDigits: d }).format(n);

// Rupiah ringkas untuk sumbu & label: Rp 1,2 M · Rp 300 jt · Rp 45 rb · Rp 900.
export function rupiahRingkas(n) {
    const v = Math.abs(Number(n) || 0);
    const tanda = Number(n) < 0 ? '−' : '';

    if (v >= 1e12) return `${tanda}Rp ${ringkasAngka(v / 1e12, 1)} T`;
    if (v >= 1e9) return `${tanda}Rp ${ringkasAngka(v / 1e9, v >= 1e10 ? 1 : 2)} M`;
    if (v >= 1e6) return `${tanda}Rp ${ringkasAngka(v / 1e6, v >= 1e8 ? 0 : 1)} jt`;
    if (v >= 1e3) return `${tanda}Rp ${ringkasAngka(v / 1e3, 0)} rb`;

    return `${tanda}Rp ${ringkasAngka(v, 0)}`;
}

// Angka ringkas tanpa mata uang: 1,2 rb · 3,4 jt.
export function angkaRingkas(n) {
    const v = Math.abs(Number(n) || 0);

    if (v >= 1e6) return `${ringkasAngka(n / 1e6, 1)} jt`;
    if (v >= 1e4) return `${ringkasAngka(n / 1e3, 1)} rb`;

    return ringkasAngka(n, 0);
}

// Persentase bagian dari total (bilangan bulat, 0 bila total 0).
export const bagian = (nilai, total) => (total > 0 ? (nilai / total) * 100 : 0);

// Selisih persen terhadap periode sebelumnya; null bila tidak ada pembanding.
export const selisihPersen = (kini, lalu) => (lalu > 0 ? ((kini - lalu) / lalu) * 100 : null);

// Path SVG potongan donut (sudut dalam radian, 0 = arah jam 12, searah jarum jam).
export function busurDonut(cx, cy, rLuar, rDalam, mulai, akhir) {
    const titik = (r, a) => [cx + r * Math.sin(a), cy - r * Math.cos(a)];
    const besar = akhir - mulai > Math.PI ? 1 : 0;
    const [x1, y1] = titik(rLuar, mulai);
    const [x2, y2] = titik(rLuar, akhir);
    const [x3, y3] = titik(rDalam, akhir);
    const [x4, y4] = titik(rDalam, mulai);
    const f = (n) => n.toFixed(2);

    return `M${f(x1)} ${f(y1)}A${rLuar} ${rLuar} 0 ${besar} 1 ${f(x2)} ${f(y2)}L${f(x3)} ${f(y3)}A${rDalam} ${rDalam} 0 ${besar} 0 ${f(x4)} ${f(y4)}Z`;
}

// Potongan donut dengan celah tetap (px di keliling luar) antar potongan.
export function potonganDonut(nilai, { cx = 80, cy = 80, rLuar = 76, rDalam = 52, celahPx = 2 } = {}) {
    const total = nilai.reduce((a, b) => a + b, 0);
    const ada = nilai.filter((v) => v > 0).length;

    if (!total) {
        return [];
    }

    const celah = ada > 1 ? celahPx / rLuar : 0;
    let sudut = 0;

    return nilai.map((v, i) => {
        const lebar = (v / total) * Math.PI * 2;
        const mulai = sudut + celah / 2;
        const akhir = sudut + lebar - celah / 2;
        sudut += lebar;

        if (v <= 0) {
            return { index: i, path: null };
        }

        // Lingkaran penuh tidak bisa digambar satu busur: bagi dua.
        const path = ada === 1
            ? busurDonut(cx, cy, rLuar, rDalam, 0, Math.PI) + busurDonut(cx, cy, rLuar, rDalam, Math.PI, Math.PI * 2 - 1e-6)
            : busurDonut(cx, cy, rLuar, rDalam, mulai, Math.max(mulai + 0.001, akhir));

        return { index: i, path };
    });
}

// Path kolom dengan ujung data membulat 4px dan dasar persegi (dari baseline ke atas).
export function pathKolom(x, y, lebar, tinggi, radius = 4) {
    if (tinggi <= 0) {
        return '';
    }

    const r = Math.min(radius, lebar / 2, tinggi);
    const bawah = y + tinggi;

    return `M${x} ${bawah}V${y + r}Q${x} ${y} ${x + r} ${y}H${x + lebar - r}Q${x + lebar} ${y} ${x + lebar} ${y + r}V${bawah}Z`;
}
