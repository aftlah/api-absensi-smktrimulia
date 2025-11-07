<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\Kelas;
use Illuminate\Http\Request;

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
                    'thn_ajaran'  => $item->thn_ajaran,
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
}
