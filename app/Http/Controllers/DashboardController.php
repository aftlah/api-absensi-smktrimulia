<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\Siswa;
use App\Models\WaliKelas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function totalSiswa()
    {
        $user = Auth::user();

        // Jika wali kelas, hitung hanya siswa di kelas yang diampu
        if ($user && $user->role === 'walas') {
            $walas = WaliKelas::with('kelas')->where('akun_id', $user->akun_id)->first();
            if ($walas && $walas->kelas) {
                $totalSiswa = Siswa::where('kelas_id', $walas->kelas->kelas_id)->count();
            } else {
                $totalSiswa = 0;
            }
        } else {
            // admin / gurket melihat total seluruh siswa
            $totalSiswa = Siswa::count();
        }

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
        $user = Auth::user();

        $query = Siswa::query();

        // Filter kelas jika wali kelas
        if ($user && $user->role === 'walas') {
            $walas = WaliKelas::with('kelas')->where('akun_id', $user->akun_id)->first();
            if ($walas && $walas->kelas) {
                $query->where('kelas_id', $walas->kelas->kelas_id);
            } else {
                // Jika tidak ada kelas, kembalikan nol
                return ApiResponse::success([
                    'total_hadir' => 0,
                    'siswa_hadir_hari_ini' => [],
                ], 'Data siswa hadir hari ini berhasil diambil');
            }
        }

        $siswaHadir = $query->whereHas('absensi', function ($absensiQuery) use ($hariIni) {
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
        $user = Auth::user();

        $query = Siswa::query();

        if ($user && $user->role === 'walas') {
            $walas = WaliKelas::with('kelas')->where('akun_id', $user->akun_id)->first();
            if ($walas && $walas->kelas) {
                $query->where('kelas_id', $walas->kelas->kelas_id);
            } else {
                return ApiResponse::success([
                    'total_terlambat' => 0,
                    'siswa_terlambat_hari_ini' => [],
                ], 'Data siswa terlambat hari ini berhasil diambil');
            }
        }

        $siswaTerlambat = $query->whereHas('absensi', function ($absensiQuery) use ($hariIni) {
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
        $user = Auth::user();

        $query = Siswa::query();

        if ($user && $user->role === 'walas') {
            $walas = WaliKelas::with('kelas')->where('akun_id', $user->akun_id)->first();
            if ($walas && $walas->kelas) {
                $query->where('kelas_id', $walas->kelas->kelas_id);
            } else {
                return ApiResponse::success([
                    'total_izin_sakit' => 0,
                    'siswa_izin_sakit_hari_ini' => [],
                ], 'Data siswa izin/sakit hari ini berhasil diambil');
            }
        }

        $siswaIzinSakit = $query->whereHas('absensi', function ($absensiQuery) use ($hariIni) {
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

    // Tambahan: pisahkan izin dan sakit untuk semua role, ter-filter walas
    public function izinHariIni()
    {
        $hariIni = now()->toDateString();
        $user = Auth::user();

        $query = Siswa::query();
        if ($user && $user->role === 'walas') {
            $walas = WaliKelas::with('kelas')->where('akun_id', $user->akun_id)->first();
            if ($walas && $walas->kelas) {
                $query->where('kelas_id', $walas->kelas->kelas_id);
            } else {
                return ApiResponse::success([
                    'total_izin' => 0,
                    'siswa_izin_hari_ini' => [],
                ], 'Data siswa izin hari ini berhasil diambil');
            }
        }

        $siswaIzin = $query->whereHas('absensi', function ($absensiQuery) use ($hariIni) {
            $absensiQuery
                ->where('status', 'izin')
                ->whereHas('rencanaAbsensi', function ($rencanaQuery) use ($hariIni) {
                    $rencanaQuery->where('tanggal', $hariIni);
                });
        })
            ->with([
                'absensi' => function ($absensiQuery) use ($hariIni) {
                    $absensiQuery->where('status', 'izin')
                        ->whereHas('rencanaAbsensi', function ($rencanaQuery) use ($hariIni) {
                            $rencanaQuery->where('tanggal', $hariIni);
                        })
                        ->with('rencanaAbsensi');
                },
                'kelas'
            ])
            ->get();

        return ApiResponse::success([
            'total_izin' => $siswaIzin->count(),
            'siswa_izin_hari_ini' => $siswaIzin,
        ], 'Data siswa izin hari ini berhasil diambil');
    }

    public function sakitHariIni()
    {
        $hariIni = now()->toDateString();
        $user = Auth::user();

        $query = Siswa::query();
        if ($user && $user->role === 'walas') {
            $walas = WaliKelas::with('kelas')->where('akun_id', $user->akun_id)->first();
            if ($walas && $walas->kelas) {
                $query->where('kelas_id', $walas->kelas->kelas_id);
            } else {
                return ApiResponse::success([
                    'total_sakit' => 0,
                    'siswa_sakit_hari_ini' => [],
                ], 'Data siswa sakit hari ini berhasil diambil');
            }
        }

        $siswaSakit = $query->whereHas('absensi', function ($absensiQuery) use ($hariIni) {
            $absensiQuery
                ->where('status', 'sakit')
                ->whereHas('rencanaAbsensi', function ($rencanaQuery) use ($hariIni) {
                    $rencanaQuery->where('tanggal', $hariIni);
                });
        })
            ->with([
                'absensi' => function ($absensiQuery) use ($hariIni) {
                    $absensiQuery->where('status', 'sakit')
                        ->whereHas('rencanaAbsensi', function ($rencanaQuery) use ($hariIni) {
                            $rencanaQuery->where('tanggal', $hariIni);
                        })
                        ->with('rencanaAbsensi');
                },
                'kelas'
            ])
            ->get();

        return ApiResponse::success([
            'total_sakit' => $siswaSakit->count(),
            'siswa_sakit_hari_ini' => $siswaSakit,
        ], 'Data siswa sakit hari ini berhasil diambil');
    }
}
