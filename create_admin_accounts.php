<?php

/**
 * Script untuk membuat 2 akun admin:
 * 1. Kepala Sekolah
 * 2. Kesiswaan
 * 
 * Cara menjalankan:
 * php create_admin_accounts.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Akun;
use App\Models\Admin;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

try {
    DB::beginTransaction();

    echo "=== Membuat Akun Admin ===\n\n";

    // Data untuk Kepala Sekolah
    $kepalaSekolahData = [
        'nama' => 'Kepala Sekolah',
        'username' => 'kepsek',
        'password' => 'kepsek123', // Ganti dengan password yang diinginkan
    ];

    // Data untuk Kesiswaan
    $kesiswaanData = [
        'nama' => 'Admin Kesiswaan',
        'username' => 'kesiswaan',
        'password' => 'kesiswaan123', // Ganti dengan password yang diinginkan
    ];

    $admins = [$kepalaSekolahData, $kesiswaanData];

    foreach ($admins as $adminData) {
        // Cek apakah username sudah ada
        $existingAkun = Akun::where('username', $adminData['username'])->first();
        
        if ($existingAkun) {
            echo "⚠️  Username '{$adminData['username']}' sudah ada, dilewati.\n";
            continue;
        }

        // Buat akun
        $akun = Akun::create([
            'username' => $adminData['username'],
            'password' => Hash::make($adminData['password']),
            'role' => 'admin',
        ]);

        // Buat admin (dengan username)
        $admin = Admin::create([
            'username' => $adminData['username'],
            'nama' => $adminData['nama'],
            'akun_id' => $akun->akun_id,
        ]);

        echo "✅ Berhasil membuat akun admin:\n";
        echo "   Nama     : {$adminData['nama']}\n";
        echo "   Username : {$adminData['username']}\n";
        echo "   Password : {$adminData['password']}\n";
        echo "   Role     : admin\n\n";
    }

    DB::commit();
    
    echo "=== Selesai ===\n";
    echo "\n⚠️  PENTING: Segera ganti password default setelah login pertama kali!\n";

} catch (\Exception $e) {
    DB::rollBack();
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    exit(1);
}
