<?php

namespace App\Http\Controllers;

use App\Helpers\ImportHelper;
use Illuminate\Http\Request;
use App\Models\Absensi;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;


class GuruController extends Controller
{
    /**
     * Laporan untuk guru piket & wali kelas
     */
    public function laporan(Request $request)
    {
        $user = Auth::user();
        $tanggal = $request->input('tanggal', Carbon::today()->toDateString());

        // Kalau role = guru piket → lihat semua kelas
        if ($user->role === 'gurket') {
            $laporan = Absensi::with('user')
                ->whereDate('tanggal', $tanggal)
                ->get();
        }

        // Kalau role = wali kelas → filter hanya kelasnya
        elseif ($user->role === 'walas') {
            $laporan = Absensi::with('user')
                ->whereDate('tanggal', $tanggal)
                ->whereHas('user', function ($q) use ($user) {
                    $q->where('kelas', $user->kelas);
                })
                ->get();
        } else {
            return response()->json(['message' => 'Role tidak valid'], 403);
        }

        return response()->json([
            'tanggal' => $tanggal,
            'laporan' => $laporan
        ]);
    }

    public function importSiswa(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv'
        ]);

        try {
            $importer = new ImportHelper();
            $kelasTerimpor = $importer->importSiswa($request->file('file'));

            return response()->json([
                'message' => 'Data siswa berhasil diimpor',
                'kelas_terimpor' => $kelasTerimpor,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal mengimpor data siswa',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

}
