<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('siswa', function (Blueprint $table) {
            $table->id('siswa_id');
            $table->string('nis', 10)->unique();
            $table->string('nama', 255);
            $table->enum('jenkel', ['L', 'P']);

            $table->unsignedBigInteger('akun_id');
            $table->foreign('akun_id')
                ->references('akun_id')
                ->on('akun')
                ->onDelete('cascade');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('siswa');
    }
};
