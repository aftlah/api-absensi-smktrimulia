<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Helpers\ImportHelper;
use Illuminate\Http\Request;
use App\Models\Absensi;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;


class GurketController extends Controller
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
            // return response()->json(['message' => 'Role tidak valid'], 403);
            return ApiResponse::error('Role tidak valid', 403);
        }

        return ApiResponse::success([
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

    public function getSiswaIzinSakit()
    {

        $siswaIzinSakit = Absensi::with('siswa')
            ->where('status', 'pending')
            ->get();
        // return response()->json([
        //     'siswa_izin_sakit' => $siswaIzinSakit,
        // ]);
        return ApiResponse::success([
            'siswa_izin_sakit' => $siswaIzinSakit,
        ]);
    }

    public function updateStatusIzinSakit(Request $request)
    {
        $request->validate([
            'absensi_id' => 'required|exists:absensi,absensi_id',
            'status' => 'required|in:izin,sakit',
            'keterangan' => 'nullable|string',
        ]);

        $absensi = Absensi::findOrFail($request->input('absensi_id'));
        $absensi->status = $request->input('status');
        if ($request->filled('keterangan')) {
            $absensi->keterangan = $request->input('keterangan');
        }
        $absensi->save();

        return ApiResponse::success([
            'message' => 'Status absensi berhasil diperbarui',
            'absensi' => $absensi,
        ]);
    }

    public function getAbsensiSiswa(){

        $absensi = Absensi::all();
        return ApiResponse::success([
            'message' => 'Absensi siswa berhasil diambil',
            'absensi' => $absensi,
        ]);
    }

    public function getAbsensiHariIni(){

        $absensi = Absensi::whereDate('tanggal', Carbon::today()->toDateString())->get();
        return ApiResponse::success([
            'message' => 'Absensi siswa hari ini berhasil diambil',
            'absensi' => $absensi,
        ]);
    }


}
