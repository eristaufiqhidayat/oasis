<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Persediaan berbasis lokasi: gudang depo, kendaraan tiap motoris, dan lokasi barang rusak.
// Setiap perpindahan barang = satu baris stock_movements (dari → ke); stock_balances = saldo terkini per lokasi.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('depot_id')->constrained()->cascadeOnDelete();
            // warehouse | vehicle | damaged
            $table->string('type', 12);
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->timestamps();
            $table->unique(['depot_id', 'type', 'employee_id']);
        });

        Schema::create('stock_balances', function (Blueprint $table) {
            $table->foreignId('location_id')->constrained('stock_locations')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->integer('quantity')->default(0);
            $table->timestamp('updated_at')->nullable();
            $table->primary(['location_id', 'product_id']);
        });

        // Dokumen gudang yang dibuat manual: penerimaan barang dari pabrik/distributor dan penyesuaian hasil opname.
        Schema::create('stock_documents', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            // receipt | adjustment
            $table->string('type', 12);
            $table->foreignId('depot_id')->constrained()->restrictOnDelete();
            $table->date('date');
            $table->string('reference')->nullable();
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('stock_document_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            // Penerimaan selalu positif; penyesuaian boleh negatif (barang hilang/rusak saat opname).
            $table->integer('quantity');
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->timestamp('occurred_at')->index();
            // receipt | adjustment | load | unload | sale | sale_void | customer_return | write_off
            $table->string('type', 20)->index();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->foreignId('from_location_id')->nullable()->constrained('stock_locations')->restrictOnDelete();
            $table->foreignId('to_location_id')->nullable()->constrained('stock_locations')->restrictOnDelete();
            $table->nullableMorphs('reference');
            $table->string('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->index(['from_location_id', 'product_id']);
            $table->index(['to_location_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock_document_lines');
        Schema::dropIfExists('stock_documents');
        Schema::dropIfExists('stock_balances');
        Schema::dropIfExists('stock_locations');
    }
};
