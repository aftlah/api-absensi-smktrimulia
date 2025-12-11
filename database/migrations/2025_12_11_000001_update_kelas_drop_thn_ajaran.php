<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('kelas', function (Blueprint $table) {
            if (Schema::hasColumn('kelas', 'thn_ajaran')) {
                $table->dropColumn('thn_ajaran');
            }
        });
    }

    public function down(): void
    {
        Schema::table('kelas', function (Blueprint $table) {
            if (!Schema::hasColumn('kelas', 'thn_ajaran')) {
                $table->string('thn_ajaran', 9)->nullable();
            }
        });
    }
};

