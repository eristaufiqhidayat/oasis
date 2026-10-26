<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Struktur organisasi: wilayah → depo → area penjualan (polygon) dan karyawan.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('depots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('region_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('phone', 30)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->timestamps();
        });

        // Area penjualan di bawah depo. boundary = GeoJSON Polygon/MultiPolygon (koordinat [lng, lat]).
        Schema::create('territories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('depot_id')->constrained()->cascadeOnDelete();
            $table->string('code', 30)->nullable();
            $table->string('name');
            $table->json('boundary')->nullable();
            $table->string('color', 9)->default('#18a6b1');
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->timestamps();
            $table->unique(['depot_id', 'code']);
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            // salesman | coordinator | depot_head | warehouse | cashier | admin (lihat App\Enums\Position)
            $table->string('position', 20)->index();
            $table->foreignId('depot_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supervisor_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('phone', 30)->nullable();
            $table->date('joined_on')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('employee_id')->references('id')->on('employees')->nullOnDelete();
        });

        // Cakupan data akun non-pusat: depo yang boleh dilihat/diolah.
        Schema::create('depot_user', function (Blueprint $table) {
            $table->foreignId('depot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['depot_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('depot_user');
        Schema::table('users', fn (Blueprint $table) => $table->dropForeign(['employee_id']));
        Schema::dropIfExists('employees');
        Schema::dropIfExists('territories');
        Schema::dropIfExists('depots');
        Schema::dropIfExists('regions');
    }
};
