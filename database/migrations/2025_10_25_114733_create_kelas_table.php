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
        Schema::create('kelas', function (Blueprint $table) {
            $table->id('kelas_id');
            $table->enum('tingkat', ['X', 'XI', 'XII']);
            $table->char('paralel', 1)->nullable();
            $table->string('thn_ajaran', 9);
            $table->unsignedBigInteger('jurusan_id');
            $table->foreign('jurusan_id')->references('jurusan_id')->on('jurusan')->onDelete('cascade');
            $table->unsignedBigInteger('walas_id');
            $table->foreign('walas_id')->references('walas_id')->on('wali_kelas')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kelas');
    }
};