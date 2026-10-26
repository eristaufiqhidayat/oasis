<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Produk dan harga. Semua qty dalam satuan terkecil (pcs/galon), semua uang dalam rupiah bulat.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 40)->unique();
            $table->string('name');
            $table->foreignId('category_id')->nullable()->constrained('product_categories')->nullOnDelete();
            $table->string('unit', 20)->default('pcs');
            // Isi per karton/dus; dipakai untuk tampilan "x dus + y pcs".
            $table->unsignedInteger('units_per_case')->default(1);
            // true = produk berwadah galon yang wadahnya wajib kembali (saldo galon outlet dihitung).
            $table->boolean('tracks_container')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->timestamps();
        });

        // Riwayat harga jual. depot_id null = harga nasional; harga depo menimpa harga nasional.
        // Harga berlaku = baris dengan effective_from terbaru ≤ tanggal transaksi.
        Schema::create('product_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('depot_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('price');
            $table->date('effective_from');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['product_id', 'depot_id', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_prices');
        Schema::dropIfExists('products');
        Schema::dropIfExists('product_categories');
    }
};
