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
        Schema::table('absensi', function (Blueprint $table) {
            // Tambahkan kolom jenis_absen hanya jika belum ada
            if (!Schema::hasColumn('absensi', 'jenis_absen')) {
                $table->enum('jenis_absen', ['hadir', 'terlambat', 'izin', 'sakit', 'alfa'])
                    ->nullable()
                    ->after('siswa_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('absensi', function (Blueprint $table) {
            if (Schema::hasColumn('absensi', 'jenis_absen')) {
                $table->dropColumn('jenis_absen');
            }
        });
    }
};
