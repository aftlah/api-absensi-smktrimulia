<?php

use App\Http\Controllers\AktivitasController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AbsensiController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GurketController;
use App\Http\Controllers\UtillityController;
use Illuminate\Support\Facades\Route;
use SebastianBergmann\CodeCoverage\Report\Html\Dashboard;

// Auth
// testting hello world
Route::get('/', function () {
    return response()->json(['message' => 'API Absensi SMK Trimulia is Running']);
});

// Auth dengan rate limiting
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1'); // 5 attempts per minute
Route::post('/logout', [AuthController::class, 'logout'])->middleware(['auth:api', 'throttle:10,1']);
Route::post('/refresh', [AuthController::class, 'refresh'])->middleware(['auth:api', 'throttle:10,1']);
Route::get('/me', [AuthController::class, 'me'])->middleware(['auth:api', 'throttle:60,1']);
Route::get('/profil', [AuthController::class, 'profil'])->middleware(['auth:api', 'throttle:60,1']);

// Hanya siswa
Route::middleware(['auth:api', 'role:siswa'])->group(function () {
    Route::prefix('/absensi')->group(function () {
        Route::post('/', [AbsensiController::class, 'absen']);
        Route::post('/pulang', [AbsensiController::class, 'absenPulang']);
        Route::post('/izinsakit', [AbsensiController::class, 'izinSakit']);
        Route::post('/cek-jarak', [AbsensiController::class, 'cekJarak']);
        Route::get('/riwayat', [AbsensiController::class, 'riwayat']);
        Route::get('/hariini', [AbsensiController::class, 'hariIni']);
    });

    // Detail profil siswa
    Route::get('/getDetailProfilSiswa', [UtillityController::class, 'getDetailProfilSiswa']);
});

// Hanya admin
Route::middleware(['auth:api', 'role:admin'])->group(function () {

    Route::prefix('/admin')->group(function () {
        Route::get('/rekap', [AdminController::class, 'rekap']);
        // Pengaturan sistem
        Route::get('/pengaturan', [AdminController::class, 'getPengaturan']);
        Route::put('/pengaturan', [AdminController::class, 'updatePengaturan']);
        Route::put('/profil', [AdminController::class, 'updateAdminProfile']);

        // Jurusan
        Route::get('/jurusan', [AdminController::class, 'getJurusan']);
        Route::post('/jurusan', [AdminController::class, 'createJurusan']);
        Route::put('/jurusan/{jurusan}', [AdminController::class, 'updateJurusan']);
        Route::delete('/jurusan/{jurusan}', [AdminController::class, 'deleteJurusan']);
        Route::delete('/jurusan-delete-all', [AdminController::class, 'deleteAllJurusan']);

        // Kelas
        Route::get('/kelas', [AdminController::class, 'getKelas']);
        Route::post('/kelas', [AdminController::class, 'createKelas']);
        Route::put('/kelas/{kelas}', [AdminController::class, 'updateKelas']);
        Route::delete('/kelas/{kelas}', [AdminController::class, 'deleteKelas']);
        Route::delete('/kelas-delete-all', [AdminController::class, 'deleteAllKelas']);
        Route::get('/riwayat-kelas', [AdminController::class, 'getRiwayatKelas']);
        Route::put('/riwayat-kelas/{riwayat}', [AdminController::class, 'updateRiwayatKelas']);
        Route::post('/riwayat-kelas/promote/{kelas}', [AdminController::class, 'promoteClass']);

        // Wali Kelas list
        // Route::get('/walas', [AdminController::class, 'getWalas']);

        // Wali Kelas CRUD
        Route::get('/wali-kelas', [AdminController::class, 'getWaliKelas']);
        Route::post('/wali-kelas', [AdminController::class, 'createWaliKelas']);
        Route::put('/wali-kelas/{walas}', [AdminController::class, 'updateWaliKelas']);
        Route::delete('/wali-kelas/{walas}', [AdminController::class, 'deleteWaliKelas']);
        Route::delete('/wali-kelas-delete-all', [AdminController::class, 'deleteAllWaliKelas']);


        // Guru Piket CRUD
        Route::get('/guru-piket', [AdminController::class, 'getGuruPiket']);
        Route::post('/guru-piket', [AdminController::class, 'createGuruPiket']);
        Route::put('/guru-piket/{gurket}', [AdminController::class, 'updateGuruPiket']);
        Route::delete('/guru-piket/{gurket}', [AdminController::class, 'deleteGuruPiket']);
        Route::delete('/guru-piket-delete-all', [AdminController::class, 'deleteAllGuruPiket']);
        // Route::get('/akun-gurket', [AdminController::class, 'getAkunGuruPiket']);

        // Jadwal Piket CRUD
        Route::get('/jadwal-piket', [AdminController::class, 'getJadwalPiket']);
        Route::post('/jadwal-piket', [AdminController::class, 'createJadwalPiket']);
        Route::put('/jadwal-piket/{jadwal}', [AdminController::class, 'updateJadwalPiket']);
        Route::delete('/jadwal-piket/{jadwal}', [AdminController::class, 'deleteJadwalPiket']);
        Route::delete('/jadwal-piket-delete-all', [AdminController::class, 'deleteAllJadwalPiket']);

        Route::post('/import-siswa', [AdminController::class, 'importSiswa']);
        Route::prefix('/kelola-datasiswa')->group(function () {
            Route::get('/', [AdminController::class, 'getDataSiswa']);
            Route::post('/create', [AdminController::class, 'createDataSiswa']);
            Route::post('/update', [AdminController::class, 'updateDataSiswa']);
            Route::delete('/delete-all', [AdminController::class, 'deleteAllSiswa']);
            Route::delete('/{siswa}', [AdminController::class, 'deleteDataSiswa']);
        });
    });
});


// Admin, gurket & wali kelas
Route::middleware(['auth:api', 'role:admin,gurket,walas'])->group(function () {
    Route::get('/total-siswa', [DashboardController::class, 'totalSiswa']);
    Route::get('/hadir-hariini', [DashboardController::class, 'hadirHariIni']);
    Route::get('/terlambat-hariini', [DashboardController::class, 'terlambatHariIni']);
    // Route::get('/izinsakit-hariini', [DashboardController::class, 'izinSakitHariIni']);
    Route::get('/izin-hariini', [DashboardController::class, 'izinHariIni']);
    Route::get('/sakit-hariini', [DashboardController::class, 'sakitHariIni']);
    // Route::get('/guru/rekap', [GurketController::class, 'rekap']);

    // Utility
    Route::get('/utility/kelas', [UtillityController::class, 'getListKelas']);
    Route::get('/utility/siswa', [UtillityController::class, 'getDataSiswa']);

    // reset pw
    Route::post('/akun/reset-password', [AuthController::class, 'resetPassword']);

    Route::get('/aktivitas-terbaru', [AktivitasController::class, 'index']);

    Route::prefix('/absensi')->group(function () {
        Route::get('/siswaIzinSakit', [GurketController::class, 'getSiswaIzinSakit']);
        Route::post('/updateStatus', [GurketController::class, 'updateStatusIzinSakit']);
        Route::post('/updateAbsensiStatus', [GurketController::class, 'updateAbsensiStatus']);
        Route::get('/hari-ini', [GurketController::class, 'getAbsensiSiswaHariIni']);
        Route::get('/lihat', [GurketController::class, 'showAbsensiSiswa']);

        Route::prefix('/rencana')->group(function () {
            Route::get('/', [GurketController::class, 'getRencanaAbsensi']);
            Route::post('/', [GurketController::class, 'tambahRencanaAbsensi']);
            Route::post('/update-status', [GurketController::class, 'updateRencanaStatusHari']);
        });
    });
});

// Semua role (termasuk siswa) butuh membaca pengaturan
Route::middleware(['auth:api', 'role:admin,gurket,walas,siswa'])->group(function () {
    Route::get('/pengaturan', [AdminController::class, 'getPengaturan']);
});

// Hanya guru piket & wali kelas
Route::middleware(['auth:api', 'role:gurket,walas'])->group(function () {
    Route::get('/guru/laporan', [GurketController::class, 'laporan']);
    Route::get('/walas/info', [GurketController::class, 'walasInfo']);

    Route::put('/profil', [GurketController::class, 'updateProfil']);

    // Route::prefix('kelola-datasiswa')->group(function () {});
});


// Route::prefix('utillity')->group(function () {
//     Route::get('/getListKelas', [UtillityController::class, 'getListKelas']);
// });
