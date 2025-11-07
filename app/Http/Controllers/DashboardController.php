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

    // public function hadirHariIni()
    // {

    //     $totalHadir = Siswa::whereHas('absensi', function ($q) {
    //         $q->where('tanggal', now()->toDateString())
    //             ->where('status', 'hadir');
    //     })->count();

    //     return ApiResponse::success([
    //         'siswa_hadir_hariini' => $totalHadir,
    //     ], 'Total siswa hadir hari ini berhasil diambil');
    // }

    public function hadirHariIni()
    {
        $hariIni = now()->toDateString();

        $siswaHadir = Siswa::whereHas('absensi', function ($absensiQuery) use ($hariIni) {
            $absensiQuery
                ->where('status', 'hadir')
                ->whereHas('rencanaAbsensi', function ($rencanaQuery) use ($hariIni) {
                    $rencanaQuery->where('tanggal', $hariIni);
                });
        })
            ->with([
                'absensi' => function ($absensiQuery) use ($hariIni) {
                    $absensiQuery
                        ->where('status', 'hadir')
                        ->whereHas('rencanaAbsensi', function ($rencanaQuery) use ($hariIni) {
                            $rencanaQuery->where('tanggal', $hariIni);
                        })
                        ->with('rencanaAbsensi');
                },
                'kelas'
            ])
            ->get();

        return ApiResponse::success([
            'total_hadir' => $siswaHadir->count(),
            'siswa_hadir_hari_ini' => $siswaHadir
        ], 'Data siswa hadir hari ini berhasil diambil');
    }


    // public function terlambatHariIni()
    // {
    //     $totalTerlambat = Siswa::whereHas('absensi', function ($q) {
    //         $q->whereHas('rencanaAbsensi', function ($q) {
    //             $q->where('tanggal', now()->toDateString());
    //         })->where('status', 'terlambat');
    //     })
    //         ->orWhereHas('rencanaAbsensi', function ($q) {
    //             $q->where('tanggal', now()->toDateString())
    //                 ->where('status', 'terlambat');
    //         })
    //         ->count();
    //     return ApiResponse::success([
    //         'siswa_terlambat_hariini' => $totalTerlambat,
    //     ], 'Total siswa terlambat hari ini berhasil diambil');
    // }

    public function terlambatHariIni()
    {
        $hariIni = now()->toDateString();

        $siswaTerlambat = Siswa::whereHas('absensi', function ($absensiQuery) use ($hariIni) {
            $absensiQuery->where('status', 'terlambat')
                ->whereHas('rencanaAbsensi', function ($rencanaQuery) use ($hariIni) {
                    $rencanaQuery->where('tanggal', $hariIni);
                });
        })
            ->with([
                'absensi' => function ($absensiQuery) use ($hariIni) {
                    $absensiQuery->where('status', 'terlambat')
                        ->whereHas('rencanaAbsensi', function ($rencanaQuery) use ($hariIni) {
                            $rencanaQuery->where('tanggal', $hariIni);
                        })
                        ->with('rencanaAbsensi');
                },
                'kelas'
            ])
            ->get();

        return ApiResponse::success([
            'total_terlambat' => $siswaTerlambat->count(),
            'siswa_terlambat_hari_ini' => $siswaTerlambat
        ], 'Data siswa terlambat hari ini berhasil diambil');
    }

    // public function izinSakitHariIni()
    // {
    //     $totalIzinSakit = Siswa::whereHas('absensi', function ($q) {
    //         $q->where('tanggal', now()->toDateString())
    //             ->whereIn('status', ['pending']);
    //     })->count();
    //     return ApiResponse::success([
    //         'siswa_izin_sakit_hariini' => $totalIzinSakit,
    //     ], 'Total siswa izin sakit hari ini berhasil diambil');
    // }
    public function izinSakitHariIni()
    {
        $hariIni = now()->toDateString();

        $siswaIzinSakit = Siswa::whereHas('absensi', function ($absensiQuery) use ($hariIni) {
            $absensiQuery
                ->whereIn('status', ['izin', 'sakit']) // sesuaikan dengan status di DB kamu
                ->whereHas('rencanaAbsensi', function ($rencanaQuery) use ($hariIni) {
                    $rencanaQuery->where('tanggal', $hariIni);
                });
        })
            ->with([
                'absensi' => function ($absensiQuery) use ($hariIni) {
                    $absensiQuery->whereIn('status', ['izin', 'sakit'])
                        ->whereHas('rencanaAbsensi', function ($rencanaQuery) use ($hariIni) {
                            $rencanaQuery->where('tanggal', $hariIni);
                        })
                        ->with('rencanaAbsensi');
                },
                'kelas'
            ])
            ->get();

        return ApiResponse::success([
            'total_izin_sakit' => $siswaIzinSakit->count(),
            'siswa_izin_sakit_hari_ini' => $siswaIzinSakit
        ], 'Data siswa izin/sakit hari ini berhasil diambil');
    }
}
