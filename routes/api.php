<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AbsensiController;
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
    Route::get('/absensi/riwayat', [AbsensiController::class, 'riwayat']);
});

// Hanya admin
Route::middleware(['auth:api', 'role:admin'])->group(function () {
    Route::get('/admin/rekap', function () {
        return response()->json(['message' => 'Rekap Absensi untuk Admin']);
    });
});

// Hanya guru piket & wali kelas
Route::middleware(['auth:api', 'role:gurket,walas'])->group(function () {
    Route::get('/guru/laporan', function () {
        return response()->json(['message' => 'Laporan Guru Piket & Wali Kelas']);
    });
});