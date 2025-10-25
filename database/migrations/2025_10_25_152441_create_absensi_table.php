<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('absensi', function (Blueprint $table) {
            $table->id('absensi_id');

            $table->unsignedBigInteger('siswa_id');
            $table->foreign('siswa_id')->references('siswa_id')->on('siswa')->onDelete('cascade');
            // rencana absensi
            $table->unsignedBigInteger('rensi_id');
            $table->foreign('rensi_id')->references('rensi_id')->on('rencana_absensi')->onDelete('cascade');
            $table->time('jam_datang')->nullable();
            $table->time('jam_pulang')->nullable();
            $table->decimal('latitude_datang', 10, 8)->nullable();
            $table->decimal('longitude_datang', 11, 8)->nullable();
            $table->decimal('latitude_pulang', 10, 8)->nullable();
            $table->decimal('longitude_pulang', 11, 8)->nullable();
            $table->enum('status', ['hadir', 'terlambat', 'izin', 'sakit', 'alfa']);
            $table->boolean('is_verif')->default(false);
            $table->string('bukti')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('absensi');
    }
};