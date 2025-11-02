<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\Kelas;
use Illuminate\Http\Request;

class UtillityController extends Controller
{
    public function getListKelas()
    {
        $kelas = Kelas::all();
        return ApiResponse::success($kelas, 'Kelas berhasil diambil');
    }
}
