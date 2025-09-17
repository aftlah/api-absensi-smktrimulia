<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\Akun;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\GuruPiket;
use App\Models\WaliKelas;
use App\Models\Admin;
use App\Models\Pengaturan;
use App\Models\Absensi;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 🔹 Admin
        $adminAkun = Akun::create([
            'username' => 'admin',
            'password' => Hash::make('admin123'),
            'role' => 'admin'
        ]);

        Admin::create([
            'nip' => 'ADM001',
            'nama' => 'Administrator',
            'akun_id' => $adminAkun->akun_id
        ]);

        // 🔹 Guru Piket
        $guruAkun = Akun::create([
            'username' => 'gurupiket',
            'password' => Hash::make('guru123'),
            'role' => 'gurket'
        ]);

        $guru = GuruPiket::create([
            'nip' => 'GP001',
            'nama' => 'Guru Piket 1',
            'akun_id' => $guruAkun->akun_id
        ]);

        // 🔹 Kelas
        $kelasX = Kelas::create([
            'tingkat' => 'X',
            'jurusan' => 'TKJ',
            'paralel' => 1
        ]);

        $kelasXI = Kelas::create([
            'tingkat' => 'XI',
            'jurusan' => 'OTKP',
            'paralel' => 2
        ]);

        // 🔹 Wali Kelas
        $walasAkun = Akun::create([
            'username' => 'walikelas',
            'password' => Hash::make('walas123'),
            'role' => 'walas'
        ]);

        WaliKelas::create([
            'nip' => 'WL001',
            'nama' => 'Wali Kelas XI OTKP',
            'akun_id' => $walasAkun->akun_id,
            'kelas_id' => $kelasXI->kelas_id
        ]);

        // 🔹 Siswa (contoh 5 siswa tiap kelas)
        for ($i = 1; $i <= 5; $i++) {
            $akun = Akun::create([
                'username' => 'nis00' . $i,
                'password' => Hash::make('siswa123'),
                'role' => 'siswa'
            ]);

            $kelas = $i % 2 == 0 ? $kelasX : $kelasXI;

            $siswa = Siswa::create([
                'nis' => '2025' . str_pad($i, 3, '0', STR_PAD_LEFT),
                'nama' => 'Siswa ' . $i,
                'akun_id' => $akun->akun_id,
                'kelas_id' => $kelas->kelas_id
            ]);

            // 🔹 Absensi dummy
            Absensi::create([
                'siswa_id' => $siswa->siswa_id,
                'tanggal' => now()->toDateString(),
                'jam_datang' => now()->subHours(rand(1, 2))->format('H:i:s'),
                'jam_pulang' => now()->format('H:i:s'),
                'status' => 'hadir',
                'latitude' => -6.234567,
                'longitude' => 106.987654,
                'bukti' => null
            ]);
        }

        // 🔹 Pengaturan sekolah
        Pengaturan::create([
            'latitude' => -6.234567,
            'longitude' => 106.987654,
            'radius_meter' => 50,
            'jam_masuk' => '07:00:00',
            'jam_pulang' => '15:00:00',
            'toleransi_telat' => 10
        ]);
    }
}
