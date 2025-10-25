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
        Schema::create('riwayat_kelas', function (Blueprint $table) {
            $table->id('riwayat_kelas_id');
            $table->unsignedBigInteger('siswa_id');
            $table->foreign('siswa_id')->references('siswa_id')->on('siswa')->onDelete('cascade');
            $table->unsignedBigInteger('kelas_id');
            $table->foreign('kelas_id')->references('kelas_id')->on('kelas')->onDelete('cascade');
            // status
            $table->enum('status', ['naik kelas', 'tidak naik kelas', 'aktif']);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('riwayat_kelas');
    }
};