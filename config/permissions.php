<?php

// Daftar izin aplikasi (kunci => label) per kelompok, dan peran bawaan yang dibuat seeder.
// Peran menyimpan daftar kunci izin di kolom roles.permissions. Peran dengan izin '*' boleh semuanya.

return [
    'groups' => [
        'Umum' => [
            'dashboard.view' => 'Melihat beranda',
            'report.view' => 'Melihat laporan',
            'report.export' => 'Mengunduh laporan (CSV)',
        ],
        'Master Data' => [
            'master.view' => 'Melihat wilayah, depo, area, saluran, kategori',
            'master.manage' => 'Mengubah wilayah, depo, area, saluran, kategori',
            'employee.view' => 'Melihat karyawan',
            'employee.manage' => 'Mengubah karyawan',
            'outlet.view' => 'Melihat outlet',
            'outlet.manage' => 'Mengubah outlet dan rute',
            'outlet.verify' => 'Memverifikasi outlet baru dari HP',
            'product.view' => 'Melihat produk dan harga',
            'product.manage' => 'Mengubah produk dan harga',
        ],
        'Lapangan' => [
            'field.view' => 'Melihat absensi, kunjungan, dan pelacakan GPS',
            'sale.view' => 'Melihat penjualan dan retur',
            'sale.void' => 'Membatalkan penjualan',
        ],
        'Gudang' => [
            'stock.view' => 'Melihat stok dan mutasi',
            'stock.manage' => 'Mencatat penerimaan barang dan penyesuaian stok',
            'trip.view' => 'Melihat muatan motoris',
            'trip.load' => 'Membuat dan membatalkan muatan',
            'trip.unload' => 'Membongkar muatan (hitung sisa barang dan galon)',
        ],
        'Keuangan' => [
            'settlement.view' => 'Melihat setoran dan rekonsiliasi',
            'settlement.receive' => 'Mencatat uang setoran (kasir)',
            'settlement.approve' => 'Menyetujui rekonsiliasi dan memutuskan selisih',
        ],
        'Kinerja' => [
            'target.view' => 'Melihat target dan pencapaian',
            'target.manage' => 'Mengatur target bulanan',
            'incentive.view' => 'Melihat insentif',
            'incentive.manage' => 'Mengatur skema insentif',
            'incentive.approve' => 'Menyetujui insentif',
            'incentive.pay' => 'Menandai insentif dibayar',
        ],
        'Pengaturan' => [
            'user.manage' => 'Mengelola pengguna',
            'role.manage' => 'Mengelola peran dan izin',
            'activity.view' => 'Melihat log aktivitas',
        ],
    ],

    'roles' => [
        'super_admin' => [
            'name' => 'Super Admin',
            'description' => 'Akses penuh ke semua menu dan semua depo.',
            'permissions' => ['*'],
        ],
        'admin_pusat' => [
            'name' => 'Admin Pusat',
            'description' => 'Mengelola data master, harga, target, dan insentif.',
            'permissions' => [
                'dashboard.view', 'report.view', 'report.export',
                'master.view', 'master.manage', 'employee.view', 'employee.manage',
                'outlet.view', 'outlet.manage', 'outlet.verify', 'product.view', 'product.manage',
                'field.view', 'sale.view', 'stock.view', 'trip.view', 'settlement.view',
                'target.view', 'target.manage', 'incentive.view', 'incentive.manage', 'incentive.approve', 'incentive.pay',
                'user.manage', 'activity.view',
            ],
        ],
        'kepala_depo' => [
            'name' => 'Kepala Depo',
            'description' => 'Mengawasi operasional satu depo dan menyetujui rekonsiliasi.',
            'permissions' => [
                'dashboard.view', 'report.view', 'report.export',
                'master.view', 'employee.view', 'outlet.view', 'outlet.manage', 'outlet.verify', 'product.view',
                'field.view', 'sale.view', 'sale.void', 'stock.view', 'trip.view', 'trip.load', 'trip.unload',
                'settlement.view', 'settlement.approve', 'target.view', 'target.manage', 'incentive.view', 'incentive.approve',
            ],
        ],
        'gudang' => [
            'name' => 'Gudang',
            'description' => 'Penerimaan barang, muat dan bongkar kendaraan motoris.',
            'permissions' => ['dashboard.view', 'product.view', 'stock.view', 'stock.manage', 'trip.view', 'trip.load', 'trip.unload', 'sale.view'],
        ],
        'kasir' => [
            'name' => 'Kasir',
            'description' => 'Menerima setoran uang motoris.',
            'permissions' => ['dashboard.view', 'sale.view', 'trip.view', 'settlement.view', 'settlement.receive', 'report.view'],
        ],
        'koordinator' => [
            'name' => 'Koordinator',
            'description' => 'Memantau tim motoris di lapangan.',
            'permissions' => ['dashboard.view', 'report.view', 'outlet.view', 'outlet.verify', 'field.view', 'sale.view', 'trip.view', 'target.view', 'incentive.view'],
        ],
        'sales' => [
            'name' => 'Sales Motoris',
            'description' => 'Hanya memakai aplikasi HP.',
            'permissions' => [],
        ],
    ],
];
