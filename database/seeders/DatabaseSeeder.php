<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\{
    Akun,
    Admin,
    GuruPiket,
    WaliKelas,
    Jurusan,
    Kelas,
    Siswa,
    RiwayatKelas,
    Absensi,
    RencanaAbsensi,
    Pengaturan
};

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        /** 🔹 1. Jurusan */
        $jurusanTKJ = Jurusan::firstOrCreate(['nama_jurusan' => 'TKJ']);
        $jurusanMP = Jurusan::firstOrCreate(['nama_jurusan' => 'MP']);
        $jurusanBR = Jurusan::firstOrCreate(['nama_jurusan' => 'BR']);



        /** 🔹 2. Admin */
        $adminAkun = Akun::firstOrCreate(
            ['username' => 'admin'],
            ['password' => Hash::make('admin123'), 'role' => 'admin']
        );

        Admin::firstOrCreate(
            ['nip' => 'ADM001'],
            ['nama' => 'Kepala Sekolah (Administrator)', 'akun_id' => $adminAkun->akun_id]
        );

        /** 🔹 3. Guru Piket */
        $guruAkun = Akun::firstOrCreate(
            ['username' => 'gurupiket'],
            ['password' => Hash::make('guru123'), 'role' => 'gurket']
        );

        $guru = GuruPiket::firstOrCreate(
            ['nip' => 'GP001'],
            ['nama' => 'Guru Piket 1', 'akun_id' => $guruAkun->akun_id]
        );

        /** 🔹 4. Wali Kelas */
        $walasAkun = Akun::firstOrCreate(
            ['username' => 'walikelas'],
            ['password' => Hash::make('walas123'), 'role' => 'walas']
        );

        $waliKelas = WaliKelas::firstOrCreate(
            ['nip' => 'WL001'],
            ['nama' => 'Wali Kelas XI OTKP', 'akun_id' => $walasAkun->akun_id]
        );

        /** 🔹 5. Kelas — dibuat setelah wali kelas */
        $kelasX = Kelas::firstOrCreate(
            ['tingkat' => 'X', 'jurusan_id' => $jurusanTKJ->jurusan_id, 'paralel' => 1],
            ['walas_id' => $waliKelas->walas_id]
        );

        $kelasXI = Kelas::firstOrCreate(
            ['tingkat' => 'XI', 'jurusan_id' => $jurusanMP->jurusan_id, 'paralel' => 2],
            ['walas_id' => $waliKelas->walas_id]
        );

        $kelasXII = Kelas::firstOrCreate(
            ['tingkat' => 'XII', 'jurusan_id' => $jurusanBR->jurusan_id, 'paralel' => 3],
            ['walas_id' => $waliKelas->walas_id]
        );

        /** 🔹 6. Siswa */
        for ($i = 1; $i <= 5; $i++) {
            $akun = Akun::firstOrCreate(
                ['username' => 'nis00' . $i],
                ['password' => Hash::make('siswa123'), 'role' => 'siswa']
            );

            $kelas = $i % 2 == 0 ? $kelasX : $kelasXI;
            if ($i == 5) {
                $kelas = $kelasXII;
            }

            $siswa = Siswa::firstOrCreate(
                ['nis' => '2025' . str_pad($i, 3, '0', STR_PAD_LEFT)],
                [
                    'nama' => 'Siswa ' . $i,
                    'akun_id' => $akun->akun_id,
                    'jenkel' => $i % 2 == 0 ? 'L' : 'P'
                ]
            );

            /** 🔹 7. Riwayat Kelas */
            RiwayatKelas::firstOrCreate(
                ['siswa_id' => $siswa->siswa_id, 'kelas_id' => $kelas->kelas_id],
                ['status' => 'aktif']
            );

            /** 🔹 8. Absensi dummy */
            // Absensi::firstOrCreate(
            //     [
            //         'siswa_id' => $siswa->siswa_id,
            //         'tanggal' => now()->toDateString(),
            //     ],
            //     [
            //         'jam_datang' => now()->subHours(rand(1, 2))->format('H:i:s'),
            //         'jam_pulang' => now()->format('H:i:s'),
            //         'status' => 'hadir',
            //         'latitude' => -6.234567,
            //         'longitude' => 106.987654,
            //         'bukti' => null
            //     ]
            // );
        }

        /** 🔹 9. Rencana Absensi */
        foreach ([$kelasX, $kelasXI] as $kelas) {
            RencanaAbsensi::firstOrCreate(
                ['tanggal' => now()->toDateString(), 'kelas_id' => $kelas->kelas_id],
                ['status_hari' => 'normal']
            );
        }

        /** 🔹 10. Pengaturan Sekolah */
        Pengaturan::firstOrCreate(
            ['latitude' => -6.234567, 'longitude' => 106.987654],
            [
                'radius_meter' => 50,
                'jam_masuk' => '07:00:00',
                'jam_pulang' => '15:00:00',
                'toleransi_telat' => 10
            ]
        );
    }
}
