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

// Hanya siswa
Route::middleware(['auth:api', 'role:siswa'])->group(function () {
    Route::prefix('/absensi')->group(function () {
        Route::post('/', [AbsensiController::class, 'absen']);
        Route::post('/pulang', [AbsensiController::class, 'absenPulang']);
        Route::post('/izinsakit', [AbsensiController::class, 'izinSakit']);
        Route::get('/riwayat', [AbsensiController::class, 'riwayat']);
        Route::get('/hariini', [AbsensiController::class, 'hariIni']);
    });
});

// Hanya admin
Route::middleware(['auth:api', 'role:admin'])->group(function () {
    Route::get('/admin/rekap', [AdminController::class, 'rekap']);
    // Pengaturan sistem
    Route::get('/admin/pengaturan', [AdminController::class, 'getPengaturan']);
    Route::put('/admin/pengaturan', [AdminController::class, 'updatePengaturan']);
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
    Route::post('/import-siswa', [GurketController::class, 'importSiswa']);
    Route::get('/aktivitas-terbaru', [AktivitasController::class, 'index']);

    Route::prefix('kelola-datasiswa')->group(function () {
        Route::get('/', [GurketController::class, 'getDataSiswa']);
        Route::post('/update', [GurketController::class, 'updateDataSiswa']);
    });


    Route::prefix('/absensi')->group(function () {
        Route::get('/siswaIzinSakit', [GurketController::class, 'getSiswaIzinSakit']);
        Route::post('/updateStatus', [GurketController::class, 'updateStatusIzinSakit']);
        Route::get('/hari-ini', [GurketController::class, 'getAbsensiSiswaHariIni']);
        Route::get('/lihat', [GurketController::class, 'showAbsensiSiswa']);
        
        Route::prefix('/rencana')->group(function () {
            Route::get('/', [GurketController::class, 'getRencanaAbsensi']);
            Route::post('/', [GurketController::class, 'tambahRencanaAbsensi']);
        });

    });
});


Route::prefix('utillity')->group(function () {
    Route::get('/getListKelas', [UtillityController::class, 'getListKelas']);
});

// testing /api
Route::get('/', function () {
    return response()->json(['message' => 'Hello World!']);
});
