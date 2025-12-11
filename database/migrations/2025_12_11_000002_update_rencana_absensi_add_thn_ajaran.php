<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('rencana_absensi', function (Blueprint $table) {
            if (!Schema::hasColumn('rencana_absensi', 'thn_ajaran')) {
                $table->string('thn_ajaran', 9)->nullable()->after('tanggal');
            }
        });
    }

    public function down(): void
    {
        Schema::table('rencana_absensi', function (Blueprint $table) {
            if (Schema::hasColumn('rencana_absensi', 'thn_ajaran')) {
                $table->dropColumn('thn_ajaran');
            }
        });
    }
};

