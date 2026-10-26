<?php

return [
    // Masa berlaku token HP (hari) sejak login.
    'token_days' => (int) env('MOBILE_TOKEN_DAYS', 30),
    // Batas permintaan per menit per token.
    'rate_per_minute' => (int) env('MOBILE_RATE_PER_MINUTE', 120),
    // Kunjungan yang check-in lebih jauh dari ini (meter) ditandai di laporan.
    'visit_radius_m' => (int) env('MOBILE_VISIT_RADIUS_M', 150),
    // Alasan tidak beli yang bisa dipilih di HP.
    'no_sale_reasons' => [
        'stock_full' => 'Stok masih banyak',
        'owner_absent' => 'Pemilik tidak ada',
        'no_money' => 'Belum ada uang',
        'competitor' => 'Pakai merek lain',
        'other' => 'Lainnya',
    ],
];
