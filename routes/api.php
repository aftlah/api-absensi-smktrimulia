<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AbsensiController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\GuruController;
use Illuminate\Support\Facades\Route;

// Auth
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:api');
Route::post('/refresh', [AuthController::class, 'refresh'])->middleware('auth:api');
Route::get('/me', [AuthController::class, 'me'])->middleware('auth:api');

// Hanya siswa
Route::middleware(['auth:api', 'role:siswa'])->group(function () {
    Route::post('/absensi', [AbsensiController::class, 'absen']);
    Route::post('/absensi/pulang', [AbsensiController::class, 'absenPulang']);
    Route::post('/absensi/izinsakit', [AbsensiController::class, 'izinSakit']);
    Route::get('/absensi/riwayat', [AbsensiController::class, 'riwayat']);
    Route::get('/absensi/hariini', [AbsensiController::class, 'hariIni']);
});

// Hanya admin
Route::middleware(['auth:api', 'role:admin'])->group(function () {
    Route::get('/admin/rekap', [AdminController::class, 'rekap']);
});

// Hanya guru piket & wali kelas
Route::middleware(['auth:api', 'role:gurket,walas'])->group(function () {
    Route::get('/guru/laporan', [GuruController::class, 'laporan']);
});
