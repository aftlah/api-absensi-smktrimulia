<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Absensi;
use Carbon\Carbon;

class AdminController extends Controller
{
    /**
     * Rekap absensi global (semua siswa)
     */
    public function rekap(Request $request)
    {
        // Filter opsional: tanggal / bulan
        $tanggal = $request->input('tanggal', Carbon::today()->toDateString());

        $rekap = Absensi::with('user')
            ->whereDate('tanggal', $tanggal)
            ->get()
            ->groupBy('user.kelas');

        return response()->json([
            'tanggal' => $tanggal,
            'rekap' => $rekap
        ]); 
    }
}
