<?php

// Menu sidebar. slug = path URL tanpa "/" depan; permission = izin yang dibutuhkan (config/permissions.php).
// Section posisi "footer" tampil di menu akun (bawah sidebar); item ber-children tampil dengan panel navigasi samping.

return [
    [
        'label' => null,
        'items' => [
            ['slug' => 'home', 'title' => 'Beranda', 'icon' => 'home', 'permission' => 'dashboard.view'],
        ],
    ],
    [
        'label' => 'Lapangan',
        'icon' => 'bike',
        'items' => [
            ['slug' => 'muatan', 'title' => 'Muatan Harian', 'icon' => 'truck', 'permission' => 'trip.view'],
            ['slug' => 'penjualan', 'title' => 'Penjualan', 'icon' => 'shopping-cart', 'permission' => 'sale.view'],
            ['slug' => 'retur', 'title' => 'Retur Outlet', 'icon' => 'undo', 'permission' => 'sale.view'],
            ['slug' => 'kunjungan', 'title' => 'Kunjungan', 'icon' => 'map-pin', 'permission' => 'field.view'],
            ['slug' => 'absensi', 'title' => 'Absensi', 'icon' => 'calendar-check', 'permission' => 'field.view'],
            ['slug' => 'pelacakan', 'title' => 'Pelacakan GPS', 'icon' => 'route', 'permission' => 'field.view'],
            ['slug' => 'outlet-baru', 'title' => 'Verifikasi Outlet Baru', 'icon' => 'store', 'permission' => 'outlet.verify'],
        ],
    ],
    [
        'label' => 'Gudang',
        'icon' => 'warehouse',
        'items' => [
            ['slug' => 'stok', 'title' => 'Stok', 'icon' => 'boxes', 'permission' => 'stock.view'],
            ['slug' => 'dokumen-stok', 'title' => 'Penerimaan & Opname', 'icon' => 'clipboard-list', 'permission' => 'stock.view'],
            ['slug' => 'mutasi-stok', 'title' => 'Mutasi Stok', 'icon' => 'arrow-left-right', 'permission' => 'stock.view'],
        ],
    ],
    [
        'label' => 'Keuangan',
        'icon' => 'banknote',
        'items' => [
            ['slug' => 'setoran', 'title' => 'Setoran & Rekonsiliasi', 'icon' => 'calculator', 'permission' => 'settlement.view'],
        ],
    ],
    [
        'label' => 'Kinerja',
        'icon' => 'flag',
        'items' => [
            ['slug' => 'target', 'title' => 'Target Bulanan', 'icon' => 'flag', 'permission' => 'target.view'],
            ['slug' => 'pencapaian', 'title' => 'Pencapaian', 'icon' => 'chart-pie', 'permission' => 'target.view'],
            ['slug' => 'insentif', 'title' => 'Insentif', 'icon' => 'badge-check', 'permission' => 'incentive.view'],
        ],
    ],
    [
        'label' => 'Laporan',
        'icon' => 'chart-bar',
        'items' => [
            ['slug' => 'laporan/penjualan', 'title' => 'Laporan Penjualan', 'icon' => 'file-text', 'permission' => 'report.view'],
            ['slug' => 'laporan/galon', 'title' => 'Saldo Galon Outlet', 'icon' => 'store', 'permission' => 'report.view'],
            ['slug' => 'laporan/kunjungan', 'title' => 'Kinerja Kunjungan', 'icon' => 'users', 'permission' => 'report.view'],
        ],
    ],
    [
        'label' => 'Akun',
        'posisi' => 'footer',
        'items' => [
            [
                'id' => 'master', 'title' => 'Master Data', 'icon' => 'layers',
                'children' => [
                    ['slug' => 'master/wilayah', 'title' => 'Wilayah', 'icon' => 'map', 'permission' => 'master.view'],
                    ['slug' => 'master/depo', 'title' => 'Depo', 'icon' => 'warehouse', 'permission' => 'master.view'],
                    ['slug' => 'master/area', 'title' => 'Area Penjualan', 'icon' => 'shapes', 'permission' => 'master.view'],
                    ['slug' => 'master/karyawan', 'title' => 'Karyawan', 'icon' => 'id-card', 'permission' => 'employee.view'],
                    ['slug' => 'master/outlet', 'title' => 'Outlet', 'icon' => 'store', 'permission' => 'outlet.view'],
                    ['slug' => 'master/rute', 'title' => 'Rute Kunjungan', 'icon' => 'signpost', 'permission' => 'outlet.view'],
                    ['slug' => 'master/saluran', 'title' => 'Saluran Outlet', 'icon' => 'network', 'permission' => 'master.view'],
                    ['slug' => 'master/produk', 'title' => 'Produk', 'icon' => 'box', 'permission' => 'product.view'],
                    ['slug' => 'master/kategori', 'title' => 'Kategori Produk', 'icon' => 'tags', 'permission' => 'product.view'],
                    ['slug' => 'master/harga', 'title' => 'Harga Jual', 'icon' => 'banknote', 'permission' => 'product.view'],
                ],
            ],
            [
                'id' => 'pengaturan', 'title' => 'Pengaturan', 'icon' => 'settings',
                'children' => [
                    ['slug' => 'pengaturan/pengguna', 'title' => 'Pengguna', 'icon' => 'users', 'permission' => 'user.manage'],
                    ['slug' => 'pengaturan/peran', 'title' => 'Peran & Izin', 'icon' => 'shield-user', 'permission' => 'role.manage'],
                    ['slug' => 'pengaturan/skema-insentif', 'title' => 'Skema Insentif', 'icon' => 'badge-check', 'permission' => 'incentive.view'],
                    ['slug' => 'pengaturan/log', 'title' => 'Log Aktivitas', 'icon' => 'clock', 'permission' => 'activity.view'],
                ],
            ],
        ],
    ],
];
