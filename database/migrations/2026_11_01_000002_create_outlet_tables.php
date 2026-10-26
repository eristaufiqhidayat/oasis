<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Outlet (pelanggan) dan rute kunjungan motoris.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outlet_channels', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('outlets', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name');
            $table->string('owner_name')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('address')->nullable();
            $table->string('district')->nullable();
            $table->string('city')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->foreignId('depot_id')->constrained()->restrictOnDelete();
            $table->foreignId('territory_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('channel_id')->nullable()->constrained('outlet_channels')->nullOnDelete();
            // pending (didaftarkan dari HP, menunggu verifikasi) | active | rejected | inactive
            $table->string('status', 12)->default('active')->index();
            $table->string('photo_path')->nullable();
            $table->uuid('client_uuid')->nullable()->unique();
            $table->foreignId('registered_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->string('verification_note')->nullable();
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->timestamps();
            $table->index(['depot_id', 'status']);
        });

        // Rute = daftar outlet berurutan yang dikunjungi seorang motoris pada hari tertentu.
        Schema::create('sales_routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('depot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            // 1 = Senin … 7 = Minggu; null = tidak terikat hari.
            $table->unsignedTinyInteger('weekday')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['employee_id', 'weekday']);
        });

        Schema::create('sales_route_outlet', function (Blueprint $table) {
            $table->foreignId('sales_route_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence')->default(0);
            $table->primary(['sales_route_id', 'outlet_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_route_outlet');
        Schema::dropIfExists('sales_routes');
        Schema::dropIfExists('outlets');
        Schema::dropIfExists('outlet_channels');
    }
};
