<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\Akun;
use App\Models\Admin;

class AdminAccountSeeder extends Seeder
{
    public function run(): void
    {
        $adminAkun = Akun::firstOrCreate(
            ['username' => 'admin'],
            ['password' => Hash::make('admin123'), 'role' => 'admin']
        );

        Admin::firstOrCreate(
            ['username' => 'admin'],
            ['nama' => 'Kepala Sekolah (Administrator)', 'akun_id' => $adminAkun->akun_id]
        );
    }
}
