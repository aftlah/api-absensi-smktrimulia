<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\AktivitasTerbaru;

class AktivitasController extends Controller
{
    public function index()
    {
        $aktivitas = AktivitasTerbaru::latest()->take(5)->get();
        // return response()->json($aktivitas)
        return ApiResponse::success($aktivitas, 'Berhasil mengambil data aktivitas terbaru');
    }
}
