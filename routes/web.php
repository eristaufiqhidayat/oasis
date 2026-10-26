<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Field;
use App\Http\Controllers\Finance\SettlementController;
use App\Http\Controllers\Master;
use App\Http\Controllers\Performance;
use App\Http\Controllers\Reports;
use App\Http\Controllers\Settings;
use App\Http\Controllers\Warehouse;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:login');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout']);
    Route::get('/ganti-password', [PasswordController::class, 'show']);
    Route::put('/ganti-password', [PasswordController::class, 'update']);
});

Route::middleware(['auth', 'password.changed'])->group(function () {
    Route::redirect('/', '/home');
    Route::get('/home', DashboardController::class)->middleware('can:dashboard.view');

    // Halaman master generik (ResourceController): index, tambah, ubah, hapus. Izin dicek di controller.
    $resource = function (string $uri, string $controller) {
        Route::get($uri, [$controller, 'index']);
        Route::post($uri, [$controller, 'store']);
        Route::put("{$uri}/{id}", [$controller, 'update'])->whereNumber('id');
        Route::delete("{$uri}/{id}", [$controller, 'destroy'])->whereNumber('id');
    };

    // ---- Lapangan ----
    Route::middleware('can:trip.view')->group(function () {
        Route::get('/muatan', [Field\TripController::class, 'index']);
        Route::get('/muatan/baru', [Field\TripController::class, 'create']);
        Route::post('/muatan', [Field\TripController::class, 'store']);
        Route::get('/muatan/{id}', [Field\TripController::class, 'show'])->whereNumber('id');
        Route::post('/muatan/{id}/batal', [Field\TripController::class, 'cancel'])->whereNumber('id');
        Route::post('/muatan/{id}/bongkar', [Field\TripController::class, 'unload'])->whereNumber('id');
    });

    Route::middleware('can:sale.view')->group(function () {
        Route::get('/penjualan', [Field\SaleController::class, 'index']);
        Route::get('/penjualan/{id}', [Field\SaleController::class, 'show'])->whereNumber('id');
        Route::post('/penjualan/{id}/batal', [Field\SaleController::class, 'void'])->whereNumber('id');
        Route::get('/retur', [Field\CustomerReturnController::class, 'index']);
    });

    Route::middleware('can:field.view')->group(function () {
        Route::get('/kunjungan', [Field\VisitController::class, 'index']);
        Route::get('/absensi', [Field\AttendanceController::class, 'index']);
        Route::get('/pelacakan', [Field\TrackingController::class, 'index']);
    });

    Route::middleware('can:outlet.verify')->group(function () {
        Route::get('/outlet-baru', [Master\OutletVerificationController::class, 'index']);
        Route::post('/outlet-baru/{id}', [Master\OutletVerificationController::class, 'verify'])->whereNumber('id');
    });

    // ---- Gudang ----
    Route::middleware('can:stock.view')->group(function () {
        Route::get('/stok', [Warehouse\StockController::class, 'index']);
        Route::get('/dokumen-stok', [Warehouse\StockDocumentController::class, 'index']);
        Route::post('/dokumen-stok', [Warehouse\StockDocumentController::class, 'store']);
        Route::get('/mutasi-stok', [Warehouse\StockMovementController::class, 'index']);
    });

    // ---- Keuangan ----
    Route::middleware('can:settlement.view')->group(function () {
        Route::get('/setoran', [SettlementController::class, 'index']);
        Route::get('/setoran/{id}', [SettlementController::class, 'show'])->whereNumber('id');
        Route::post('/setoran/{id}/terima', [SettlementController::class, 'receive'])->whereNumber('id');
        Route::post('/setoran/{id}/setujui', [SettlementController::class, 'approve'])->whereNumber('id');
    });

    // ---- Kinerja ----
    Route::middleware('can:target.view')->group(function () {
        Route::get('/target', [Performance\TargetController::class, 'index']);
        Route::post('/target', [Performance\TargetController::class, 'store']);
        Route::post('/target/bagi-rata', [Performance\TargetController::class, 'distribute']);
        Route::get('/pencapaian', [Performance\AchievementController::class, 'index']);
    });

    Route::middleware('can:incentive.view')->group(function () {
        Route::get('/insentif', [Performance\IncentiveController::class, 'index']);
        Route::post('/insentif/setujui', [Performance\IncentiveController::class, 'approve']);
        Route::post('/insentif/bayar', [Performance\IncentiveController::class, 'pay']);
        Route::get('/pengaturan/skema-insentif', [Settings\IncentiveSchemeController::class, 'index']);
        Route::post('/pengaturan/skema-insentif', [Settings\IncentiveSchemeController::class, 'store'])->middleware('can:incentive.manage');
        Route::put('/pengaturan/skema-insentif/{scheme}', [Settings\IncentiveSchemeController::class, 'update'])->middleware('can:incentive.manage');
    });

    // ---- Laporan ----
    Route::middleware('can:report.view')->group(function () {
        Route::get('/laporan/penjualan', [Reports\SalesReportController::class, 'index']);
        Route::get('/laporan/penjualan/unduh', [Reports\SalesReportController::class, 'export']);
        Route::get('/laporan/galon', [Reports\ContainerReportController::class, 'index']);
        Route::get('/laporan/kunjungan', [Reports\VisitReportController::class, 'index']);
    });

    // ---- Master data ----
    $resource('/master/wilayah', Master\RegionController::class);
    $resource('/master/depo', Master\DepotController::class);
    Route::get('/master/area/peta', [Master\TerritoryController::class, 'map']);
    Route::put('/master/area/{id}/batas', [Master\TerritoryController::class, 'saveBoundary'])->whereNumber('id');
    $resource('/master/area', Master\TerritoryController::class);
    $resource('/master/karyawan', Master\EmployeeController::class);
    $resource('/master/outlet', Master\OutletController::class);
    $resource('/master/rute', Master\SalesRouteController::class);
    Route::get('/master/rute/{id}', [Master\SalesRouteController::class, 'show'])->whereNumber('id');
    Route::put('/master/rute/{id}/outlet', [Master\SalesRouteController::class, 'syncOutlets'])->whereNumber('id');
    $resource('/master/saluran', Master\ChannelController::class);
    $resource('/master/kategori', Master\CategoryController::class);
    $resource('/master/produk', Master\ProductController::class);
    Route::get('/master/harga', [Master\PriceController::class, 'index']);
    Route::post('/master/harga', [Master\PriceController::class, 'store']);
    Route::delete('/master/harga/{depot}/{product}', [Master\PriceController::class, 'resetDepot'])->whereNumber(['depot', 'product']);

    // ---- Pengaturan ----
    $resource('/pengaturan/pengguna', Settings\UserController::class);
    Route::middleware('can:role.manage')->group(function () {
        Route::get('/pengaturan/peran', [Settings\RoleController::class, 'index']);
        Route::post('/pengaturan/peran', [Settings\RoleController::class, 'store']);
        Route::put('/pengaturan/peran/{role}', [Settings\RoleController::class, 'update']);
        Route::delete('/pengaturan/peran/{role}', [Settings\RoleController::class, 'destroy']);
    });
    Route::get('/pengaturan/log', [Settings\ActivityLogController::class, 'index'])->middleware('can:activity.view');
});
