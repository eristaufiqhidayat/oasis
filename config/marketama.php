<?php

use App\Support\Menu\Motoris\SumberBarangHabisTerjual;
use App\Support\Menu\Motoris\SumberBarangTerbaikBulanan;
use App\Support\Menu\Motoris\SumberBarangTerbaikHarian;
use App\Support\Menu\Motoris\SumberBarangTerbaikMingguan;
use App\Support\Menu\Motoris\SumberCanvassing;
use App\Support\Menu\Motoris\SumberEffectiveCall;
use App\Support\Menu\Motoris\SumberEffectiveCallItem;
use App\Support\Menu\Motoris\SumberInsentifSales;
use App\Support\Menu\Motoris\SumberLastSeenKoordinator;
use App\Support\Menu\Motoris\SumberLastSeenSales;
use App\Support\Menu\Motoris\SumberOutletAc;
use App\Support\Menu\Motoris\SumberOutletEc;
use App\Support\Menu\Motoris\SumberOutletNoo;
use App\Support\Menu\Motoris\SumberOutletOss;
use App\Support\Menu\Motoris\SumberOutletRo;
use App\Support\Menu\Motoris\SumberOutletStatus;
use App\Support\Menu\Motoris\SumberPengembalianBarang;
use App\Support\Menu\Motoris\SumberPenjualan;
use App\Support\Menu\Motoris\SumberPenjualanItem;
use App\Support\Menu\Motoris\SumberSesiLogin;
use App\Support\Menu\Motoris\SumberStokKompetitor;
use App\Support\Menu\Motoris\SumberStokOutlet;
use App\Support\Menu\SumberCheckinPagi;
use App\Support\Menu\SumberJoinVisit;
use App\Support\Menu\SumberOutletBaru;
use App\Support\Menu\SumberOutletMaster;

// Struktur menu sidebar disamakan dengan Marketama Canvassing System.
// Dihasilkan dari hasil penarikan data 22 Okt 2026.

return [
    // Menu yang sudah terhubung ke data aplikasi motoris: membaca/menulis tabel yang sama dengan API HP
    // (mk_*, trx_*, akun & role) atau tabel referensi yang dipakai aplikasi HP. Menu lain tampil di sidebar
    // dengan awalan "* " (belum terhubung ke motoris / tidak berhubungan). Tambahkan slug di sini saat menunya
    // dialihkan ke data motoris.
    'menu_motoris' => [
        // Utama (Beranda = halaman awal setelah login)
        'home', 'monitoring_harian_sales', 'rekap_pencapaian_sku', 'summary_all_depo', 'motoris_report',
        // Canvassing
        'checkin_depo', 'lap_checkin_checkout', 'tracking_sales_history_location', 'tracking_koordinator_history_location',
        // Aktivitas Canvas
        'checkin_outlet', 'lap_checkin_outlet',
        // Absensi
        'absensi_sales', 'absensi_sales_terlambat', 'absensi_koordinator', 'lap_absensi_sales',
        // Outlet dan Visit
        'outlet_all', 'join_visit',
        // User dan Karyawan (akun login aplikasi HP, role & hak akses)
        'user_sales', 'user_koordinator', 'role',
        // Master Data & Pengaturan yang dibaca aplikasi HP / laporan motoris
        'kuota_motoris', 'master_depo', 'holiday_dates', 'waktu_aktif_app', 'produk_kompetitor', 'tipe_outlet',
        'rumus_insentif_sales', 'sales_point_conversion_per_warehouse',
        // Grup A analisa menu (25 Okt 2026): dialihkan ke trx_* / mk_* (riwayat lama lewat enesis:migrasi-transaksi)
        'barang_canvassing', 'lap_barang_canvassing', 'lap_barang_habis_terjual', 'pengembalian_barang', 'lap_pengembalian_barang',
        'tracking_sales', 'tracking_koordinator',
        'penjualan_barang', 'lap_penjualan_barang', 'lap_penjualan_barang_detail', 'lap_effective_call', 'lap_effective_call_barang',
        'lap_penjualan_barang_terbaik_harian', 'lap_penjualan_barang_terbaik_mingguan', 'lap_penjualan_barang_terbaik_bulanan',
        'stok_outlet', 'lap_stok_outlet', 'stok_kompetitor', 'lap_stok_kompetitor',
        'outlet_noo', 'lap_outlet_noo', 'outlet_ac', 'lap_outlet_ac', 'outlet_ec', 'lap_outlet_ec', 'outlet_ro', 'lap_outlet_ro',
        'outlet_oss', 'lap_outlet_oss', 'lap_outlet_all',
        'lap_join_visit', 'lap_absensi_koordinator', 'login_session', 'lap_insentif_sales',
    ],

    'menu' => [
        [
            'label' => null,
            'items' => [
                [
                    'slug' => 'home',
                    'title' => 'Beranda',
                    'icon' => 'home',
                ],
                [
                    'slug' => 'monitoring_harian_sales',
                    'title' => 'Monitoring Harian Sales',
                    'icon' => 'clock',
                ],
                [
                    'slug' => 'rekap_pencapaian_sku',
                    'title' => 'Rekap Pencapaian SKU',
                    'icon' => 'boxes',
                ],
                [
                    'slug' => 'summary_all_depo',
                    'title' => 'Summary All Depo',
                    'icon' => 'chart-pie',
                ],
                [
                    'slug' => 'motoris_report',
                    'title' => 'Motoris Report',
                    'icon' => 'bike',
                ],
            ],
        ],
        [
            'label' => 'Canvassing',
            'icon' => 'megaphone',
            'items' => [
                [
                    'slug' => 'checkin_depo',
                    'title' => 'Checkin Checkout Depo',
                    'icon' => 'camera',
                ],
                [
                    'slug' => 'barang_canvassing',
                    'title' => 'Barang Canvassing',
                    'icon' => 'bike',
                ],
                [
                    'slug' => 'pengembalian_barang',
                    'title' => 'Pengembalian Barang',
                    'icon' => 'undo',
                ],
                [
                    'slug' => 'tracking_sales',
                    'title' => 'Tracking Last Seen Sales',
                    'icon' => 'map-pin',
                ],
                [
                    'slug' => 'tracking_sales_history_location',
                    'title' => 'Tracking History Sales Location',
                    'icon' => 'map-pin',
                ],
                [
                    'slug' => 'tracking_koordinator',
                    'title' => 'Tracking Last Seen Koordinator',
                    'icon' => 'map-pin',
                ],
                [
                    'slug' => 'tracking_koordinator_history_location',
                    'title' => 'Tracking History Koordinator Location',
                    'icon' => 'map-pin',
                ],
                [
                    'title' => 'Laporan',
                    'icon' => 'book',
                    'id' => 'lapcanvassing',
                    'children' => [
                        [
                            'slug' => 'lap_checkin_checkout',
                            'title' => 'Lap. Checkin Checkout',
                            'icon' => null,
                        ],
                        [
                            'slug' => 'lap_barang_canvassing',
                            'title' => 'Lap. Barang Canvassing',
                            'icon' => null,
                        ],
                        [
                            'slug' => 'lap_barang_habis_terjual',
                            'title' => 'Lap. Barang Habis Terjual',
                            'icon' => null,
                        ],
                        [
                            'slug' => 'lap_pengembalian_barang',
                            'title' => 'Lap. Pengembalian Barang',
                            'icon' => null,
                        ],
                    ],
                ],
            ],
        ],
        [
            'label' => 'Aktivitas Canvas',
            'icon' => 'route',
            'items' => [
                [
                    'slug' => 'checkin_outlet',
                    'title' => 'Checkin Outlet',
                    'icon' => 'camera',
                ],
                [
                    'slug' => 'penjualan_barang',
                    'title' => 'Penjualan Barang',
                    'icon' => 'box',
                ],
                [
                    'slug' => 'stok_outlet',
                    'title' => 'Stok Outlet',
                    'icon' => 'store',
                ],
                [
                    'slug' => 'stok_kompetitor',
                    'title' => 'Stok Kompetitor',
                    'icon' => 'store',
                ],
                [
                    'title' => 'Laporan',
                    'icon' => 'book',
                    'id' => 'laptransaksi',
                    'children' => [
                        [
                            'slug' => 'lap_checkin_outlet',
                            'title' => 'Lap. Checkin Outlet',
                            'icon' => null,
                        ],
                        [
                            'slug' => 'lap_effective_call',
                            'title' => 'Lap. Efective Call',
                            'icon' => null,
                        ],
                        [
                            'slug' => 'lap_effective_call_barang',
                            'title' => 'Lap. Efective Call Detail',
                            'icon' => null,
                        ],
                        [
                            'slug' => 'lap_penjualan_barang',
                            'title' => 'Lap. Penjualan Barang',
                            'icon' => null,
                        ],
                        [
                            'slug' => 'lap_penjualan_barang_detail',
                            'title' => 'Lap. Penjualan Barang Detail',
                            'icon' => null,
                        ],
                        [
                            'slug' => 'lap_penjualan_barang_terbaik_harian',
                            'title' => 'Lap. Barang Terbaik Harian',
                            'icon' => null,
                        ],
                        [
                            'slug' => 'lap_penjualan_barang_terbaik_mingguan',
                            'title' => 'Lap. Barang Terbaik Mingguan',
                            'icon' => null,
                        ],
                        [
                            'slug' => 'lap_penjualan_barang_terbaik_bulanan',
                            'title' => 'Lap. Barang Terbaik Bulanan',
                            'icon' => null,
                        ],
                        [
                            'slug' => 'lap_stok_outlet',
                            'title' => 'Lap. Stok Outlet',
                            'icon' => null,
                        ],
                        [
                            'slug' => 'lap_stok_kompetitor',
                            'title' => 'Lap. Stok Kompetitor',
                            'icon' => null,
                        ],
                    ],
                ],
            ],
        ],
        [
            'label' => 'Absensi dan Penggajian',
            'icon' => 'calendar-check',
            'items' => [
                [
                    'slug' => 'absensi_sales',
                    'title' => 'Absensi Sales',
                    'icon' => 'calendar',
                ],
                [
                    'slug' => 'absensi_sales_terlambat',
                    'title' => 'Absensi Sales Terlambat',
                    'icon' => 'clock',
                ],
                [
                    'slug' => 'absensi_koordinator',
                    'title' => 'Absensi Koordinator',
                    'icon' => 'calendar',
                ],
                [
                    'title' => 'Laporan',
                    'icon' => 'book',
                    'id' => 'lappenggajian',
                    'children' => [
                        [
                            'slug' => 'lap_absensi_sales',
                            'title' => 'Lap. Absensi Sales',
                            'icon' => null,
                        ],
                        [
                            'slug' => 'lap_absensi_koordinator',
                            'title' => 'Lap. Absensi Koordinator',
                            'icon' => null,
                        ],
                        [
                            'slug' => 'lap_insentif_sales',
                            'title' => 'Lap. Insentif Sales',
                            'icon' => null,
                        ],
                    ],
                ],
            ],
        ],
        [
            'label' => 'Outlet dan Visit',
            'icon' => 'store',
            'items' => [
                [
                    'title' => 'Outlet',
                    'icon' => 'book',
                    'id' => 'groupoutlet',
                    'children' => [
                        [
                            'slug' => 'outlet_noo',
                            'title' => 'New Open Outlet',
                            'icon' => 'store',
                        ],
                        [
                            'slug' => 'outlet_all',
                            'title' => 'All Outlet',
                            'icon' => 'store',
                        ],
                        [
                            'slug' => 'outlet_ac',
                            'title' => 'Actual Call',
                            'icon' => 'store',
                        ],
                        [
                            'slug' => 'outlet_ec',
                            'title' => 'Effective Call',
                            'icon' => 'store',
                        ],
                        [
                            'slug' => 'outlet_ro',
                            'title' => 'Repeat Order',
                            'icon' => 'store',
                        ],
                        [
                            'slug' => 'outlet_oss',
                            'title' => 'Out of Stock',
                            'icon' => 'store',
                        ],
                    ],
                ],
                [
                    'slug' => 'join_visit',
                    'title' => 'Join Visit',
                    'icon' => 'map',
                ],
                [
                    'title' => 'Laporan',
                    'icon' => 'book',
                    'id' => 'lapoutlet',
                    'children' => [
                        [
                            'slug' => 'lap_outlet_noo',
                            'title' => 'Lap. Outlet NOO',
                            'icon' => null,
                        ],
                        [
                            'slug' => 'lap_outlet_all',
                            'title' => 'Lap. Outlet All',
                            'icon' => null,
                        ],
                        [
                            'slug' => 'lap_outlet_ac',
                            'title' => 'Lap. Outlet AC',
                            'icon' => null,
                        ],
                        [
                            'slug' => 'lap_outlet_ec',
                            'title' => 'Lap. Outlet EC',
                            'icon' => null,
                        ],
                        [
                            'slug' => 'lap_outlet_ro',
                            'title' => 'Lap. Outlet RO',
                            'icon' => null,
                        ],
                        [
                            'slug' => 'lap_outlet_oss',
                            'title' => 'Lap. Outlet OSS',
                            'icon' => null,
                        ],
                        [
                            'slug' => 'lap_join_visit',
                            'title' => 'Lap. Join Visit',
                            'icon' => null,
                        ],
                    ],
                ],
            ],
        ],
        [
            'label' => 'User dan Karyawan',
            'icon' => 'users',
            'items' => [
                [
                    'title' => 'Users',
                    'icon' => 'users',
                    'id' => 'group_users',
                    'children' => [
                        [
                            'slug' => 'user_koordinator',
                            'title' => 'User Koordinator',
                            'icon' => 'users',
                        ],
                        [
                            'slug' => 'user_sales',
                            'title' => 'User Sales',
                            'icon' => 'users',
                        ],
                    ],
                ],
                [
                    'slug' => 'role',
                    'title' => 'Role & Hak Akses',
                    'icon' => 'shield-user',
                ],
                [
                    'slug' => 'login_session',
                    'title' => 'Sesi Login',
                    'icon' => 'key',
                ],
            ],
        ],
        [
            'label' => 'Master Data & Pengaturan',
            'icon' => 'settings',
            // Tidak ditampilkan di daftar scroll sidebar, tapi di popup menu user (footer sidebar).
            'posisi' => 'footer',
            'items' => [
                [
                    'title' => 'Manajemen Produk',
                    'icon' => 'boxes',
                    'id' => 'produk_group',
                    'children' => [
                        [
                            'slug' => 'produk_kompetitor',
                            'title' => 'Produk Kompetitor',
                            'icon' => 'box',
                        ],
                    ],
                ],
                [
                    'title' => 'Konfigurasi',
                    'icon' => 'settings',
                    'id' => 'konfigurasi_group',
                    'children' => [
                        [
                            'slug' => 'master_depo',
                            'title' => 'Master Warehouse / Depo',
                            'icon' => 'building',
                        ],
                        [
                            'slug' => 'kuota_motoris',
                            'title' => 'Kuota Motoris',
                            'icon' => 'users',
                        ],
                        [
                            'slug' => 'holiday_dates',
                            'title' => 'Tanggal Libur',
                            'icon' => 'calendar',
                        ],
                        [
                            'slug' => 'waktu_aktif_app',
                            'title' => 'Hari Kerja Aktif',
                            'icon' => 'clock',
                        ],
                    ],
                ],
                [
                    'title' => 'Master Data',
                    'icon' => 'book',
                    'id' => 'master_data_group',
                    'children' => [
                        [
                            'slug' => 'tipe_outlet',
                            'title' => 'Tipe Outlet',
                            'icon' => 'tags',
                        ],
                        [
                            'slug' => 'sales_point_conversion_per_warehouse',
                            'title' => 'Konversi Poin Per Depo',
                            'icon' => 'arrow-left-right',
                        ],
                        [
                            'slug' => 'rumus_insentif_sales',
                            'title' => 'Rumus Insentif Sales',
                            'icon' => 'calculator',
                        ],
                    ],
                ],
            ],
        ],
    ],

    'pages' => [
        'home' => [
            'title' => 'Beranda',
            'tables' => [],
            'same_as' => null,
        ],
        // Gabungan menu monitoring harian sales. Filter tanggal dan KPI diatur per tabel.
        // 'tabs' => true: hanya tabel grup tab aktif (query ?tab=) yang ditampilkan. Tiap grup: tab_key, tab_label, tables.
        'monitoring_harian_sales' => [
            'title' => 'Monitoring Harian Sales',
            'tabs' => true,
            'tables' => [
                [
                    'tab_key' => 'checkin_pagi',
                    'tab_label' => 'Sales Checkin Outlet Pagi',
                    'tables' => [
                        ['table' => 'checkin_pagi', 'date_filter_column' => 'tanggal_data'],
                    ],
                ],
                [
                    'tab_key' => 'sales_tidak_penjualan',
                    'tab_label' => 'Sales Tidak Penjualan',
                    'tables' => [
                        ['table' => 'sales_tidak_penjualan', 'date_filter_column' => null, 'kpi' => null],
                    ],
                ],
                [
                    'tab_key' => 'sales_tidak_absen',
                    'tab_label' => 'Sales Tidak Absen Pagi',
                    'tables' => [
                        ['table' => 'sales_tidak_absen', 'date_filter_column' => null, 'kpi' => null],
                    ],
                ],
            ],
            'same_as' => null,
        ],
        // Gabungan rekap pencapaian SKU per motoris & per depo. 'tabs' => true: hanya grup tab aktif (query ?tab=) yang ditampilkan.
        'rekap_pencapaian_sku' => [
            'title' => 'Rekap Pencapaian SKU',
            'tabs' => true,
            'tables' => [
                [
                    'tab_key' => 'motoris',
                    'tab_label' => 'Per Motoris',
                    'tables' => [
                        'rekap_pencapaian_sku_motoris_tableachieved',
                        'rekap_pencapaian_sku_motoris_tablenotachieved',
                    ],
                ],
                [
                    'tab_key' => 'depo',
                    'tab_label' => 'Per Depo',
                    'tables' => [
                        'rekap_pencapaian_depo_sku_tableachieved',
                        'rekap_pencapaian_depo_sku_tablenotachieved',
                    ],
                ],
            ],
            'same_as' => null,
        ],
        'summary_all_depo' => [
            'title' => 'Summary All Depo',
            'tables' => [],
            'same_as' => null,
        ],
        // Gabungan Motoris Coverage, Motoris Performance, Monitor Absensi Motoris. 'tabs' => true: hanya grup tab aktif (query ?tab=) yang ditampilkan.
        'motoris_report' => [
            'title' => 'Motoris Report',
            'tabs' => true,
            'tables' => [
                [
                    'tab_key' => 'coverage',
                    'tab_label' => 'Coverage',
                    'tables' => [
                        'motoris_coverage',
                    ],
                ],
                [
                    'tab_key' => 'performance',
                    'tab_label' => 'Performance',
                    'tables' => [
                        'motoris_performance',
                    ],
                ],
                [
                    'tab_key' => 'absensi',
                    'tab_label' => 'Absensi',
                    'tables' => [
                        'monitor_absensi_motoris',
                    ],
                ],
            ],
            'same_as' => null,
        ],
        'checkin_depo' => [
            'title' => 'Checkin Checkout Depo',
            'tables' => [
                'lap_checkin_checkout',
            ],
            'same_as' => 'lap_checkin_checkout',
        ],
        'barang_canvassing' => [
            'title' => 'Barang Canvassing',
            'tables' => [
                'lap_barang_canvassing',
            ],
            'same_as' => 'lap_barang_canvassing',
        ],
        'pengembalian_barang' => [
            'title' => 'Pengembalian Barang',
            'tables' => [
                'lap_pengembalian_barang',
            ],
            'same_as' => 'lap_pengembalian_barang',
        ],
        'tracking_sales' => [
            'title' => 'Tracking Last Seen Sales',
            'tables' => [
                'tracking_last_seen_sales',
            ],
            'same_as' => null,
        ],
        'tracking_sales_history_location' => [
            'title' => 'Tracking History Sales Location',
            'tables' => [],
            'same_as' => null,
        ],
        'tracking_koordinator' => [
            'title' => 'Tracking Last Seen Koordinator',
            'tables' => [
                'tracking_last_seen_koordinator',
            ],
            'same_as' => null,
        ],
        'tracking_koordinator_history_location' => [
            'title' => 'Tracking History Koordinator Location',
            'tables' => [],
            'same_as' => null,
        ],
        'lap_checkin_checkout' => [
            'title' => 'Lap. Checkin Checkout',
            'tables' => [
                'lap_checkin_checkout',
            ],
            'same_as' => null,
        ],
        'lap_barang_canvassing' => [
            'title' => 'Lap. Barang Canvassing',
            'tables' => [
                'lap_barang_canvassing',
            ],
            'same_as' => null,
        ],
        'lap_barang_habis_terjual' => [
            'title' => 'Lap. Barang Habis Terjual',
            'tables' => [
                'lap_barang_habis_terjual',
            ],
            'same_as' => null,
        ],
        'lap_pengembalian_barang' => [
            'title' => 'Lap. Pengembalian Barang',
            'tables' => [
                'lap_pengembalian_barang',
            ],
            'same_as' => null,
        ],
        'checkin_outlet' => [
            'title' => 'Checkin Outlet',
            'tables' => [
                'lap_checkin_outlet',
            ],
            'same_as' => 'lap_checkin_outlet',
        ],
        'penjualan_barang' => [
            'title' => 'Penjualan Barang',
            'tables' => [
                'lap_penjualan_barang',
            ],
            'same_as' => 'lap_penjualan_barang',
        ],
        'stok_outlet' => [
            'title' => 'Stok Outlet',
            'tables' => [
                'lap_stok_outlet',
            ],
            'same_as' => 'lap_stok_outlet',
        ],
        'stok_kompetitor' => [
            'title' => 'Stok Kompetitor',
            'tables' => [
                'lap_stok_kompetitor',
            ],
            'same_as' => 'lap_stok_kompetitor',
        ],
        'lap_checkin_outlet' => [
            'title' => 'Lap. Checkin Outlet',
            'tables' => [
                'lap_checkin_outlet',
            ],
            'same_as' => null,
        ],
        'lap_effective_call' => [
            'title' => 'Lap. Efective Call',
            'tables' => [
                'lap_effective_call',
            ],
            'same_as' => null,
        ],
        'lap_effective_call_barang' => [
            'title' => 'Lap. Efective Call Detail',
            'tables' => [
                'lap_effective_call_barang',
            ],
            'same_as' => null,
        ],
        'lap_penjualan_barang' => [
            'title' => 'Lap. Penjualan Barang',
            'tables' => [
                'lap_penjualan_barang',
            ],
            'same_as' => null,
        ],
        'lap_penjualan_barang_detail' => [
            'title' => 'Lap. Penjualan Barang Detail',
            'tables' => [
                'lap_penjualan_barang_detail',
            ],
            'same_as' => null,
        ],
        'lap_penjualan_barang_terbaik_harian' => [
            'title' => 'Lap. Barang Terbaik Harian',
            'tables' => [
                ['table' => 'lap_penjualan_barang_terbaik_harian', 'date_filter_column' => 'tanggal_data'],
            ],
            'same_as' => null,
        ],
        'lap_penjualan_barang_terbaik_mingguan' => [
            'title' => 'Lap. Barang Terbaik Mingguan',
            'tables' => [
                ['table' => 'lap_penjualan_barang_terbaik_mingguan', 'date_filter_column' => 'tanggal_data'],
            ],
            'same_as' => null,
        ],
        'lap_penjualan_barang_terbaik_bulanan' => [
            'title' => 'Lap. Barang Terbaik Bulanan',
            'tables' => [
                ['table' => 'lap_penjualan_barang_terbaik_bulanan', 'date_filter_column' => 'tanggal_data'],
            ],
            'same_as' => null,
        ],
        'lap_stok_outlet' => [
            'title' => 'Lap. Stok Outlet',
            'tables' => [
                'lap_stok_outlet',
            ],
            'same_as' => null,
        ],
        'lap_stok_kompetitor' => [
            'title' => 'Lap. Stok Kompetitor',
            'tables' => [
                'lap_stok_kompetitor',
            ],
            'same_as' => null,
        ],
        'absensi_sales' => [
            'title' => 'Absensi Sales',
            'tables' => [
                'lap_absensi_sales',
            ],
            'same_as' => 'lap_absensi_sales',
        ],
        'absensi_sales_terlambat' => [
            'title' => 'Absensi Sales Terlambat',
            'tables' => [
                'absensi_sales_terlambat',
            ],
            'same_as' => null,
        ],
        'absensi_koordinator' => [
            'title' => 'Absensi Koordinator',
            'tables' => [
                'absensi_koordinator',
            ],
            'same_as' => null,
        ],
        'lap_absensi_sales' => [
            'title' => 'Lap. Absensi Sales',
            'tables' => [
                'lap_absensi_sales',
            ],
            'same_as' => null,
        ],
        'lap_absensi_koordinator' => [
            'title' => 'Lap. Absensi Koordinator',
            'tables' => [
                'lap_absensi_koordinator',
            ],
            'same_as' => null,
        ],
        'lap_insentif_sales' => [
            'title' => 'Lap. Insentif Sales',
            'tables' => [
                ['table' => 'lap_insentif_sales', 'date_filter_column' => 'periode'],
            ],
            'same_as' => null,
        ],
        'outlet_noo' => [
            'title' => 'New Open Outlet',
            'tables' => [
                'lap_outlet_noo',
            ],
            'same_as' => 'lap_outlet_noo',
        ],
        // Sama dengan API mobile sales motoris: master mk_outlet, plus outlet baru dari HP (trx_outlet_baru).
        // Data laporan lama tetap ada di menu Lap. Outlet All (lap_outlet_all).
        'outlet_all' => [
            'title' => 'All Outlet',
            'tabs' => true,
            'tables' => [
                [
                    'tab_key' => 'master',
                    'tab_label' => 'Master Outlet',
                    'tables' => ['mk_outlet'],
                ],
                [
                    'tab_key' => 'baru',
                    'tab_label' => 'Outlet Baru dari HP',
                    'tables' => ['trx_outlet_baru'],
                ],
            ],
            'same_as' => null,
        ],
        'outlet_ac' => [
            'title' => 'Actual Call',
            'tables' => [
                'lap_outlet_ac',
            ],
            'same_as' => 'lap_outlet_ac',
        ],
        'outlet_ec' => [
            'title' => 'Effective Call',
            'tables' => [
                'lap_outlet_ec',
            ],
            'same_as' => 'lap_outlet_ec',
        ],
        'outlet_ro' => [
            'title' => 'Repeat Order',
            'tables' => [
                'lap_outlet_ro',
            ],
            'same_as' => 'lap_outlet_ro',
        ],
        'outlet_oss' => [
            'title' => 'Out of Stock',
            'tables' => [
                'lap_outlet_oss',
            ],
            'same_as' => 'lap_outlet_oss',
        ],
        // Join visit dari aplikasi HP koordinator + riwayat aplikasi lama yang dipindah (trx_join_visit).
        // Data laporan lama apa adanya tetap di Lap. Join Visit (lap_join_visit).
        'join_visit' => [
            'title' => 'Join Visit',
            'tables' => [
                'trx_join_visit',
            ],
            'same_as' => null,
        ],
        'lap_outlet_noo' => [
            'title' => 'Lap. Outlet NOO',
            'tables' => [
                'lap_outlet_noo',
            ],
            'same_as' => null,
        ],
        'lap_outlet_all' => [
            'title' => 'Lap. Outlet All',
            'tables' => [
                'lap_outlet_all',
            ],
            'same_as' => null,
        ],
        'lap_outlet_ac' => [
            'title' => 'Lap. Outlet AC',
            'tables' => [
                'lap_outlet_ac',
            ],
            'same_as' => null,
        ],
        'lap_outlet_ec' => [
            'title' => 'Lap. Outlet EC',
            'tables' => [
                'lap_outlet_ec',
            ],
            'same_as' => null,
        ],
        'lap_outlet_ro' => [
            'title' => 'Lap. Outlet RO',
            'tables' => [
                'lap_outlet_ro',
            ],
            'same_as' => null,
        ],
        'lap_outlet_oss' => [
            'title' => 'Lap. Outlet OSS',
            'tables' => [
                'lap_outlet_oss',
            ],
            'same_as' => null,
        ],
        'lap_join_visit' => [
            'title' => 'Lap. Join Visit',
            'tables' => [
                // Sama dengan menu Join Visit: data HP koordinator + riwayat lama hasil migrasi.
                'trx_join_visit',
            ],
            'same_as' => null,
        ],
        'user_koordinator' => [
            'title' => 'User Koordinator',
            'tables' => [
                'user_koordinator',
            ],
            'same_as' => null,
        ],
        'user_sales' => [
            'title' => 'User Sales',
            'tables' => [
                'user_sales',
            ],
            'same_as' => null,
        ],
        'login_session' => [
            'title' => 'Sesi Login',
            'tables' => [
                'login_session',
            ],
            'same_as' => null,
        ],
        'produk_kompetitor' => [
            'title' => 'Produk Kompetitor',
            'tables' => [
                'produk_kompetitor',
            ],
            'same_as' => null,
        ],
        // Halaman sendiri (KuotaMotorisController): kuota sales motoris per depo isian admin, untuk Motoris Report.
        'kuota_motoris' => [
            'title' => 'Kuota Motoris',
            'tables' => [],
            'same_as' => null,
        ],
        'master_depo' => [
            'title' => 'Master Warehouse / Depo',
            'tables' => [
                'master_depo',
            ],
            'same_as' => null,
        ],
        'holiday_dates' => [
            'title' => 'Tanggal Libur',
            'tables' => [
                'holiday_dates',
            ],
            'same_as' => null,
        ],
        'waktu_aktif_app' => [
            'title' => 'Hari Kerja Aktif',
            'tables' => [
                'waktu_aktif_app',
            ],
            'same_as' => null,
        ],
        'tipe_outlet' => [
            'title' => 'Tipe Outlet',
            'tables' => [
                'tipe_outlet',
            ],
            'same_as' => null,
        ],
        'sales_point_conversion_per_warehouse' => [
            'title' => 'Konversi Poin Per Depo',
            'tables' => [
                'sales_point_conversion_per_warehouse',
            ],
            'same_as' => null,
        ],
        'rumus_insentif_sales' => [
            'title' => 'Rumus Insentif Sales',
            'tables' => [
                'rumus_insentif_sales',
            ],
            'same_as' => null,
        ],
    ],

    'tables' => [
        'tracking_last_seen_sales' => [
            'title' => 'Tracking Last Seen Sales',
            'sumber' => SumberLastSeenSales::class,
            'columns' => [
                'nama' => 'Nama',
                'telepon' => 'Telepon',
                'depo' => 'Depo',
                'koordinator' => 'Koordinator',
                'terakhir_terlihat' => 'Terakhir Terlihat',
                'sejak' => 'Sejak',
                'latitude' => 'Latitude',
                'longitude' => 'Longitude',
                'baterai' => 'Baterai',
                'lokasi_palsu' => 'Lokasi Palsu',
                'sumber' => 'Sumber',
            ],
        ],
        'tracking_last_seen_koordinator' => [
            'title' => 'Tracking Last Seen Koordinator',
            'sumber' => SumberLastSeenKoordinator::class,
            'columns' => [
                'nama' => 'Nama',
                'telepon' => 'Telepon',
                'depo' => 'Depo',
                'terakhir_terlihat' => 'Terakhir Terlihat',
                'sejak' => 'Sejak',
                'latitude' => 'Latitude',
                'longitude' => 'Longitude',
                'baterai' => 'Baterai',
                'lokasi_palsu' => 'Lokasi Palsu',
                'sumber' => 'Sumber',
            ],
        ],
        'lap_insentif_sales' => [
            'title' => 'Lap. Insentif Sales',
            'sumber' => SumberInsentifSales::class,
            'columns' => [
                'no_payroll' => 'No. Payroll',
                'periode' => 'Periode',
                'nama_sales' => 'Nama Sales',
                'telepon' => 'Telepon',
                'koordinator' => 'Koordinator',
                'depo' => 'Depo',
                'insentif_ec' => 'Insentif EC',
                'total_ec' => 'Total EC',
                'total_poin' => 'Total Poin',
                'insentif_poin' => 'Nilai Poin',
                'insentif_absensi' => 'Insentif Absensi',
                'total_insentif' => 'Total Insentif',
            ],
        ],
        'lap_outlet_ro' => [
            'title' => 'Lap. Outlet RO',
            'sumber' => SumberOutletRo::class,
            'columns' => [
                'depo' => 'Depo',
                'koordinator' => 'Koordinator',
                'kode_outlet' => 'Kode Outlet',
                'nama_outlet' => 'Nama Outlet',
                'contact_person' => 'Contact Person',
                'telepon' => 'Telepon',
                'alamat' => 'Alamat',
                'area' => 'Area',
                'provinsi' => 'Provinsi',
                'kota_kab' => 'Kota/Kab',
                'kecamatan' => 'Kecamatan',
                'kelurahan' => 'Kelurahan',
                'latitude' => 'Latitude',
                'longitude' => 'Longitude',
                'dibuat_oleh' => 'Dibuat Oleh',
                'dibuat_pada' => 'Dibuat Pada',
            ],
        ],
        'produk_kompetitor' => [
            'title' => 'Produk Kompetitor',
            'columns' => [
                'nama_produk' => 'Nama Produk',
                'satuan' => 'Satuan',
                'diupdate_oleh' => 'Diupdate Oleh',
                'diupdate_pada' => 'Diupdate Pada',
            ],
        ],
        'tipe_outlet' => [
            'title' => 'Tipe Outlet',
            'columns' => [
                'source_id' => 'ID',
                'kode' => 'Kode',
                'tipe_outlet' => 'Tipe Outlet',
            ],
        ],
        'lap_penjualan_barang_terbaik_harian' => [
            'title' => 'Lap. Barang Terbaik Harian',
            'sumber' => SumberBarangTerbaikHarian::class,
            'columns' => [
                'tanggal_data' => 'Tanggal Data',
                'product_id' => 'Product ID',
                'product_name' => 'Product Name',
                'product_sku' => 'Product SKU',
                'category_name' => 'Category Name',
                'brand_name' => 'Brand Name',
                'unit_name' => 'Unit Name',
                'total_qty' => 'Total Qty',
                'total_amount' => 'Total Amount',
                'total_discount' => 'Total Discount',
                'total_transaksi' => 'Total Transaksi',
            ],
        ],
        'lap_penjualan_barang_terbaik_mingguan' => [
            'title' => 'Lap. Barang Terbaik Mingguan',
            'sumber' => SumberBarangTerbaikMingguan::class,
            'columns' => [
                'product_id' => 'Product ID',
                'product_name' => 'Product Name',
                'product_sku' => 'Product SKU',
                'category_name' => 'Category Name',
                'brand_name' => 'Brand Name',
                'unit_name' => 'Unit Name',
                'total_qty' => 'Total Qty',
                'total_amount' => 'Total Amount',
                'total_discount' => 'Total Discount',
                'total_transaksi' => 'Total Transaksi',
            ],
        ],
        'holiday_dates' => [
            'title' => 'Tanggal Libur',
            'columns' => [
                'nama_libur' => 'Nama Libur',
                'tanggal' => 'Tanggal',
                'keterangan' => 'Keterangan',
                'diupdate_oleh' => 'Diupdate Oleh',
                'diupdate_pada' => 'Diupdate Pada',
            ],
        ],
        'lap_penjualan_barang_terbaik_bulanan' => [
            'title' => 'Lap. Barang Terbaik Bulanan',
            'sumber' => SumberBarangTerbaikBulanan::class,
            'columns' => [
                'product_id' => 'Product ID',
                'product_name' => 'Product Name',
                'product_sku' => 'Product SKU',
                'category_name' => 'Category Name',
                'brand_name' => 'Brand Name',
                'unit_name' => 'Unit Name',
                'total_qty' => 'Total Qty',
                'total_amount' => 'Total Amount',
                'total_discount' => 'Total Discount',
                'total_transaksi' => 'Total Transaksi',
            ],
        ],
        'sales_point_conversion_per_warehouse' => [
            'title' => 'Konversi Poin Per Depo',
            'columns' => [
                'depo' => 'Depo',
                'poin_insentif_rp' => 'Poin Insentif (Rp)',
                'diupdate_oleh' => 'Diupdate Oleh',
                'diupdate_pada' => 'Diupdate Pada',
            ],
        ],
        'master_depo' => [
            'title' => 'Master Warehouse / Depo',
            'columns' => [
                'kode' => 'Kode',
                'nama' => 'Nama',
                'projek' => 'Projek',
                'kota_besar' => 'Kota Besar',
                'minimum_sku' => 'Minimum SKU',
                'multi_polygon_sales' => 'Multi Polygon Sales',
                'provinsi' => 'Provinsi',
                'kota_kab' => 'Kota/Kab',
                'kecamatan' => 'Kecamatan',
                'kelurahan_desa' => 'Kelurahan/Desa',
                'alamat' => 'Alamat',
                'longitude' => 'Longitude',
                'latitude' => 'Latitude',
                'total_koordinator' => 'Total Koordinator',
                'jumlah_sales_aktif' => 'Jumlah Sales Aktif',
                'jumlah_wilayah_depo' => 'Jumlah Wilayah Depo',
                'kuota_koordinator' => 'Kuota Koordinator',
                'kuota_sales_motoris' => 'Kuota Sales Motoris',
                'target_call' => 'Target Call',
                'target_ec' => 'Target EC',
                'durasi_tracking_gps' => 'Durasi Tracking GPS',
                'insentif_per_poin' => 'Insentif per Poin',
                'outlet_1_sku' => 'Outlet 1 SKU',
                'minimal_value_transaksi' => 'Minimal Value Transaksi',
                'diupdate_oleh' => 'Diupdate Oleh',
                'diupdate_pada' => 'Diupdate Pada',
                'status' => 'Status',
            ],
        ],
        'sales_tidak_absen' => [
            'title' => 'Sales Tidak Absen Pagi',
            'columns' => [
                'tanggal_data' => 'Tanggal Data',
                'no' => 'No',
                'depo' => 'Depo',
                'koordinator' => 'Koordinator',
                'nama_sales' => 'Nama Sales',
            ],
        ],
        'sales_tidak_penjualan' => [
            'title' => 'Sales Tidak Penjualan',
            'columns' => [
                'tanggal_data' => 'Tanggal Data',
                'no' => 'No',
                'depo' => 'Depo',
                'koordinator' => 'Koordinator',
                'nama_sales' => 'Nama Sales',
                'jumlah_visit_outlet' => 'Jumlah Visit Outlet',
            ],
        ],
        'user_koordinator' => [
            'title' => 'User Koordinator',
            'columns' => [
                'username' => 'Username',
                'nickname' => 'Nickname',
                'karyawan' => 'Karyawan',
                'depo' => 'Depo',
                'status' => 'Status',
                'keterangan' => 'Keterangan',
                'login_terakhir_pada' => 'Login Terakhir',
            ],
        ],
        'checkin_pagi' => [
            'title' => 'Sales Checkin Outlet Pagi',
            // Dihitung dari trx_kunjungan + mk_karyawan untuk tanggal terpilih (tabel checkin_pagi = salinan lama Marketama).
            'sumber' => SumberCheckinPagi::class,
            'columns' => [
                'no' => 'No',
                'depo' => 'Depo',
                'koordinator' => 'Koordinator',
                'nama_sales' => 'Nama Sales',
                'checkin_pertama_hari_ini' => 'Checkin Pertama Hari Ini',
            ],
        ],
        'monitor_absensi_motoris' => [
            'title' => 'Monitor Absensi Motoris',
            'columns' => [
                'no' => 'No',
                'nama_sales_motoris' => 'Nama Sales Motoris',
                'nama_koordinator' => 'Nama Koordinator',
                'nama_depo' => 'Nama Depo',
                'hari_kerja' => 'Hari Kerja',
                'total_kehadiran' => 'Total Kehadiran',
                'total_tidak_hadir' => 'Total Tidak Hadir',
            ],
        ],
        'lap_absensi_koordinator' => [
            'title' => 'Lap. Absensi Koordinator',
            'columns' => [
                'source_id' => 'ID',
                'date' => 'Date',
                'time_in' => 'Time In',
                'time_out' => 'Time Out',
                'employee_name' => 'Employee Name',
                'employee_phone' => 'Employee Phone',
                'category_name' => 'Category Name',
                'warehouse_name' => 'Warehouse Name',
            ],
        ],
        'absensi_sales_terlambat' => [
            'title' => 'Absensi Sales Terlambat',
            'columns' => [
                'nama_sales' => 'Nama Sales',
                'koordinator' => 'Koordinator',
                'depo' => 'Depo',
                'tanggal' => 'Tanggal',
                'jam_masuk' => 'Jam Masuk',
                'jam_wajib' => 'Jam Wajib',
                'telat' => 'Telat',
                'diupdate_oleh' => 'Diupdate Oleh',
                'diupdate_pada' => 'Diupdate Pada',
            ],
        ],
        'user_sales' => [
            'title' => 'User Sales',
            'columns' => [
                'username' => 'Username',
                'nickname' => 'Nickname',
                'karyawan' => 'Karyawan',
                'depo' => 'Depo',
                'koordinator' => 'Koordinator',
                'status' => 'Status',
                'keterangan' => 'Keterangan',
                'login_terakhir_pada' => 'Login Terakhir',
            ],
        ],
        'lap_outlet_noo' => [
            'title' => 'Lap. Outlet NOO',
            'sumber' => SumberOutletNoo::class,
            'columns' => [
                'source_id' => 'ID',
                'outlet_code' => 'Outlet Code',
                'outlet_name' => 'Outlet Name',
                'contact_person1' => 'Contact Person1',
                'contact_person2' => 'Contact Person2',
                'phone1' => 'Phone1',
                'phone2' => 'Phone2',
                'address' => 'Address',
                'location_lat' => 'Location Lat',
                'location_long' => 'Location Long',
                'area_name' => 'Area Name',
                'province_name' => 'Province Name',
                'city_name' => 'City Name',
                'district_name' => 'District Name',
                'village_name' => 'Village Name',
                'category_name' => 'Category Name',
                'created_by_name' => 'Created By Name',
                'created_at' => 'Created At',
                'warehouse_name' => 'Warehouse Name',
                'koordinator_name' => 'Koordinator Name',
                'status' => 'Status Verifikasi',
            ],
        ],
        'lap_stok_kompetitor' => [
            'title' => 'Lap. Stok Kompetitor',
            'sumber' => SumberStokKompetitor::class,
            'columns' => [
                'source_id' => 'ID',
                'quantity' => 'Quantity',
                'last_update' => 'Last Update',
                'salesperson_name' => 'Salesperson Name',
                'koordinator_name' => 'Koordinator Name',
                'date_checkin' => 'Date Checkin',
                'datetime_checkin' => 'Datetime Checkin',
                'datetime_checkout' => 'Datetime Checkout',
                'duration' => 'Duration',
                'outlet_name' => 'Outlet Name',
                'outlet_code' => 'Outlet Code',
                'outlet_address' => 'Outlet Address',
                'area_name' => 'Area Name',
                'product_name' => 'Product Name',
                'sumber' => 'Sumber',
            ],
        ],
        'absensi_koordinator' => [
            'title' => 'Absensi Koordinator',
            'columns' => [
                'nama_karyawan' => 'Nama Karyawan',
                'depo' => 'Depo',
                'tanggal' => 'Tanggal',
                'jam_masuk' => 'Jam Masuk',
                'jam_keluar' => 'Jam Keluar',
                'diupdate_oleh' => 'Diupdate Oleh',
                'diupdate_pada' => 'Diupdate Pada',
            ],
        ],
        'lap_absensi_sales' => [
            'title' => 'Lap. Absensi Sales',
            'columns' => [
                'source_id' => 'ID',
                'date' => 'Date',
                'time_in' => 'Time In',
                'time_out' => 'Time Out',
                'employee_name' => 'Employee Name',
                'employee_phone' => 'Employee Phone',
                'koordinator_name' => 'Koordinator Name',
                'warehouse_name' => 'Warehouse Name',
            ],
        ],
        'lap_checkin_checkout' => [
            'title' => 'Lap. Checkin Checkout',
            'columns' => [
                'source_id' => 'ID',
                'attendance_no' => 'Attendance No',
                'attendance_date' => 'Attendance Date',
                'checkin_time' => 'Checkin Time',
                'checkout_time' => 'Checkout Time',
                'duration' => 'Duration',
                'attendance_status' => 'Attendance Status',
                'location_long' => 'Location Long',
                'location_lat' => 'Location Lat',
                'warehouse_name' => 'Warehouse Name',
                'employee_name' => 'Employee Name',
                'updated_by_name' => 'Updated By Name',
                'updated_at' => 'Updated At',
            ],
        ],
        'login_session' => [
            'title' => 'Sesi Login',
            'sumber' => SumberSesiLogin::class,
            'columns' => [
                'username_nickname' => 'Username / Nickname',
                'kategori' => 'Kategori',
                'depo' => 'Depo',
                'nama_karyawan' => 'Nama Karyawan',
                'id_karyawan' => 'ID Karyawan',
                'nama_koordinator' => 'Nama Koordinator',
                'device_token' => 'Device / Token',
                'login_pada' => 'Login Pada',
                'terakhir_aktif' => 'Terakhir Aktif',
            ],
        ],
        'lap_barang_habis_terjual' => [
            'title' => 'Lap. Barang Habis Terjual',
            'sumber' => SumberBarangHabisTerjual::class,
            'columns' => [
                'source_id' => 'ID',
                'canvassing_no' => 'Canvassing No',
                'warehouse_name' => 'Warehouse Name',
                'area_name' => 'Area Name',
                'salesperson_name' => 'Salesperson Name',
                'koordinator_name' => 'Koordinator Name',
                'receive_date' => 'Receive Date',
                'product_sku' => 'Product SKU',
                'product_name' => 'Product Name',
                'category_name' => 'Category Name',
                'unit_name' => 'Unit Name',
                'product_unit' => 'Product Unit',
                'total_qty' => 'Total Qty',
                'sale_qty' => 'Sale Qty',
                'return_qty' => 'Return Qty',
                'sisa_qty' => 'Sisa Qty',
                'status_terjual' => 'Status Terjual',
                'pct_terjual' => 'Pct Terjual',
                'sumber' => 'Sumber',
            ],
        ],
        'lap_pengembalian_barang' => [
            'title' => 'Lap. Pengembalian Barang',
            'sumber' => SumberPengembalianBarang::class,
            'columns' => [
                'source_id' => 'ID',
                'canvas_return_no' => 'Canvas Return No',
                'canvassing_no' => 'Canvassing No',
                'return_date' => 'Return Date',
                'return_datetime' => 'Return Datetime',
                'warehouse_name' => 'Warehouse Name',
                'area_name' => 'Area Name',
                'salesperson_name' => 'Salesperson Name',
                'product_sku' => 'Product SKU',
                'product_name' => 'Product Name',
                'category_name' => 'Category Name',
                'brand_name' => 'Brand Name',
                'unit_name' => 'Unit Name',
                'product_unit' => 'Product Unit',
                'total_qty' => 'Total Qty',
                'sale_qty' => 'Sale Qty',
                'return_qty' => 'Return Qty',
                'sumber' => 'Sumber',
            ],
        ],
        'lap_barang_canvassing' => [
            'title' => 'Lap. Barang Canvassing',
            'sumber' => SumberCanvassing::class,
            'columns' => [
                'source_id' => 'ID',
                'canvassing_no' => 'Canvassing No',
                'warehouse_name' => 'Warehouse Name',
                'area_name' => 'Area Name',
                'salesperson_name' => 'Salesperson Name',
                'koordinator_name' => 'Koordinator Name',
                'receive_date' => 'Receive Date',
                'receive_datetime' => 'Receive Datetime',
                'product_name' => 'Product Name',
                'product_sku' => 'Product SKU',
                'category_name' => 'Category Name',
                'brand_name' => 'Brand Name',
                'unit_name' => 'Unit Name',
                'product_unit' => 'Product Unit',
                'total_qty' => 'Total Qty',
                'sale_qty' => 'Sale Qty',
                'return_qty' => 'Return Qty',
                'returned' => 'Returned',
                'sumber' => 'Sumber',
            ],
        ],
        'lap_outlet_oss' => [
            'title' => 'Lap. Outlet OSS',
            'sumber' => SumberOutletOss::class,
            'columns' => [
                'source_id' => 'ID',
                'outlet_code' => 'Outlet Code',
                'outlet_name' => 'Outlet Name',
                'contact_person1' => 'Contact Person1',
                'contact_person2' => 'Contact Person2',
                'phone1' => 'Phone1',
                'phone2' => 'Phone2',
                'address' => 'Address',
                'location_lat' => 'Location Lat',
                'location_long' => 'Location Long',
                'area_name' => 'Area Name',
                'province_name' => 'Province Name',
                'city_name' => 'City Name',
                'district_name' => 'District Name',
                'village_name' => 'Village Name',
                'category_name' => 'Category Name',
                'created_by_name' => 'Created By Name',
                'created_at' => 'Created At',
                'warehouse_name' => 'Warehouse Name',
                'koordinator_name' => 'Koordinator Name',
            ],
        ],
        'lap_effective_call' => [
            'title' => 'Lap. Efective Call',
            'sumber' => SumberEffectiveCall::class,
            'columns' => [
                'source_id' => 'ID',
                'trans_code' => 'Trans Code',
                'salesperson_name' => 'Salesperson Name',
                'koordinator_name' => 'Koordinator Name',
                'date_trans' => 'Date Trans',
                'datetime_trans' => 'Datetime Trans',
                'total_item' => 'Total Item',
                'total_qty' => 'Total Qty',
                'grand_total' => 'Grand Total',
                'outlet_name' => 'Outlet Name',
                'outlet_code' => 'Outlet Code',
                'outlet_longitude' => 'Outlet Longitude',
                'outlet_latitude' => 'Outlet Latitude',
                'outlet_address' => 'Outlet Address',
                'area_name' => 'Area Name',
                'warehouse_name' => 'Warehouse Name',
                'datetime_checkin' => 'Datetime Checkin',
                'datetime_checkout' => 'Datetime Checkout',
                'checkin_duration' => 'Checkin Duration',
                'sales_longitude' => 'Sales Longitude',
                'sales_latitude' => 'Sales Latitude',
                'created_by_name' => 'Created By Name',
                'created_at' => 'Created At',
                'sumber' => 'Sumber',
            ],
        ],
        'lap_penjualan_barang' => [
            'title' => 'Lap. Penjualan Barang',
            'sumber' => SumberPenjualan::class,
            'columns' => [
                'source_id' => 'ID',
                'trans_code' => 'Trans Code',
                'salesperson_name' => 'Salesperson Name',
                'koordinator_name' => 'Koordinator Name',
                'date_trans' => 'Date Trans',
                'datetime_trans' => 'Datetime Trans',
                'effective_call_status' => 'Effective Call Status',
                'total_item' => 'Total Item',
                'total_qty' => 'Total Qty',
                'grand_total' => 'Grand Total',
                'outlet_name' => 'Outlet Name',
                'outlet_code' => 'Outlet Code',
                'outlet_longitude' => 'Outlet Longitude',
                'outlet_latitude' => 'Outlet Latitude',
                'outlet_address' => 'Outlet Address',
                'area_name' => 'Area Name',
                'warehouse_name' => 'Warehouse Name',
                'datetime_checkin' => 'Datetime Checkin',
                'datetime_checkout' => 'Datetime Checkout',
                'checkin_duration' => 'Checkin Duration',
                'sales_longitude' => 'Sales Longitude',
                'sales_latitude' => 'Sales Latitude',
                'created_by_name' => 'Created By Name',
                'created_at' => 'Created At',
                'sumber' => 'Sumber',
            ],
        ],
        'lap_outlet_ac' => [
            'title' => 'Lap. Outlet AC',
            'sumber' => SumberOutletAc::class,
            'columns' => [
                'source_id' => 'ID',
                'outlet_code' => 'Outlet Code',
                'outlet_name' => 'Outlet Name',
                'contact_person1' => 'Contact Person1',
                'contact_person2' => 'Contact Person2',
                'phone1' => 'Phone1',
                'phone2' => 'Phone2',
                'address' => 'Address',
                'location_lat' => 'Location Lat',
                'location_long' => 'Location Long',
                'area_name' => 'Area Name',
                'province_name' => 'Province Name',
                'city_name' => 'City Name',
                'district_name' => 'District Name',
                'village_name' => 'Village Name',
                'category_name' => 'Category Name',
                'created_by_name' => 'Created By Name',
                'created_at' => 'Created At',
                'warehouse_name' => 'Warehouse Name',
                'koordinator_name' => 'Koordinator Name',
            ],
        ],
        'lap_checkin_outlet' => [
            'title' => 'Lap. Checkin Outlet',
            'columns' => [
                'source_id' => 'ID',
                'salesperson_name' => 'Salesperson Name',
                'koordinator_name' => 'Koordinator Name',
                'date_checkin' => 'Date Checkin',
                'datetime_checkin' => 'Datetime Checkin',
                'datetime_checkout' => 'Datetime Checkout',
                'duration' => 'Duration',
                'description' => 'Description',
                'category' => 'Category',
                'photo' => 'Photo',
                'sales_longitude' => 'Sales Longitude',
                'sales_latitude' => 'Sales Latitude',
                'outlet_name' => 'Outlet Name',
                'outlet_code' => 'Outlet Code',
                'outlet_longitude' => 'Outlet Longitude',
                'outlet_latitude' => 'Outlet Latitude',
                'outlet_address' => 'Outlet Address',
                'area_name' => 'Area Name',
                'warehouse_name' => 'Warehouse Name',
            ],
        ],
        'lap_outlet_ec' => [
            'title' => 'Lap. Outlet EC',
            'sumber' => SumberOutletEc::class,
            'columns' => [
                'source_id' => 'ID',
                'outlet_code' => 'Outlet Code',
                'outlet_name' => 'Outlet Name',
                'contact_person1' => 'Contact Person1',
                'contact_person2' => 'Contact Person2',
                'phone1' => 'Phone1',
                'phone2' => 'Phone2',
                'address' => 'Address',
                'location_lat' => 'Location Lat',
                'location_long' => 'Location Long',
                'area_name' => 'Area Name',
                'province_name' => 'Province Name',
                'city_name' => 'City Name',
                'district_name' => 'District Name',
                'village_name' => 'Village Name',
                'category_name' => 'Category Name',
                'created_by_name' => 'Created By Name',
                'created_at' => 'Created At',
                'warehouse_name' => 'Warehouse Name',
                'koordinator_name' => 'Koordinator Name',
            ],
        ],
        'lap_effective_call_barang' => [
            'title' => 'Lap. Efective Call Detail',
            'sumber' => SumberEffectiveCallItem::class,
            'columns' => [
                'source_id' => 'ID',
                'trans_code' => 'Trans Code',
                'salesperson_name' => 'Salesperson Name',
                'koordinator_name' => 'Koordinator Name',
                'date_trans' => 'Date Trans',
                'datetime_trans' => 'Datetime Trans',
                'product_name' => 'Product Name',
                'product_sku' => 'Product SKU',
                'category_name' => 'Category Name',
                'brand_name' => 'Brand Name',
                'unit_name' => 'Unit Name',
                'quantity' => 'Quantity',
                'price' => 'Price',
                'discount' => 'Discount',
                'total' => 'Total',
                'total_discount' => 'Total Discount',
                'outlet_name' => 'Outlet Name',
                'outlet_code' => 'Outlet Code',
                'outlet_longitude' => 'Outlet Longitude',
                'outlet_latitude' => 'Outlet Latitude',
                'area_name' => 'Area Name',
                'warehouse_name' => 'Warehouse Name',
                'datetime_checkin' => 'Datetime Checkin',
                'datetime_checkout' => 'Datetime Checkout',
                'checkin_duration' => 'Checkin Duration',
                'sales_longitude' => 'Sales Longitude',
                'sales_latitude' => 'Sales Latitude',
                'sumber' => 'Sumber',
            ],
        ],
        'lap_penjualan_barang_detail' => [
            'title' => 'Lap. Penjualan Barang Detail',
            'sumber' => SumberPenjualanItem::class,
            'columns' => [
                'source_id' => 'ID',
                'trans_code' => 'Trans Code',
                'effective_call_status' => 'Effective Call Status',
                'salesperson_name' => 'Salesperson Name',
                'koordinator_name' => 'Koordinator Name',
                'date_trans' => 'Date Trans',
                'datetime_trans' => 'Datetime Trans',
                'product_name' => 'Product Name',
                'product_sku' => 'Product SKU',
                'category_name' => 'Category Name',
                'brand_name' => 'Brand Name',
                'unit_name' => 'Unit Name',
                'quantity' => 'Quantity',
                'price' => 'Price',
                'discount' => 'Discount',
                'total' => 'Total',
                'total_discount' => 'Total Discount',
                'outlet_name' => 'Outlet Name',
                'outlet_code' => 'Outlet Code',
                'outlet_longitude' => 'Outlet Longitude',
                'outlet_latitude' => 'Outlet Latitude',
                'area_name' => 'Area Name',
                'warehouse_name' => 'Warehouse Name',
                'datetime_checkin' => 'Datetime Checkin',
                'datetime_checkout' => 'Datetime Checkout',
                'checkin_duration' => 'Checkin Duration',
                'sales_longitude' => 'Sales Longitude',
                'sales_latitude' => 'Sales Latitude',
                'sumber' => 'Sumber',
            ],
        ],
        // 'sumber': kelas App\Support\Menu\SumberTabel yang membuat query (join ke master area/depo/karyawan).
        'mk_outlet' => [
            'title' => 'Master Outlet',
            'sumber' => SumberOutletMaster::class,
            'columns' => [
                'id' => 'ID',
                'kode' => 'Kode Outlet',
                'nama' => 'Nama Outlet',
                'alamat' => 'Alamat',
                'area' => 'Area',
                'depo' => 'Depo',
                'provinsi' => 'Provinsi',
                'kota_kabupaten' => 'Kota/Kabupaten',
                'kecamatan' => 'Kecamatan',
                'kelurahan' => 'Kelurahan',
                'latitude' => 'Latitude',
                'longitude' => 'Longitude',
                'verifikasi' => 'Verifikasi',
                'status_fiktif' => 'Status Fiktif',
                'dibuat_oleh' => 'Dibuat Oleh',
                'dibuat_pada' => 'Dibuat Pada',
            ],
        ],
        'trx_outlet_baru' => [
            'title' => 'Outlet Baru dari HP',
            'sumber' => SumberOutletBaru::class,
            // Tombol Setujui / Tolak + tautan foto (Menu/Show.vue), untuk akun dengan hak ubah outlet_all.
            'aksi' => 'verifikasi_outlet_baru',
            'columns' => [
                'id' => 'ID',
                'status' => 'Status',
                'nama' => 'Nama Outlet',
                'pemilik' => 'Pemilik',
                'no_hp' => 'No. HP',
                'tipe' => 'Tipe',
                'alamat' => 'Alamat',
                'sales' => 'Sales',
                'depo' => 'Depo',
                'area' => 'Area',
                'lat' => 'Latitude',
                'lng' => 'Longitude',
                'outlet_id' => 'ID Master Outlet',
                'recorded_at' => 'Dicatat di HP',
                'alasan_tolak' => 'Alasan Tolak',
            ],
        ],
        'trx_join_visit' => [
            'title' => 'Join Visit',
            'sumber' => SumberJoinVisit::class,
            // Tombol Foto (Menu/Show.vue) bila ada foto_id; cukup hak lihat.
            'aksi' => 'foto',
            'aksi_hak' => 'lihat',
            'columns' => [
                'id' => 'ID',
                'tanggal' => 'Tanggal',
                'koordinator' => 'Koordinator',
                'sales' => 'Sales',
                'kode_outlet' => 'Kode Outlet',
                'outlet' => 'Outlet',
                'area' => 'Area',
                'depo' => 'Depo',
                'mulai' => 'Mulai',
                'selesai' => 'Selesai',
                'durasi' => 'Durasi',
                'jarak_m' => 'Jarak ke Outlet (m)',
                'catatan' => 'Catatan',
                'sumber' => 'Sumber',
            ],
        ],
        'lap_outlet_all' => [
            'title' => 'Lap. Outlet All',
            'sumber' => SumberOutletStatus::class,
            'columns' => [
                'source_id' => 'ID',
                'outlet_code' => 'Outlet Code',
                'outlet_name' => 'Outlet Name',
                'contact_person1' => 'Contact Person1',
                'contact_person2' => 'Contact Person2',
                'phone1' => 'Phone1',
                'phone2' => 'Phone2',
                'address' => 'Address',
                'location_lat' => 'Location Lat',
                'location_long' => 'Location Long',
                'area_name' => 'Area Name',
                'province_name' => 'Province Name',
                'city_name' => 'City Name',
                'district_name' => 'District Name',
                'village_name' => 'Village Name',
                'category_name' => 'Category Name',
                'created_by_name' => 'Created By Name',
                'created_at' => 'Created At',
                'warehouse_name' => 'Warehouse Name',
                'koordinator_name' => 'Koordinator Name',
            ],
        ],
        'lap_stok_outlet' => [
            'title' => 'Lap. Stok Outlet',
            'sumber' => SumberStokOutlet::class,
            'columns' => [
                'source_id' => 'ID',
                'quantity' => 'Quantity',
                'unit_name' => 'Unit Name',
                'last_update' => 'Last Update',
                'salesperson_name' => 'Salesperson Name',
                'koordinator_name' => 'Koordinator Name',
                'date_checkin' => 'Date Checkin',
                'datetime_checkin' => 'Datetime Checkin',
                'datetime_checkout' => 'Datetime Checkout',
                'duration' => 'Duration',
                'category' => 'Category',
                'outlet_name' => 'Outlet Name',
                'outlet_code' => 'Outlet Code',
                'outlet_address' => 'Outlet Address',
                'area_name' => 'Area Name',
                'warehouse_name' => 'Warehouse Name',
                'product_code' => 'Product Code',
                'product_name' => 'Product Name',
                'category_name' => 'Category Name',
                'brand_name' => 'Brand Name',
                'sumber' => 'Sumber',
            ],
        ],
        'rekap_pencapaian_sku_motoris_tableachieved' => [
            'title' => 'Rekap Pencapaian SKU per Motoris - tableAchieved',
            'columns' => [
                'no' => 'No',
                'depo' => 'Depo',
                'motoris' => 'Motoris',
                'total_qty' => 'Total Qty',
                'hari_kerja' => 'Hari Kerja',
                'avg_day' => 'Avg/Day',
                'kategori' => 'Kategori',
            ],
        ],
        'rekap_pencapaian_sku_motoris_tablenotachieved' => [
            'title' => 'Rekap Pencapaian SKU per Motoris - tableNotAchieved',
            'columns' => [
                'no' => 'No',
                'depo' => 'Depo',
                'motoris' => 'Motoris',
                'total_qty' => 'Total Qty',
                'hari_kerja' => 'Hari Kerja',
                'avg_day' => 'Avg/Day',
                'kategori' => 'Kategori',
            ],
        ],
        'rekap_pencapaian_depo_sku_tableachieved' => [
            'title' => 'Rekap Pencapaian SKU per Depo - tableAchieved',
            'columns' => [
                'no' => 'No',
                'depo' => 'Depo',
                'total_qty' => 'Total Qty',
                'hari_kerja' => 'Hari Kerja',
                'avg_day_motoris' => 'Avg/Day /Motoris',
                'kategori' => 'Kategori',
            ],
        ],
        'rekap_pencapaian_depo_sku_tablenotachieved' => [
            'title' => 'Rekap Pencapaian SKU per Depo - tableNotAchieved',
            'columns' => [
                'no' => 'No',
                'depo' => 'Depo',
                'total_qty' => 'Total Qty',
                'hari_kerja' => 'Hari Kerja',
                'avg_day_motoris' => 'Avg/Day /Motoris',
                'kategori' => 'Kategori',
            ],
        ],
        'motoris_coverage' => [
            'title' => 'Motoris Coverage',
            'columns' => [
                '11_city' => '11 CITY',
                'quota_motoris' => 'QUOTA MOTORIS',
                'alreadytransaction' => 'AlreadyTransaction',
                'active_end_periode' => 'ACTIVE END PERIODE',
                'vacant' => 'VACANT',
                'coverage' => 'COVERAGE %',
            ],
        ],
        'motoris_performance' => [
            'title' => 'Motoris Performance',
            'columns' => [
                '11_city' => '11 City',
                'motoris_kuota' => 'Motoris Kuota',
                'motoris_already_transaction' => 'Motoris Already Transaction',
                'motoris_actual_end_periode' => 'Motoris Actual End Periode',
                'hk_standar' => 'HK Standar',
                'act_hk' => 'Act HK',
                'target' => 'Target',
                'call' => 'Call',
                'ec' => 'EC',
                'ipt' => 'IPT',
                'value' => 'Value',
            ],
        ],
        'waktu_aktif_app' => [
            'title' => 'Hari Kerja Aktif',
            'columns' => [
                'no' => 'No',
                'nama_hari' => 'Nama Hari',
                'waktu_mulai' => 'Waktu Mulai',
                'waktu_berakhir' => 'Waktu Berakhir',
                'sales' => 'Sales',
                'koordinator' => 'Koordinator',
                'update_terakhir' => 'Update Terakhir',
                'kolom' => '',
            ],
        ],
        'rumus_insentif_sales' => [
            'title' => 'Rumus Insentif Sales',
            'columns' => [
                'source_id' => 'ID',
                'deskripsi' => 'Deskripsi',
                'tot_ec_lower_bound' => 'Tot. EC Lower Bound',
                'tot_ec_upper_bound' => 'Tot. EC Upper Bound',
                'total_insentif' => 'Total Insentif',
            ],
        ],
    ],
];
