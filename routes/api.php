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
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:api');
Route::post('/refresh', [AuthController::class, 'refresh'])->middleware('auth:api');
Route::get('/me', [AuthController::class, 'me'])->middleware('auth:api');
Route::get('/profil', [AuthController::class, 'profil'])->middleware('auth:api');

// Hanya siswa
Route::middleware(['auth:api', 'role:siswa'])->group(function () {
    Route::prefix('/absensi')->group(function () {
        Route::post('/', [AbsensiController::class, 'absen']);
        Route::post('/pulang', [AbsensiController::class, 'absenPulang']);
        Route::post('/izinsakit', [AbsensiController::class, 'izinSakit']);
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

        // Kelas
        Route::get('/kelas', [AdminController::class, 'getKelas']);
        Route::post('/kelas', [AdminController::class, 'createKelas']);
        Route::put('/kelas/{kelas}', [AdminController::class, 'updateKelas']);
        Route::delete('/kelas/{kelas}', [AdminController::class, 'deleteKelas']);
        Route::get('/riwayat-kelas', [AdminController::class, 'getRiwayatKelas']);

        // Wali Kelas list
        // Route::get('/walas', [AdminController::class, 'getWalas']);

        // Wali Kelas CRUD
        Route::get('/wali-kelas', [AdminController::class, 'getWaliKelas']);
        Route::post('/wali-kelas', [AdminController::class, 'createWaliKelas']);
        Route::put('/wali-kelas/{walas}', [AdminController::class, 'updateWaliKelas']);
        Route::delete('/wali-kelas/{walas}', [AdminController::class, 'deleteWaliKelas']);
        

        // Guru Piket CRUD
        Route::get('/guru-piket', [AdminController::class, 'getGuruPiket']);
        Route::post('/guru-piket', [AdminController::class, 'createGuruPiket']);
        Route::put('/guru-piket/{gurket}', [AdminController::class, 'updateGuruPiket']);
        Route::delete('/guru-piket/{gurket}', [AdminController::class, 'deleteGuruPiket']);
        Route::get('/akun-gurket', [AdminController::class, 'getAkunGuruPiket']);

        // Jadwal Piket CRUD
        Route::get('/jadwal-piket', [AdminController::class, 'getJadwalPiket']);
        Route::post('/jadwal-piket', [AdminController::class, 'createJadwalPiket']);
        Route::put('/jadwal-piket/{jadwal}', [AdminController::class, 'updateJadwalPiket']);
        Route::delete('/jadwal-piket/{jadwal}', [AdminController::class, 'deleteJadwalPiket']);

        Route::post('/import-siswa', [AdminController::class, 'importSiswa']);
        Route::prefix('/kelola-datasiswa')->group(function () {
            Route::get('/', [AdminController::class, 'getDataSiswa']);
            Route::post('/update', [AdminController::class, 'updateDataSiswa']);
            Route::post('/create', [AdminController::class, 'createDataSiswa']);
        });
    });
});



// Admin, gurket & wali kelas
Route::middleware(['auth:api', 'role:admin,gurket,walas'])->group(function () {
    Route::get('/total-siswa', [DashboardController::class, 'totalSiswa']);
    Route::get('/hadir-hariini', [DashboardController::class, 'hadirHariIni']);
    Route::get('/terlambat-hariini', [DashboardController::class, 'terlambatHariIni']);
    Route::get('/izinsakit-hariini', [DashboardController::class, 'izinSakitHariIni']);
    Route::get('/izin-hariini', [DashboardController::class, 'izinHariIni']);
    Route::get('/sakit-hariini', [DashboardController::class, 'sakitHariIni']);
    Route::get('/guru/rekap', [GurketController::class, 'rekap']);

    Route::post('/akun/reset-password', [AuthController::class, 'resetPassword']);

    // Utility
    Route::get('/utility/kelas', [UtillityController::class, 'getListKelas']);
});

// Semua role (termasuk siswa) butuh membaca pengaturan
Route::middleware(['auth:api', 'role:admin,gurket,walas,siswa'])->group(function () {
    Route::get('/pengaturan', [AdminController::class, 'getPengaturan']);
});

// Hanya guru piket & wali kelas
Route::middleware(['auth:api', 'role:gurket,walas'])->group(function () {
    Route::get('/guru/laporan', [GurketController::class, 'laporan']);
    Route::get('/walas/info', [GurketController::class, 'walasInfo']);
    Route::get('/aktivitas-terbaru', [AktivitasController::class, 'index']);

    Route::put('/profil', [GurketController::class, 'updateProfil']);

    Route::prefix('kelola-datasiswa')->group(function () {});


    Route::prefix('/absensi')->group(function () {
        Route::get('/siswaIzinSakit', [GurketController::class, 'getSiswaIzinSakit']);
        Route::post('/updateStatus', [GurketController::class, 'updateStatusIzinSakit']);
        Route::get('/hari-ini', [GurketController::class, 'getAbsensiSiswaHariIni']);
        Route::get('/lihat', [GurketController::class, 'showAbsensiSiswa']);

        Route::prefix('/rencana')->group(function () {
            Route::get('/', [GurketController::class, 'getRencanaAbsensi']);
            Route::post('/', [GurketController::class, 'tambahRencanaAbsensi']);
            Route::post('/update-status', [GurketController::class, 'updateRencanaStatusHari']);
        });
    });
});


Route::prefix('utillity')->group(function () {
    Route::get('/getListKelas', [UtillityController::class, 'getListKelas']);
});
