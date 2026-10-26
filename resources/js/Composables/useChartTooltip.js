import { onBeforeUnmount, onMounted, reactive, ref } from 'vue';

// Lebar elemen (px) yang ikut berubah saat ukuran kartu berubah — grafik SVG digambar dengan piksel nyata
// supaya batang tetap tipis (≤ 24px) dan teks sumbu tidak ikut membesar/mengecil.
export function useElementWidth(fallback = 600) {
    const el = ref(null);
    const width = ref(fallback);
    let observer = null;

    onMounted(() => {
        if (!el.value) return;
        width.value = el.value.clientWidth || fallback;
        if (typeof ResizeObserver !== 'undefined') {
            observer = new ResizeObserver(([entry]) => {
                width.value = Math.max(240, Math.round(entry.contentRect.width));
            });
            observer.observe(el.value);
        }
    });

    onBeforeUnmount(() => observer?.disconnect());

    return { el, width };
}

// Status tooltip satu grafik. show(event | elemen, judul, baris[]) — baris: { label, value, color? }.
export function useChartTooltip() {
    const box = ref(null);
    const tip = reactive({ visible: false, x: 0, y: 0, title: '', rows: [] });

    function show(source, title, rows) {
        const host = box.value;
        if (!host) return;
        const area = host.getBoundingClientRect();
        let x;
        let y;

        if (source?.clientX !== undefined && source.clientX !== 0) {
            x = source.clientX - area.left;
            y = source.clientY - area.top;
        } else {
            // Fokus keyboard: pakai tengah atas elemen yang difokus.
            const r = (source?.currentTarget ?? source?.target ?? source).getBoundingClientRect();
            x = r.left + r.width / 2 - area.left;
            y = r.top - area.top;
        }

        Object.assign(tip, { visible: true, x, y, title, rows });
    }

    const hide = () => {
        tip.visible = false;
    };

    return { box, tip, show, hide };
}
