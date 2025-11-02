<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\User;
use App\Models\Siswa;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function totalSiswa()
    {
        $totalSiswa = Siswa::count();

        return ApiResponse::success([
            'total_siswa' => $totalSiswa,
        ], 'Total siswa berhasil diambil');
    }

    public function hadirHariIni()
    {

        $totalHadir = Siswa::whereHas('absensi', function ($q) {
            $q->where('tanggal', now()->toDateString())
                ->where('status', 'hadir');
        })->count();

        return ApiResponse::success([
            'siswa_hadir_hariini' => $totalHadir,
        ], 'Total siswa hadir hari ini berhasil diambil');
    }

    public function terlambatHariIni()
    {
        $totalTerlambat = Siswa::whereHas('absensi', function ($q) {
            $q->where('tanggal', now()->toDateString())
                ->where('status', 'terlambat');
        })->count();
        return ApiResponse::success([
            'siswa_terlambat_hariini' => $totalTerlambat,
        ], 'Total siswa terlambat hari ini berhasil diambil');
    }

    public function izinSakitHariIni()
    {
        $totalIzinSakit = Siswa::whereHas('absensi', function ($q) {
            $q->where('tanggal', now()->toDateString())
                ->whereIn('status', ['pending']);
        })->count();
        return ApiResponse::success([
            'siswa_izin_sakit_hariini' => $totalIzinSakit,
        ], 'Total siswa izin sakit hari ini berhasil diambil');
    }
}
