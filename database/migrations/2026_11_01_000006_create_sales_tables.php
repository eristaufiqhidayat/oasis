<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Penjualan, retur dari outlet, dan pergerakan wadah galon di outlet.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->uuid('client_uuid')->unique();
            $table->string('number', 30)->unique();
            $table->foreignId('depot_id')->constrained()->restrictOnDelete();
            $table->foreignId('trip_id')->constrained()->restrictOnDelete();
            $table->foreignId('visit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('outlet_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->timestamp('sold_at')->index();
            $table->unsignedBigInteger('subtotal');
            $table->unsignedBigInteger('discount')->default(0);
            $table->unsignedBigInteger('total');
            // cash = uang tunai wajib disetor; transfer = dibayar outlet langsung ke rekening.
            $table->string('payment_method', 10)->default('cash');
            // completed | void
            $table->string('status', 10)->default('completed')->index();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason')->nullable();
            $table->timestamps();
            $table->index(['employee_id', 'sold_at']);
        });

        Schema::create('sale_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_price');
            $table->unsignedBigInteger('discount')->default(0);
            $table->unsignedBigInteger('line_total');
        });

        // Barang yang dikembalikan outlet ke motoris (rusak, kedaluwarsa, bocor). Diverifikasi gudang saat bongkar.
        Schema::create('customer_returns', function (Blueprint $table) {
            $table->id();
            $table->uuid('client_uuid')->unique();
            $table->string('number', 30)->unique();
            $table->foreignId('depot_id')->constrained()->restrictOnDelete();
            $table->foreignId('trip_id')->constrained()->restrictOnDelete();
            $table->foreignId('visit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('outlet_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->timestamp('returned_at');
            $table->string('note')->nullable();
            $table->timestamps();
        });

        Schema::create('customer_return_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            // damaged | expired | leaking | other
            $table->string('reason', 12);
        });

        // Saldo galon di outlet: + galon isi terkirim, − galon kosong ditarik. Saldo = SUM(quantity).
        Schema::create('container_movements', function (Blueprint $table) {
            $table->id();
            $table->uuid('client_uuid')->nullable()->unique();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('trip_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('visit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained()->cascadeOnDelete();
            // delivered | collected | adjustment
            $table->string('type', 12);
            $table->integer('quantity');
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->index(['outlet_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('container_movements');
        Schema::dropIfExists('customer_return_lines');
        Schema::dropIfExists('customer_returns');
        Schema::dropIfExists('sale_lines');
        Schema::dropIfExists('sales');
    }
};
