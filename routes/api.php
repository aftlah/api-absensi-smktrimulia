<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');



use App\Http\Controllers\AbsensiController;
use App\Http\Controllers\AuthController;

Route::post('/login', [AuthController::class, 'login']);

// hanya siswa
Route::middleware(['auth:api', 'role:siswa'])->group(function () {
    Route::post('/absensi', [AbsensiController::class, 'absen']);
    Route::get('/absensi/riwayat', [AbsensiController::class, 'riwayat']);
});

// hanya admin
Route::middleware(['auth:api', 'role:admin'])->group(function () {
    Route::get('/admin/rekap', function () {
        return "Rekap Absensi untuk Admin";
    });
});

// hanya guru piket dan walas
Route::middleware(['auth:api', 'role:gurket,walas'])->group(function () {
    Route::get('/guru/laporan', function () {
        return "Laporan Guru Piket & Wali Kelas";
    });
});
