<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use Illuminate\Http\Request;
use App\Models\Absensi;
use App\Models\Pengaturan;
use Illuminate\Support\Facades\Auth;

class AbsensiController extends Controller
{
    // haversine formula        
    private function hitungJarak($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earthRadius * $c;
    }

    // siswa absen datang
    public function absen(Request $request)
    {
        $request->validate([
            'latitude' => 'required',
            'longitude' => 'required'
        ]);

        $user = Auth::user(); // ambil user login
        if ($user->role !== 'siswa') {
            // return response()->json(['error' => 'Hanya siswa yang bisa absen'], 403);
            return ApiResponse::error('Hanya siswa yang bisa absen', null, 403);
        }

        $pengaturan = Pengaturan::first();
        $jarak = $this->hitungJarak($request->latitude, $request->longitude, $pengaturan->latitude, $pengaturan->longitude);

        if ($jarak > $pengaturan->radius_meter) {
            // return response()->json(['error' => 'Di luar radius absensi'], 422);
            return ApiResponse::error('Di luar radius absensi', ['distance' => $jarak], 422);
        }

        $absensi = Absensi::create([
            'siswa_id' => $user->siswa->siswa_id,
            'tanggal' => now()->toDateString(),
            'jam_datang' => now()->toTimeString(),
            'status' => 'hadir',
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
        ]);

        // return response()->json(['message' => 'Absensi berhasil', 'data' => $absensi]);
        return ApiResponse::success($absensi, 'Absensi berhasil');
    }

    // siswa absen pulang
    public function absenPulang(Request $request)
    {
        $request->validate([
            'latitude' => 'required',
            'longitude' => 'required'
        ]);

        $user = Auth::user();
        if ($user->role !== 'siswa') {
            return ApiResponse::error('Hanya siswa yang bisa absen', null, 403);
        }

        // Validasi waktu - tidak boleh absen pulang sebelum jam 15:00
        $currentTime = now();
        $jamPulang = $currentTime->copy()->setTime(15, 0, 0); // 15:00:00

        if ($currentTime->lt($jamPulang)) {
            return ApiResponse::error('Absensi pulang hanya bisa dilakukan setelah jam 15:00', null, 422);
        }

        $pengaturan = Pengaturan::first();
        $jarak = $this->hitungJarak($request->latitude, $request->longitude, $pengaturan->latitude, $pengaturan->longitude);

        if ($jarak > $pengaturan->radius_meter) {
            return ApiResponse::error('Di luar radius absensi', ['distance' => $jarak], 422);
        }

        // Cek apakah sudah ada absensi datang hari ini
        $absensiHariIni = Absensi::where('siswa_id', $user->siswa->siswa_id)
            ->where('tanggal', now()->toDateString())
            ->first();

        if (!$absensiHariIni) {
            return ApiResponse::error('Anda belum melakukan absensi datang hari ini', null, 422);
        }

        // Update absensi pulang
        $absensiHariIni->update([
            'jam_pulang' => now()->toTimeString(),
        ]);

        return ApiResponse::success($absensiHariIni, 'Absensi pulang berhasil');
    }

    // lihat riwayat absensi siswa
    public function riwayat()
    {
        $user = Auth::user();
        $riwayat = Absensi::where('siswa_id', $user->siswa->siswa_id)->get();

        // return response()->json($riwayat);
        return ApiResponse::success($riwayat);
    }
}