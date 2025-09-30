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
        ]);
    }

    public function hadirHariIni()
    {

        $totalHadir = Siswa::whereHas('absensi', function ($q) {
            $q->where('tanggal', now()->toDateString())
                ->where('status', 'hadir');
        })->count();

        return ApiResponse::success([
            'siswa_hadir_hariini' => $totalHadir,
        ]);
    }

    public function terlambatHariIni()
    {
        $totalTerlambat = Siswa::whereHas('absensi', function ($q) {
            $q->where('tanggal', now()->toDateString())
                ->where('status', 'terlambat');
        })->count();
        return ApiResponse::success([
            'siswa_terlambat_hariini' => $totalTerlambat,
        ]);
    }

    public function izinSakitHariIni(){
        $totalIzinSakit = Siswa::whereHas('absensi', function ($q) {
            $q->where('tanggal', now()->toDateString())
                ->whereIn('status', ['izin', 'sakit']);
        })->count();
        return ApiResponse::success([
            'siswa_izin_sakit_hariini' => $totalIzinSakit,
        ]);
    }
}
