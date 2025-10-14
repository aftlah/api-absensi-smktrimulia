<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('aktivitas_terbaru', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('akun_id')->nullable();
            $table->string('tabel');
            $table->string('aksi');
            $table->string('deskripsi')->nullable();
            $table->string('user')->nullable();
            $table->string('role')->nullable();
            $table->timestamps();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('aktivitas_terbaru');
    }
};
