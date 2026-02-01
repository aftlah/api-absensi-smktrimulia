<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    
    public function up(): void
    {
        Schema::create('rencana_absensi', function (Blueprint $table) {
            $table->id('rensi_id');
            $table->date('tanggal');
            $table->enum('status_hari', ['normal', 'libur'])->default('normal');
            $table->string('keterangan')->nullable();
            // fk kelas
            $table->unsignedBigInteger('kelas_id');
            $table->foreign('kelas_id')->references('kelas_id')->on('kelas')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rencana_absensi');
    }
};