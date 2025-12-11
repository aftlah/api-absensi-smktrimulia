<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\Jurusan;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\WaliKelas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UtillityController extends Controller
{
    public function getListKelas()
    {
        $kelas = Kelas::with('jurusan')
            ->get()
            ->map(function ($item) {
                return [
                    'kelas_id'    => $item->kelas_id,
                    'tingkat'     => $item->tingkat,
                    'paralel'     => $item->paralel,
                    'jurusan_id'  => $item->jurusan_id,
                    'walas_id'    => $item->walas_id,
                    'created_at'  => $item->created_at,
                    'updated_at'  => $item->updated_at,
                    'jurusan'     => $item->jurusan->nama_jurusan,
                    'jurusan_id'   => $item->jurusan->jurusan_id,
                ];
            });

        return ApiResponse::success($kelas, 'Kelas berhasil diambil');
    }


    /**
     * Ambil detail profil siswa
     */
    public function getDetailProfilSiswa(Request $request)
    {
        $user = Auth::user();

        $siswa = Siswa::with(['akun', 'kelas.jurusan', 'kelas.walas'])
            ->where('akun_id', $user->akun_id)
            ->first();

        if (!$siswa) {
            return ApiResponse::error('Profil siswa tidak ditemukan', null, 404);
        }

        $kelas = $siswa->kelas;
        $jurusan = $kelas?->jurusan;
        $walas = $kelas?->walas;

        $kelasLabel = $kelas ? trim(($kelas->tingkat ?? '') . ' ' . ($jurusan->nama_jurusan ?? '') . ' ' . ($kelas->paralel ?? '')) : null;

        return ApiResponse::success([
            'siswa_id' => $siswa->siswa_id,
            'nis' => $siswa->nis,
            'nama' => $siswa->nama,
            'jenis_kelamin' => $siswa->jenkel,
            'username' => $siswa->akun?->username,
            'wali_kelas' => $walas?->nama,
            'kelas' => $kelasLabel,
            'jurusan' => $jurusan?->nama_jurusan,
        ], 'Detail profil siswa berhasil diambil');
    }
}
