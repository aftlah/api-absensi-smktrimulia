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
        // Update admin table
        Schema::table('admin', function (Blueprint $table) {
            $table->renameColumn('nip', 'username');
        });

        // Update wali_kelas table
        Schema::table('wali_kelas', function (Blueprint $table) {
            $table->renameColumn('nip', 'username');
        });

        // Update guru_piket table
        Schema::table('guru_piket', function (Blueprint $table) {
            $table->renameColumn('nip', 'username');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Rollback admin table
        Schema::table('admin', function (Blueprint $table) {
            $table->renameColumn('username', 'nip');
        });

        // Rollback wali_kelas table
        Schema::table('wali_kelas', function (Blueprint $table) {
            $table->renameColumn('username', 'nip');
        });

        // Rollback guru_piket table
        Schema::table('guru_piket', function (Blueprint $table) {
            $table->renameColumn('username', 'nip');
        });
    }
};