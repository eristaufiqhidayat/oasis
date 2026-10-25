<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Satu tabel untuk setiap menu Oasis (terhubung ke data motoris) yang punya data.
// Kolom mengikuti hasil penarikan data (lihat config/marketama.php untuk label kolom).
return new class extends Migration
{
    public function up(): void
    {
        // Lap. Insentif Sales
        Schema::create('lap_insentif_sales', function (Blueprint $table) {
            $table->id();
            $table->string('no_payroll', 255)->nullable();
            $table->string('periode', 255)->nullable();
            $table->string('nama_sales', 255)->nullable();
            $table->string('telepon', 255)->nullable();
            $table->string('koordinator', 255)->nullable();
            $table->string('depo', 255)->nullable();
            $table->string('insentif_ec', 255)->nullable();
            $table->string('insentif_absensi', 255)->nullable();
            $table->string('total_insentif', 255)->nullable();
        });

        // Lap. Outlet RO
        Schema::create('lap_outlet_ro', function (Blueprint $table) {
            $table->id();
            $table->string('depo', 255)->nullable();
            $table->string('koordinator', 255)->nullable();
            $table->string('kode_outlet', 255)->nullable();
            $table->string('nama_outlet', 255)->nullable();
            $table->string('contact_person', 255)->nullable();
            $table->string('telepon', 255)->nullable();
            $table->string('alamat', 255)->nullable();
            $table->string('area', 255)->nullable();
            $table->string('provinsi', 255)->nullable();
            $table->string('kota_kab', 255)->nullable();
            $table->string('kecamatan', 255)->nullable();
            $table->string('kelurahan', 255)->nullable();
            $table->string('latitude', 255)->nullable();
            $table->string('longitude', 255)->nullable();
            $table->string('dibuat_oleh', 255)->nullable();
            $table->string('dibuat_pada', 255)->nullable();
        });

        // Produk Kompetitor
        Schema::create('produk_kompetitor', function (Blueprint $table) {
            $table->id();
            $table->string('nama_produk', 255)->nullable();
            $table->string('satuan', 255)->nullable();
            $table->string('diupdate_oleh', 255)->nullable();
            $table->string('diupdate_pada', 255)->nullable();
        });

        // Tipe Outlet
        Schema::create('tipe_outlet', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('source_id')->nullable();
            $table->string('kode', 255)->nullable();
            $table->string('tipe_outlet', 255)->nullable();
            $table->index('source_id');
        });

        // Lap. Barang Terbaik Harian
        Schema::create('lap_penjualan_barang_terbaik_harian', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal_data')->nullable();
            $table->bigInteger('product_id')->nullable();
            $table->string('product_name', 255)->nullable();
            $table->bigInteger('product_sku')->nullable();
            $table->string('category_name', 255)->nullable();
            $table->string('brand_name', 255)->nullable();
            $table->string('unit_name', 255)->nullable();
            $table->decimal('total_qty', 20, 4)->nullable();
            $table->decimal('total_amount', 20, 4)->nullable();
            $table->decimal('total_discount', 20, 4)->nullable();
            $table->bigInteger('total_transaksi')->nullable();
        });

        // Lap. Barang Terbaik Mingguan
        Schema::create('lap_penjualan_barang_terbaik_mingguan', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('product_id')->nullable();
            $table->string('product_name', 255)->nullable();
            $table->bigInteger('product_sku')->nullable();
            $table->string('category_name', 255)->nullable();
            $table->string('brand_name', 255)->nullable();
            $table->string('unit_name', 255)->nullable();
            $table->bigInteger('total_qty')->nullable();
            $table->decimal('total_amount', 20, 4)->nullable();
            $table->decimal('total_discount', 20, 4)->nullable();
            $table->bigInteger('total_transaksi')->nullable();
        });

        // Tanggal Libur
        Schema::create('holiday_dates', function (Blueprint $table) {
            $table->id();
            $table->string('nama_libur', 255)->nullable();
            $table->string('tanggal', 255)->nullable();
            $table->string('keterangan', 255)->nullable();
            $table->string('diupdate_oleh', 255)->nullable();
            $table->string('diupdate_pada', 255)->nullable();
        });

        // Lap. Barang Terbaik Bulanan
        Schema::create('lap_penjualan_barang_terbaik_bulanan', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('product_id')->nullable();
            $table->string('product_name', 255)->nullable();
            $table->bigInteger('product_sku')->nullable();
            $table->string('category_name', 255)->nullable();
            $table->string('brand_name', 255)->nullable();
            $table->string('unit_name', 255)->nullable();
            $table->decimal('total_qty', 20, 4)->nullable();
            $table->decimal('total_amount', 20, 4)->nullable();
            $table->decimal('total_discount', 20, 4)->nullable();
            $table->bigInteger('total_transaksi')->nullable();
        });

        // Konversi Poin Per Depo
        Schema::create('sales_point_conversion_per_warehouse', function (Blueprint $table) {
            $table->id();
            $table->string('depo', 255)->nullable();
            $table->bigInteger('poin_insentif_rp')->nullable();
            $table->string('diupdate_oleh', 255)->nullable();
            $table->string('diupdate_pada', 255)->nullable();
        });

        // Master Warehouse / Depo
        Schema::create('master_depo', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 255)->nullable();
            $table->string('nama', 255)->nullable();
            $table->string('projek', 255)->nullable();
            $table->string('kota_besar', 255)->nullable();
            $table->string('minimum_sku', 255)->nullable();
            $table->string('multi_polygon_sales', 255)->nullable();
            $table->string('provinsi', 255)->nullable();
            $table->string('kota_kab', 255)->nullable();
            $table->string('kecamatan', 255)->nullable();
            $table->string('kelurahan_desa', 255)->nullable();
            $table->string('alamat', 255)->nullable();
            $table->decimal('longitude', 20, 4)->nullable();
            $table->decimal('latitude', 20, 4)->nullable();
            $table->bigInteger('total_koordinator')->nullable();
            $table->bigInteger('jumlah_sales_aktif')->nullable();
            $table->bigInteger('jumlah_wilayah_depo')->nullable();
            $table->bigInteger('kuota_koordinator')->nullable();
            $table->bigInteger('kuota_sales_motoris')->nullable();
            $table->bigInteger('target_call')->nullable();
            $table->bigInteger('target_ec')->nullable();
            $table->bigInteger('durasi_tracking_gps')->nullable();
            $table->bigInteger('insentif_per_poin')->nullable();
            $table->bigInteger('outlet_1_sku')->nullable();
            $table->bigInteger('minimal_value_transaksi')->nullable();
            $table->string('diupdate_oleh', 255)->nullable();
            $table->string('diupdate_pada', 255)->nullable();
            $table->string('status', 255)->nullable();
        });

        // Sales Tidak Absen Pagi
        Schema::create('sales_tidak_absen', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal_data')->nullable();
            $table->bigInteger('no')->nullable();
            $table->string('depo', 255)->nullable();
            $table->string('koordinator', 255)->nullable();
            $table->string('nama_sales', 255)->nullable();
        });

        // Sales Tidak Penjualan
        Schema::create('sales_tidak_penjualan', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal_data')->nullable();
            $table->bigInteger('no')->nullable();
            $table->string('depo', 255)->nullable();
            $table->string('koordinator', 255)->nullable();
            $table->string('nama_sales', 255)->nullable();
            $table->string('jumlah_visit_outlet', 255)->nullable();
        });

        // User Koordinator
        Schema::create('user_koordinator', function (Blueprint $table) {
            $table->id();
            $table->string('username', 255)->nullable();
            $table->string('nickname', 255)->nullable();
            $table->string('karyawan', 255)->nullable();
            $table->string('depo', 255)->nullable();
            $table->string('status', 255)->nullable();
            $table->string('diupdate_oleh', 255)->nullable();
            $table->string('diupdate_pada', 255)->nullable();
        });

        // checkin_pagi
        Schema::create('checkin_pagi', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal_data')->nullable();
            $table->bigInteger('no')->nullable();
            $table->string('depo', 255)->nullable();
            $table->string('koordinator', 255)->nullable();
            $table->string('nama_sales', 255)->nullable();
            $table->string('checkin_pertama_hari_ini', 255)->nullable();
        });

        // Monitor Absensi Motoris
        Schema::create('monitor_absensi_motoris', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('no')->nullable();
            $table->string('nama_sales_motoris', 255)->nullable();
            $table->string('nama_koordinator', 255)->nullable();
            $table->string('nama_depo', 255)->nullable();
            $table->bigInteger('hari_kerja')->nullable();
            $table->bigInteger('total_kehadiran')->nullable();
            $table->bigInteger('total_tidak_hadir')->nullable();
        });

        // Lap. Absensi Koordinator
        Schema::create('lap_absensi_koordinator', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('source_id')->nullable();
            $table->date('date')->nullable();
            $table->dateTime('time_in')->nullable();
            $table->dateTime('time_out')->nullable();
            $table->string('employee_name', 255)->nullable();
            $table->string('employee_phone', 255)->nullable();
            $table->string('category_name', 255)->nullable();
            $table->string('warehouse_name', 255)->nullable();
            $table->index('source_id');
        });

        // Absensi Sales Terlambat
        Schema::create('absensi_sales_terlambat', function (Blueprint $table) {
            $table->id();
            $table->string('nama_sales', 255)->nullable();
            $table->string('koordinator', 255)->nullable();
            $table->string('depo', 255)->nullable();
            $table->string('tanggal', 255)->nullable();
            $table->string('jam_masuk', 255)->nullable();
            $table->string('jam_wajib', 255)->nullable();
            $table->string('telat', 255)->nullable();
            $table->string('diupdate_oleh', 255)->nullable();
            $table->string('diupdate_pada', 255)->nullable();
        });

        // User Sales
        Schema::create('user_sales', function (Blueprint $table) {
            $table->id();
            $table->string('username', 255)->nullable();
            $table->string('nickname', 255)->nullable();
            $table->string('karyawan', 255)->nullable();
            $table->string('depo', 255)->nullable();
            $table->string('koordinator', 255)->nullable();
            $table->string('status', 255)->nullable();
            $table->string('diupdate_oleh', 255)->nullable();
            $table->string('diupdate_pada', 255)->nullable();
        });

        // Lap. Outlet NOO
        Schema::create('lap_outlet_noo', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('source_id')->nullable();
            $table->string('outlet_code', 255)->nullable();
            $table->string('outlet_name', 255)->nullable();
            $table->string('contact_person1', 255)->nullable();
            $table->string('contact_person2', 255)->nullable();
            $table->string('phone1', 255)->nullable();
            $table->string('phone2', 255)->nullable();
            $table->string('address', 255)->nullable();
            $table->decimal('location_lat', 20, 4)->nullable();
            $table->decimal('location_long', 20, 4)->nullable();
            $table->string('area_name', 255)->nullable();
            $table->string('province_name', 255)->nullable();
            $table->string('city_name', 255)->nullable();
            $table->string('district_name', 255)->nullable();
            $table->string('village_name', 255)->nullable();
            $table->string('category_name', 255)->nullable();
            $table->string('created_by_name', 255)->nullable();
            $table->dateTime('created_at')->nullable();
            $table->string('warehouse_name', 255)->nullable();
            $table->string('koordinator_name', 255)->nullable();
            $table->index('source_id');
        });

        // Lap. Stok Kompetitor
        Schema::create('lap_stok_kompetitor', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('source_id')->nullable();
            $table->bigInteger('quantity')->nullable();
            $table->dateTime('last_update')->nullable();
            $table->string('salesperson_name', 255)->nullable();
            $table->string('koordinator_name', 255)->nullable();
            $table->date('date_checkin')->nullable();
            $table->dateTime('datetime_checkin')->nullable();
            $table->dateTime('datetime_checkout')->nullable();
            $table->bigInteger('duration')->nullable();
            $table->string('outlet_name', 255)->nullable();
            $table->string('outlet_code', 255)->nullable();
            $table->string('outlet_address', 255)->nullable();
            $table->string('area_name', 255)->nullable();
            $table->string('product_name', 255)->nullable();
            $table->index('source_id');
        });

        // Absensi Koordinator
        Schema::create('absensi_koordinator', function (Blueprint $table) {
            $table->id();
            $table->string('nama_karyawan', 255)->nullable();
            $table->string('depo', 255)->nullable();
            $table->string('tanggal', 255)->nullable();
            $table->string('jam_masuk', 255)->nullable();
            $table->string('jam_keluar', 255)->nullable();
            $table->string('diupdate_oleh', 255)->nullable();
            $table->string('diupdate_pada', 255)->nullable();
        });

        // Lap. Absensi Sales
        Schema::create('lap_absensi_sales', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('source_id')->nullable();
            $table->date('date')->nullable();
            $table->dateTime('time_in')->nullable();
            $table->dateTime('time_out')->nullable();
            $table->string('employee_name', 255)->nullable();
            $table->string('employee_phone', 255)->nullable();
            $table->string('koordinator_name', 255)->nullable();
            $table->string('warehouse_name', 255)->nullable();
            $table->index('source_id');
        });

        // Lap. Checkin Checkout
        Schema::create('lap_checkin_checkout', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('source_id')->nullable();
            $table->string('attendance_no', 255)->nullable();
            $table->date('attendance_date')->nullable();
            $table->dateTime('checkin_time')->nullable();
            $table->dateTime('checkout_time')->nullable();
            $table->string('duration', 255)->nullable();
            $table->bigInteger('attendance_status')->nullable();
            $table->decimal('location_long', 20, 4)->nullable();
            $table->decimal('location_lat', 20, 4)->nullable();
            $table->string('warehouse_name', 255)->nullable();
            $table->string('employee_name', 255)->nullable();
            $table->string('updated_by_name', 255)->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->index('source_id');
        });

        // Lap. Join Visit
        Schema::create('lap_join_visit', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('source_id')->nullable();
            $table->string('koordinator_name', 255)->nullable();
            $table->string('sales_name', 255)->nullable();
            $table->dateTime('inspection_date')->nullable();
            $table->dateTime('start_time')->nullable();
            $table->dateTime('end_time')->nullable();
            $table->string('description', 255)->nullable();
            $table->string('outlet_name', 255)->nullable();
            $table->string('outlet_code', 255)->nullable();
            $table->string('outlet_address', 255)->nullable();
            $table->string('area_name', 255)->nullable();
            $table->string('warehouse_name', 255)->nullable();
            $table->string('photo', 255)->nullable();
            $table->string('created_by_name', 255)->nullable();
            $table->dateTime('created_at')->nullable();
            $table->string('updated_by_name', 255)->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->index('source_id');
        });

        // Sesi Login
        Schema::create('login_session', function (Blueprint $table) {
            $table->id();
            $table->string('username_nickname', 255)->nullable();
            $table->string('kategori', 255)->nullable();
            $table->string('depo', 255)->nullable();
            $table->string('nama_karyawan', 255)->nullable();
            $table->bigInteger('id_karyawan')->nullable();
            $table->string('nama_koordinator', 255)->nullable();
            $table->string('device_token', 255)->nullable();
            $table->string('login_pada', 255)->nullable();
            $table->string('terakhir_aktif', 255)->nullable();
        });

        // Lap. Barang Habis Terjual
        Schema::create('lap_barang_habis_terjual', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('source_id')->nullable();
            $table->string('canvassing_no', 255)->nullable();
            $table->string('warehouse_name', 255)->nullable();
            $table->string('area_name', 255)->nullable();
            $table->string('salesperson_name', 255)->nullable();
            $table->string('koordinator_name', 255)->nullable();
            $table->date('receive_date')->nullable();
            $table->bigInteger('product_sku')->nullable();
            $table->string('product_name', 255)->nullable();
            $table->string('category_name', 255)->nullable();
            $table->string('unit_name', 255)->nullable();
            $table->string('product_unit', 255)->nullable();
            $table->bigInteger('total_qty')->nullable();
            $table->decimal('sale_qty', 20, 4)->nullable();
            $table->bigInteger('return_qty')->nullable();
            $table->decimal('sisa_qty', 20, 4)->nullable();
            $table->string('status_terjual', 255)->nullable();
            $table->decimal('pct_terjual', 20, 4)->nullable();
            $table->index('source_id');
        });

        // Lap. Pengembalian Barang
        Schema::create('lap_pengembalian_barang', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('source_id')->nullable();
            $table->string('canvas_return_no', 255)->nullable();
            $table->string('canvassing_no', 255)->nullable();
            $table->date('return_date')->nullable();
            $table->dateTime('return_datetime')->nullable();
            $table->string('warehouse_name', 255)->nullable();
            $table->string('area_name', 255)->nullable();
            $table->string('salesperson_name', 255)->nullable();
            $table->string('product_sku', 255)->nullable();
            $table->string('product_name', 255)->nullable();
            $table->string('category_name', 255)->nullable();
            $table->string('brand_name', 255)->nullable();
            $table->string('unit_name', 255)->nullable();
            $table->string('product_unit', 255)->nullable();
            $table->bigInteger('total_qty')->nullable();
            $table->decimal('sale_qty', 20, 4)->nullable();
            $table->decimal('return_qty', 20, 4)->nullable();
            $table->index('source_id');
        });

        // Lap. Barang Canvassing
        Schema::create('lap_barang_canvassing', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('source_id')->nullable();
            $table->string('canvassing_no', 255)->nullable();
            $table->string('warehouse_name', 255)->nullable();
            $table->string('area_name', 255)->nullable();
            $table->string('salesperson_name', 255)->nullable();
            $table->string('koordinator_name', 255)->nullable();
            $table->date('receive_date')->nullable();
            $table->dateTime('receive_datetime')->nullable();
            $table->string('product_name', 255)->nullable();
            $table->string('product_sku', 255)->nullable();
            $table->string('category_name', 255)->nullable();
            $table->string('brand_name', 255)->nullable();
            $table->string('unit_name', 255)->nullable();
            $table->string('product_unit', 255)->nullable();
            $table->bigInteger('total_qty')->nullable();
            $table->decimal('sale_qty', 20, 4)->nullable();
            $table->decimal('return_qty', 20, 4)->nullable();
            $table->bigInteger('returned')->nullable();
            $table->index('source_id');
        });

        // Lap. Outlet OSS
        Schema::create('lap_outlet_oss', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('source_id')->nullable();
            $table->string('outlet_code', 255)->nullable();
            $table->string('outlet_name', 255)->nullable();
            $table->string('contact_person1', 255)->nullable();
            $table->string('contact_person2', 255)->nullable();
            $table->string('phone1', 255)->nullable();
            $table->string('phone2', 255)->nullable();
            $table->string('address', 255)->nullable();
            $table->decimal('location_lat', 20, 4)->nullable();
            $table->decimal('location_long', 20, 4)->nullable();
            $table->string('area_name', 255)->nullable();
            $table->string('province_name', 255)->nullable();
            $table->string('city_name', 255)->nullable();
            $table->string('district_name', 255)->nullable();
            $table->string('village_name', 255)->nullable();
            $table->string('category_name', 255)->nullable();
            $table->string('created_by_name', 255)->nullable();
            $table->dateTime('created_at')->nullable();
            $table->string('warehouse_name', 255)->nullable();
            $table->string('koordinator_name', 255)->nullable();
            $table->index('source_id');
        });

        // Lap. Efective Call
        Schema::create('lap_effective_call', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('source_id')->nullable();
            $table->string('trans_code', 255)->nullable();
            $table->string('salesperson_name', 255)->nullable();
            $table->string('koordinator_name', 255)->nullable();
            $table->date('date_trans')->nullable();
            $table->dateTime('datetime_trans')->nullable();
            $table->bigInteger('total_item')->nullable();
            $table->decimal('total_qty', 20, 4)->nullable();
            $table->decimal('grand_total', 20, 4)->nullable();
            $table->string('outlet_name', 255)->nullable();
            $table->string('outlet_code', 255)->nullable();
            $table->decimal('outlet_longitude', 20, 4)->nullable();
            $table->decimal('outlet_latitude', 20, 4)->nullable();
            $table->string('outlet_address', 255)->nullable();
            $table->string('area_name', 255)->nullable();
            $table->string('warehouse_name', 255)->nullable();
            $table->dateTime('datetime_checkin')->nullable();
            $table->dateTime('datetime_checkout')->nullable();
            $table->bigInteger('checkin_duration')->nullable();
            $table->decimal('sales_longitude', 20, 4)->nullable();
            $table->decimal('sales_latitude', 20, 4)->nullable();
            $table->string('created_by_name', 255)->nullable();
            $table->dateTime('created_at')->nullable();
            $table->index('source_id');
        });

        // Lap. Penjualan Barang
        Schema::create('lap_penjualan_barang', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('source_id')->nullable();
            $table->string('trans_code', 255)->nullable();
            $table->string('salesperson_name', 255)->nullable();
            $table->string('koordinator_name', 255)->nullable();
            $table->date('date_trans')->nullable();
            $table->dateTime('datetime_trans')->nullable();
            $table->bigInteger('effective_call_status')->nullable();
            $table->bigInteger('total_item')->nullable();
            $table->decimal('total_qty', 20, 4)->nullable();
            $table->decimal('grand_total', 20, 4)->nullable();
            $table->string('outlet_name', 255)->nullable();
            $table->string('outlet_code', 255)->nullable();
            $table->decimal('outlet_longitude', 20, 4)->nullable();
            $table->decimal('outlet_latitude', 20, 4)->nullable();
            $table->string('outlet_address', 255)->nullable();
            $table->string('area_name', 255)->nullable();
            $table->string('warehouse_name', 255)->nullable();
            $table->dateTime('datetime_checkin')->nullable();
            $table->dateTime('datetime_checkout')->nullable();
            $table->bigInteger('checkin_duration')->nullable();
            $table->decimal('sales_longitude', 20, 4)->nullable();
            $table->decimal('sales_latitude', 20, 4)->nullable();
            $table->string('created_by_name', 255)->nullable();
            $table->dateTime('created_at')->nullable();
            $table->index('source_id');
        });

        // Lap. Outlet AC
        Schema::create('lap_outlet_ac', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('source_id')->nullable();
            $table->string('outlet_code', 255)->nullable();
            $table->string('outlet_name', 255)->nullable();
            $table->string('contact_person1', 255)->nullable();
            $table->string('contact_person2', 255)->nullable();
            $table->string('phone1', 255)->nullable();
            $table->string('phone2', 255)->nullable();
            $table->text('address')->nullable();
            $table->decimal('location_lat', 20, 4)->nullable();
            $table->decimal('location_long', 20, 4)->nullable();
            $table->string('area_name', 255)->nullable();
            $table->string('province_name', 255)->nullable();
            $table->string('city_name', 255)->nullable();
            $table->string('district_name', 255)->nullable();
            $table->string('village_name', 255)->nullable();
            $table->string('category_name', 255)->nullable();
            $table->string('created_by_name', 255)->nullable();
            $table->dateTime('created_at')->nullable();
            $table->string('warehouse_name', 255)->nullable();
            $table->string('koordinator_name', 255)->nullable();
            $table->index('source_id');
        });

        // Lap. Checkin Outlet
        Schema::create('lap_checkin_outlet', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('source_id')->nullable();
            $table->string('salesperson_name', 255)->nullable();
            $table->string('koordinator_name', 255)->nullable();
            $table->date('date_checkin')->nullable();
            $table->dateTime('datetime_checkin')->nullable();
            $table->dateTime('datetime_checkout')->nullable();
            $table->bigInteger('duration')->nullable();
            $table->string('description', 255)->nullable();
            $table->bigInteger('category')->nullable();
            $table->string('photo', 255)->nullable();
            $table->decimal('sales_longitude', 20, 4)->nullable();
            $table->decimal('sales_latitude', 20, 4)->nullable();
            $table->string('outlet_name', 255)->nullable();
            $table->string('outlet_code', 255)->nullable();
            $table->decimal('outlet_longitude', 20, 4)->nullable();
            $table->decimal('outlet_latitude', 20, 4)->nullable();
            $table->string('outlet_address', 255)->nullable();
            $table->string('area_name', 255)->nullable();
            $table->string('warehouse_name', 255)->nullable();
            $table->index('source_id');
        });

        // Lap. Outlet EC
        Schema::create('lap_outlet_ec', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('source_id')->nullable();
            $table->string('outlet_code', 255)->nullable();
            $table->string('outlet_name', 255)->nullable();
            $table->string('contact_person1', 255)->nullable();
            $table->string('contact_person2', 255)->nullable();
            $table->string('phone1', 255)->nullable();
            $table->string('phone2', 255)->nullable();
            $table->text('address')->nullable();
            $table->decimal('location_lat', 20, 4)->nullable();
            $table->decimal('location_long', 20, 4)->nullable();
            $table->string('area_name', 255)->nullable();
            $table->string('province_name', 255)->nullable();
            $table->string('city_name', 255)->nullable();
            $table->string('district_name', 255)->nullable();
            $table->string('village_name', 255)->nullable();
            $table->string('category_name', 255)->nullable();
            $table->string('created_by_name', 255)->nullable();
            $table->dateTime('created_at')->nullable();
            $table->string('warehouse_name', 255)->nullable();
            $table->string('koordinator_name', 255)->nullable();
            $table->index('source_id');
        });

        // Lap. Efective Call Detail
        Schema::create('lap_effective_call_barang', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('source_id')->nullable();
            $table->string('trans_code', 255)->nullable();
            $table->string('salesperson_name', 255)->nullable();
            $table->string('koordinator_name', 255)->nullable();
            $table->date('date_trans')->nullable();
            $table->dateTime('datetime_trans')->nullable();
            $table->string('product_name', 255)->nullable();
            $table->bigInteger('product_sku')->nullable();
            $table->string('category_name', 255)->nullable();
            $table->string('brand_name', 255)->nullable();
            $table->string('unit_name', 255)->nullable();
            $table->decimal('quantity', 20, 4)->nullable();
            $table->decimal('price', 20, 4)->nullable();
            $table->decimal('discount', 20, 4)->nullable();
            $table->decimal('total', 20, 4)->nullable();
            $table->decimal('total_discount', 20, 4)->nullable();
            $table->string('outlet_name', 255)->nullable();
            $table->string('outlet_code', 255)->nullable();
            $table->decimal('outlet_longitude', 20, 4)->nullable();
            $table->decimal('outlet_latitude', 20, 4)->nullable();
            $table->string('area_name', 255)->nullable();
            $table->string('warehouse_name', 255)->nullable();
            $table->dateTime('datetime_checkin')->nullable();
            $table->dateTime('datetime_checkout')->nullable();
            $table->bigInteger('checkin_duration')->nullable();
            $table->decimal('sales_longitude', 20, 4)->nullable();
            $table->decimal('sales_latitude', 20, 4)->nullable();
            $table->index('source_id');
        });

        // Lap. Penjualan Barang Detail
        Schema::create('lap_penjualan_barang_detail', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('source_id')->nullable();
            $table->string('trans_code', 255)->nullable();
            $table->bigInteger('effective_call_status')->nullable();
            $table->string('salesperson_name', 255)->nullable();
            $table->string('koordinator_name', 255)->nullable();
            $table->date('date_trans')->nullable();
            $table->dateTime('datetime_trans')->nullable();
            $table->string('product_name', 255)->nullable();
            $table->bigInteger('product_sku')->nullable();
            $table->string('category_name', 255)->nullable();
            $table->string('brand_name', 255)->nullable();
            $table->string('unit_name', 255)->nullable();
            $table->decimal('quantity', 20, 4)->nullable();
            $table->decimal('price', 20, 4)->nullable();
            $table->decimal('discount', 20, 4)->nullable();
            $table->decimal('total', 20, 4)->nullable();
            $table->decimal('total_discount', 20, 4)->nullable();
            $table->string('outlet_name', 255)->nullable();
            $table->string('outlet_code', 255)->nullable();
            $table->decimal('outlet_longitude', 20, 4)->nullable();
            $table->decimal('outlet_latitude', 20, 4)->nullable();
            $table->string('area_name', 255)->nullable();
            $table->string('warehouse_name', 255)->nullable();
            $table->dateTime('datetime_checkin')->nullable();
            $table->dateTime('datetime_checkout')->nullable();
            $table->bigInteger('checkin_duration')->nullable();
            $table->decimal('sales_longitude', 20, 4)->nullable();
            $table->decimal('sales_latitude', 20, 4)->nullable();
            $table->index('source_id');
        });

        // Lap. Outlet All
        Schema::create('lap_outlet_all', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('source_id')->nullable();
            $table->string('outlet_code', 255)->nullable();
            $table->string('outlet_name', 255)->nullable();
            $table->string('contact_person1', 255)->nullable();
            $table->string('contact_person2', 255)->nullable();
            $table->string('phone1', 255)->nullable();
            $table->string('phone2', 255)->nullable();
            $table->text('address')->nullable();
            $table->decimal('location_lat', 20, 4)->nullable();
            $table->decimal('location_long', 20, 4)->nullable();
            $table->string('area_name', 255)->nullable();
            $table->string('province_name', 255)->nullable();
            $table->string('city_name', 255)->nullable();
            $table->string('district_name', 255)->nullable();
            $table->string('village_name', 255)->nullable();
            $table->string('category_name', 255)->nullable();
            $table->string('created_by_name', 255)->nullable();
            $table->dateTime('created_at')->nullable();
            $table->string('warehouse_name', 255)->nullable();
            $table->string('koordinator_name', 255)->nullable();
            $table->index('source_id');
        });

        // Lap. Stok Outlet
        Schema::create('lap_stok_outlet', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('source_id')->nullable();
            $table->bigInteger('quantity')->nullable();
            $table->string('unit_name', 255)->nullable();
            $table->dateTime('last_update')->nullable();
            $table->string('salesperson_name', 255)->nullable();
            $table->string('koordinator_name', 255)->nullable();
            $table->date('date_checkin')->nullable();
            $table->dateTime('datetime_checkin')->nullable();
            $table->dateTime('datetime_checkout')->nullable();
            $table->bigInteger('duration')->nullable();
            $table->bigInteger('category')->nullable();
            $table->string('outlet_name', 255)->nullable();
            $table->string('outlet_code', 255)->nullable();
            $table->string('outlet_address', 255)->nullable();
            $table->string('area_name', 255)->nullable();
            $table->string('warehouse_name', 255)->nullable();
            $table->string('product_code', 255)->nullable();
            $table->string('product_name', 255)->nullable();
            $table->string('category_name', 255)->nullable();
            $table->string('brand_name', 255)->nullable();
            $table->index('source_id');
        });

        // Rekap Pencapaian SKU per Motoris - tableAchieved
        Schema::create('rekap_pencapaian_sku_motoris_tableachieved', function (Blueprint $table) {
            $table->id();
            $table->string('no', 255)->nullable();
            $table->string('depo', 255)->nullable();
            $table->string('motoris', 255)->nullable();
            $table->decimal('total_qty', 20, 4)->nullable();
            $table->bigInteger('hari_kerja')->nullable();
            $table->decimal('avg_day', 20, 4)->nullable();
            $table->string('kategori', 255)->nullable();
        });

        // Rekap Pencapaian SKU per Motoris - tableNotAchieved
        Schema::create('rekap_pencapaian_sku_motoris_tablenotachieved', function (Blueprint $table) {
            $table->id();
            $table->string('no', 255)->nullable();
            $table->string('depo', 255)->nullable();
            $table->string('motoris', 255)->nullable();
            $table->decimal('total_qty', 20, 4)->nullable();
            $table->bigInteger('hari_kerja')->nullable();
            $table->bigInteger('avg_day')->nullable();
            $table->string('kategori', 255)->nullable();
        });

        // Rekap Pencapaian SKU per Depo - tableAchieved
        Schema::create('rekap_pencapaian_depo_sku_tableachieved', function (Blueprint $table) {
            $table->id();
            $table->string('no', 255)->nullable();
            $table->string('depo', 255)->nullable();
            $table->string('total_qty', 255)->nullable();
            $table->decimal('hari_kerja', 20, 4)->nullable();
            $table->decimal('avg_day_motoris', 20, 4)->nullable();
            $table->string('kategori', 255)->nullable();
        });

        // Rekap Pencapaian SKU per Depo - tableNotAchieved
        Schema::create('rekap_pencapaian_depo_sku_tablenotachieved', function (Blueprint $table) {
            $table->id();
            $table->string('no', 255)->nullable();
            $table->string('depo', 255)->nullable();
            $table->bigInteger('total_qty')->nullable();
            $table->bigInteger('hari_kerja')->nullable();
            $table->bigInteger('avg_day_motoris')->nullable();
            $table->string('kategori', 255)->nullable();
        });

        // Motoris Coverage
        Schema::create('motoris_coverage', function (Blueprint $table) {
            $table->id();
            $table->string('11_city', 255)->nullable();
            $table->bigInteger('quota_motoris')->nullable();
            $table->bigInteger('alreadytransaction')->nullable();
            $table->bigInteger('active_end_periode')->nullable();
            $table->bigInteger('vacant')->nullable();
            $table->string('coverage', 255)->nullable();
        });

        // Motoris Performance
        Schema::create('motoris_performance', function (Blueprint $table) {
            $table->id();
            $table->string('11_city', 255)->nullable();
            $table->string('motoris_kuota', 255)->nullable();
            $table->string('motoris_already_transaction', 255)->nullable();
            $table->string('motoris_actual_end_periode', 255)->nullable();
            $table->string('hk_standar', 255)->nullable();
            $table->string('act_hk', 255)->nullable();
            $table->string('target', 255)->nullable();
            $table->string('call', 255)->nullable();
            $table->string('ec', 255)->nullable();
            $table->string('ipt', 255)->nullable();
            $table->string('value', 255)->nullable();
        });

        // Hari Kerja Aktif
        Schema::create('waktu_aktif_app', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('no')->nullable();
            $table->string('nama_hari', 255)->nullable();
            $table->string('waktu_mulai', 255)->nullable();
            $table->string('waktu_berakhir', 255)->nullable();
            $table->string('sales', 255)->nullable();
            $table->string('koordinator', 255)->nullable();
            $table->string('update_terakhir', 255)->nullable();
            $table->string('kolom', 255)->nullable();
        });

        // Rumus Insentif Sales
        Schema::create('rumus_insentif_sales', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('source_id')->nullable();
            $table->string('deskripsi', 255)->nullable();
            $table->string('tot_ec_lower_bound', 255)->nullable();
            $table->string('tot_ec_upper_bound', 255)->nullable();
            $table->string('total_insentif', 255)->nullable();
            $table->index('source_id');
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('rumus_insentif_sales');
        Schema::dropIfExists('waktu_aktif_app');
        Schema::dropIfExists('motoris_performance');
        Schema::dropIfExists('motoris_coverage');
        Schema::dropIfExists('rekap_pencapaian_depo_sku_tablenotachieved');
        Schema::dropIfExists('rekap_pencapaian_depo_sku_tableachieved');
        Schema::dropIfExists('rekap_pencapaian_sku_motoris_tablenotachieved');
        Schema::dropIfExists('rekap_pencapaian_sku_motoris_tableachieved');
        Schema::dropIfExists('lap_stok_outlet');
        Schema::dropIfExists('lap_outlet_all');
        Schema::dropIfExists('lap_penjualan_barang_detail');
        Schema::dropIfExists('lap_effective_call_barang');
        Schema::dropIfExists('lap_outlet_ec');
        Schema::dropIfExists('lap_checkin_outlet');
        Schema::dropIfExists('lap_outlet_ac');
        Schema::dropIfExists('lap_penjualan_barang');
        Schema::dropIfExists('lap_effective_call');
        Schema::dropIfExists('lap_outlet_oss');
        Schema::dropIfExists('lap_barang_canvassing');
        Schema::dropIfExists('lap_pengembalian_barang');
        Schema::dropIfExists('lap_barang_habis_terjual');
        Schema::dropIfExists('login_session');
        Schema::dropIfExists('lap_join_visit');
        Schema::dropIfExists('lap_checkin_checkout');
        Schema::dropIfExists('lap_absensi_sales');
        Schema::dropIfExists('absensi_koordinator');
        Schema::dropIfExists('lap_stok_kompetitor');
        Schema::dropIfExists('lap_outlet_noo');
        Schema::dropIfExists('user_sales');
        Schema::dropIfExists('absensi_sales_terlambat');
        Schema::dropIfExists('lap_absensi_koordinator');
        Schema::dropIfExists('monitor_absensi_motoris');
        Schema::dropIfExists('checkin_pagi');
        Schema::dropIfExists('user_koordinator');
        Schema::dropIfExists('sales_tidak_penjualan');
        Schema::dropIfExists('sales_tidak_absen');
        Schema::dropIfExists('master_depo');
        Schema::dropIfExists('sales_point_conversion_per_warehouse');
        Schema::dropIfExists('lap_penjualan_barang_terbaik_bulanan');
        Schema::dropIfExists('holiday_dates');
        Schema::dropIfExists('lap_penjualan_barang_terbaik_mingguan');
        Schema::dropIfExists('lap_penjualan_barang_terbaik_harian');
        Schema::dropIfExists('tipe_outlet');
        Schema::dropIfExists('produk_kompetitor');
        Schema::dropIfExists('lap_outlet_ro');
        Schema::dropIfExists('lap_insentif_sales');
    }
};
