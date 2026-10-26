// Format angka bahasa Indonesia (pemisah ribuan titik), sama dengan number_format() di Blade.
export const angka = (n) => new Intl.NumberFormat('id-ID').format(n);

// Satu desimal dengan koma (padanan number_format($n, 1, ',', '.')).
export const desimal = (n) => new Intl.NumberFormat('id-ID', { minimumFractionDigits: 1, maximumFractionDigits: 1 }).format(n);

// Desimal dengan nol di akhir dibuang (padanan rtrim(number_format($n, $maks, ',', '.'), '0'), ',')).
export const angkaRingkas = (n, maks = 2) => new Intl.NumberFormat('id-ID', { maximumFractionDigits: maks }).format(n);

// Rupiah tanpa desimal: Rp 1.250.000 (null → "-").
export const rupiah = (n) => (n === null || n === undefined || n === '' ? '-' : `${Number(n) < 0 ? '−' : ''}Rp ${angka(Math.abs(Number(n)))}`);

// Persen satu desimal: 87,5% (null → "-").
export const persen = (n) => (n === null || n === undefined ? '-' : `${desimal(n)}%`);

// Varian badge pencapaian: ≥ 100% hijau, ≥ 80% kuning, di bawahnya merah.
export const varianCapaian = (p) => (p === null || p === undefined ? 'light' : p >= 100 ? 'success' : p >= 80 ? 'warning' : 'danger');

// Qty dengan pecahan dus bila isi per dus > 1: "2 dus + 4" (hanya informasi tambahan).
export const qtyDus = (qty, isi) => (isi > 1 && qty >= isi ? `${Math.floor(qty / isi)} dus${qty % isi ? ` + ${qty % isi}` : ''}` : '');
