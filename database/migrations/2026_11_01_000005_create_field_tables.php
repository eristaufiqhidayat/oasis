<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Kegiatan lapangan: muatan harian (trip), absensi, kunjungan, jejak GPS, token HP.
return new class extends Migration
{
    public function up(): void
    {
        // Satu trip = satu hari kerja motoris dengan satu muatan kendaraan.
        // Status: loaded → on_route → returned → unloaded → closed (atau cancelled sebelum berangkat). Lihat App\Enums\TripStatus.
        Schema::create('trips', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->foreignId('depot_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('vehicle_location_id')->constrained('stock_locations')->restrictOnDelete();
            $table->date('date');
            $table->string('status', 12)->index();
            $table->foreignId('loaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('loaded_at')->nullable();
            $table->timestamp('departed_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->foreignId('unloaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('unloaded_at')->nullable();
            // Galon kosong yang benar-benar diterima gudang saat bongkar.
            $table->unsignedInteger('empty_containers_received')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();
            $table->index(['employee_id', 'date']);
            $table->index(['depot_id', 'date']);
        });

        Schema::create('trip_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('loaded_qty');
            // Hasil hitung gudang saat bongkar (null = belum dibongkar).
            $table->unsignedInteger('returned_good_qty')->nullable();
            $table->unsignedInteger('returned_damaged_qty')->nullable();
            $table->unique(['trip_id', 'product_id']);
        });

        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->timestamp('check_in_at');
            $table->decimal('check_in_latitude', 10, 7)->nullable();
            $table->decimal('check_in_longitude', 10, 7)->nullable();
            $table->string('check_in_photo')->nullable();
            $table->timestamp('check_out_at')->nullable();
            $table->decimal('check_out_latitude', 10, 7)->nullable();
            $table->decimal('check_out_longitude', 10, 7)->nullable();
            $table->string('note')->nullable();
            $table->timestamps();
            $table->unique(['employee_id', 'date']);
        });

        Schema::create('visits', function (Blueprint $table) {
            $table->id();
            $table->uuid('client_uuid')->unique();
            $table->foreignId('trip_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('outlet_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            // Jarak posisi HP ke titik outlet saat check-in (meter).
            $table->unsignedInteger('distance_m')->nullable();
            // null (sedang berlangsung) | sale | no_sale | closed
            $table->string('outcome', 10)->nullable();
            $table->string('no_sale_reason', 40)->nullable();
            $table->string('photo_path')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();
            $table->index(['employee_id', 'started_at']);
            $table->index(['outlet_id', 'started_at']);
        });

        Schema::create('location_pings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('trip_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('recorded_at');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->unsignedSmallInteger('accuracy_m')->nullable();
            $table->unsignedTinyInteger('battery')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->unique(['employee_id', 'recorded_at']);
        });

        // Token Bearer per HP. Hanya hash SHA-256 yang disimpan.
        Schema::create('api_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('device_name', 100);
            $table->char('token_hash', 64)->unique();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_tokens');
        Schema::dropIfExists('location_pings');
        Schema::dropIfExists('visits');
        Schema::dropIfExists('attendances');
        Schema::dropIfExists('trip_lines');
        Schema::dropIfExists('trips');
    }
};
