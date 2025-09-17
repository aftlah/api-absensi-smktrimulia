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
        Schema::create('guru_piket', function (Blueprint $table) {
            $table->id('gurket_id');
            $table->string('nip', 50)->unique();
            $table->string('nama', 100);

            $table->unsignedBigInteger('akun_id');
            $table->foreign('akun_id')->references('akun_id')->on('akun')->onDelete('cascade');

            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('guru_piket');
    }
};
