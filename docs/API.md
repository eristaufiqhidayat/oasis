# API Aplikasi HP Motoris — v1

Basis URL: `https://<server>/api/v1`. Semua permintaan dan respons JSON. Setelah login, kirim `Authorization: Bearer <token>`.

- Respons sukses: `{"data": …}`. Data baru → **201**; kirim ulang dengan `client_uuid` yang sama → **200** dengan data yang sudah ada (aman untuk antrean offline).
- Respons gagal: `{"error": {"code": "…", "message": "…", "field"?: "…", "fields"?: {…}}}`.

| HTTP | `error.code` | Arti |
| --- | --- | --- |
| 401 | `unauthenticated`, `invalid_credentials` | Token habis/dicabut, atau username/password salah |
| 403 | `not_salesman`, `forbidden` | Akun tidak terhubung ke motoris aktif berdepo |
| 422 | `validation_failed` | Isian tidak valid (`fields` berisi pesan per field) |
| 422 | `no_active_trip` | Belum ada muatan aktif (minta gudang memuat barang) |
| 422 | `insufficient_stock` | Stok kendaraan tidak cukup |
| 422 | `outlet_not_found`, `outlet_inactive`, `product_inactive`, `price_missing`, `trip_not_active`, `not_checked_in` | Aturan bisnis lain |
| 429 | `too_many_requests` | Lebih dari 120 permintaan/menit |

Waktu dari HP (`sold_at`, `started_at`, …) dipakai bila wajar (tidak di masa depan, tidak lebih dari 3 hari lalu); selain itu diganti waktu server.

## Autentikasi

`POST /auth/login` — `{"username", "password", "device_name"}` → `{"token", "expires_at", "must_change_password", "user"}`. Token berlaku 30 hari.
`POST /auth/logout` — mencabut token yang dipakai.
`POST /auth/password` — `{"current_password", "password", "password_confirmation"}`.

## Data awal hari kerja

`GET /bootstrap` →

```json
{
  "data": {
    "server_time": "2026-11-02T07:30:00+07:00",
    "employee": {"id": 7, "code": "BDG-M1", "name": "…", "depot": {"id": 1, "name": "Depo Bandung", "latitude": -6.91, "longitude": 107.61}},
    "attendance": {"checked_in_at": null, "checked_out_at": null},
    "trip": {"id": 12, "number": "MUAT-261102-0001", "status": "loaded", "stock": [{"product_id": 1, "quantity": 40}], "sales_total": 0, "cash_total": 0, "transactions": 0},
    "products": [{"id": 1, "sku": "OAS-G19", "name": "Oasis Galon 19 L", "unit": "galon", "units_per_case": 1, "tracks_container": true, "price": 20000}],
    "route": [{"id": 31, "code": "BDG-0001", "name": "…", "latitude": -6.9, "longitude": 107.6, "status": "active", "container_balance": 3, "sequence": 1, "visited": false}],
    "options": {"no_sale_reasons": [{"value": "stock_full", "label": "Stok masih banyak"}], "return_reasons": […], "payment_methods": […], "visit_radius_m": 150}
  }
}
```

`trip` null = belum dimuat. Harga sudah memperhitungkan harga khusus depo.

## Absensi & muatan

| Endpoint | Body | Keterangan |
| --- | --- | --- |
| `POST /attendance/check-in` | `latitude`, `longitude`, `photo_path?`, `note?` | Sekali per hari (kirim ulang → 200) |
| `POST /attendance/check-out` | `latitude`, `longitude` | |
| `POST /trip/depart` | – | `loaded` → `on_route` (otomatis juga saat transaksi pertama) |
| `POST /trip/finish` | – | Selesai berjualan, menuju depo (`returned`) |

## Outlet & kunjungan

| Endpoint | Body / query | Keterangan |
| --- | --- | --- |
| `GET /outlets` | `q?`, `latitude?`, `longitude?` | Cari outlet di depo (urut terdekat bila koordinat dikirim), maks. 50 |
| `POST /outlets` | `client_uuid`, `name`, `latitude`, `longitude`, `owner_name?`, `phone?`, `address?`, `district?`, `city?`, `channel_id?`, `photo_path?` | Outlet baru berstatus `pending`; area diisi dari polygon. Sudah bisa dikunjungi & dijual |
| `POST /visits` | `client_uuid`, `outlet_id`, `latitude?`, `longitude?`, `started_at?`, `photo_path?` | Check-in outlet; respons berisi `distance_m` ke titik outlet |
| `POST /visits/{client_uuid}/end` | `outcome?` (`sale`/`no_sale`/`closed`), `no_sale_reason?`, `note?`, `ended_at?` | Hasil otomatis `sale` bila ada penjualan di kunjungan itu |
| `POST /locations` | `points`: `[{recorded_at, latitude, longitude, accuracy_m?, battery?}]` (maks. 500) | Titik dengan waktu sama diabaikan |

## Transaksi

`POST /sales`

```json
{
  "client_uuid": "1b7c…",
  "outlet_id": 31,
  "visit_uuid": "9a2e…",
  "payment_method": "cash",
  "sold_at": "2026-11-02T09:14:00+07:00",
  "lines": [{"product_id": 1, "quantity": 3}, {"product_id": 2, "quantity": 24}]
}
```

Harga ditentukan server. Stok kendaraan berkurang; produk galon menambah saldo galon outlet. Respons: `{id, number, total, status, lines[{product_id, quantity, unit_price, line_total}]}`.

`GET /sales?date=YYYY-MM-DD` — riwayat penjualan motoris (bawaan hari ini).

`POST /returns` — `client_uuid`, `outlet_id`, `visit_uuid?`, `note?`, `lines: [{product_id, quantity, reason}]` (`damaged`/`expired`/`leaking`/`other`). Barang masuk ke kendaraan dan dihitung gudang saat bongkar.

`POST /containers` — `client_uuid`, `outlet_id`, `visit_uuid?`, `quantity`, `collected_at?`: galon kosong yang ditarik. Respons berisi `outlet_balance` terbaru.

## Alur yang disarankan di HP

1. Login → simpan token.
2. `GET /bootstrap` setiap pagi / saat aplikasi dibuka; simpan produk, harga, rute untuk offline.
3. Absen masuk → (muatan sudah dimuat gudang) → berangkat.
4. Di outlet: `POST /visits` → `POST /sales` / `/returns` / `/containers` → `POST /visits/{uuid}/end`.
5. Kirim GPS berkala ke `POST /locations` (batch).
6. Antre semua POST saat offline; kirim ulang dengan `client_uuid` yang sama sampai dapat 200/201. Error 422 jangan diulang otomatis — tampilkan pesannya.
7. `POST /trip/finish` saat kembali ke depo, lalu absen pulang.
