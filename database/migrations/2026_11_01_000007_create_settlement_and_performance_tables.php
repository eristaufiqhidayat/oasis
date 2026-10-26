<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Setoran & rekonsiliasi per trip, target bulanan, skema dan pembayaran insentif, log aktivitas.
return new class extends Migration
{
    public function up(): void
    {
        // Satu baris per trip: kasir mencatat uang diterima, kepala depo menyetujui selisih barang & uang.
        Schema::create('settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('cash_expected');
            $table->unsignedBigInteger('cash_received')->nullable();
            // Diisi saat disetujui (snapshot): selisih uang (+ lebih, − kurang), nilai barang kurang, galon kurang.
            $table->bigInteger('cash_variance')->nullable();
            $table->unsignedBigInteger('stock_shortage_value')->nullable();
            $table->integer('container_shortage')->nullable();
            // none | deduct_incentive | charge_employee | write_off
            $table->string('resolution', 20)->nullable();
            $table->string('note')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        // Target bulanan. Tepat satu dari depot_id / employee_id terisi; product_id hanya untuk metrik quantity.
        // metric: revenue (rupiah) | effective_calls (jumlah kunjungan berhasil jual) | quantity (qty per SKU).
        Schema::create('targets', function (Blueprint $table) {
            $table->id();
            $table->date('period')->index();
            $table->string('metric', 20);
            $table->foreignId('depot_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('value');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['period', 'metric', 'depot_id']);
            $table->index(['period', 'metric', 'employee_id']);
        });

        // Skema insentif. rules (JSON):
        //   per_effective_call: rupiah per EC
        //   revenue_percent: komisi % dari penjualan
        //   tiers: [{min_achievement: %, amount: rupiah}] — bonus tingkat tertinggi yang dicapai (pencapaian target revenue)
        Schema::create('incentive_schemes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // salesman | coordinator
            $table->string('applies_to', 12);
            $table->json('rules');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('incentive_payouts', function (Blueprint $table) {
            $table->id();
            $table->date('period');
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('incentive_scheme_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount');
            $table->bigInteger('deduction')->default(0);
            $table->json('breakdown');
            // approved | paid (perkiraan tidak disimpan, dihitung langsung)
            $table->string('status', 10);
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->unique(['period', 'employee_id', 'incentive_scheme_id']);
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 40);
            $table->nullableMorphs('subject');
            $table->string('description');
            $table->json('properties')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('incentive_payouts');
        Schema::dropIfExists('incentive_schemes');
        Schema::dropIfExists('targets');
        Schema::dropIfExists('settlements');
    }
};
