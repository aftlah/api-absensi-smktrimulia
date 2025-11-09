<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use Illuminate\Http\Request;
use App\Models\Absensi;
use App\Models\Pengaturan;
use App\Models\RencanaAbsensi;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;


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
    // public function absen(Request $request)
    // {
    //     $request->validate([
    //         'latitude' => 'required',
    //         'longitude' => 'required'
    //     ]);


    //     $user = Auth::user(); // ambil user login
    //     if ($user->role !== 'siswa') {
    //         // return response()->json(['error' => 'Hanya siswa yang bisa absen'], 403);
    //         return ApiResponse::error('Hanya siswa yang bisa absen', null, 403);
    //     }

    //     $pengaturan = Pengaturan::first();
    //     $jarak = $this->hitungJarak($request->latitude, $request->longitude, $pengaturan->latitude, $pengaturan->longitude);

    //     if ($jarak > $pengaturan->radius_meter) {
    //         // return response()->json(['error' => 'Di luar radius absensi'], 422);
    //         return ApiResponse::error('Di luar radius absensi', ['distance' => $jarak], 422);
    //     }

    //     $absensi = Absensi::create([
    //         'siswa_id' => $user->siswa->siswa_id,
    //         'tanggal' => now()->toDateString(),
    //         'jam_datang' => now()->toTimeString(),
    //         'status' => 'hadir',
    //         'jenis_absen' => $request->jenis_absen,
    //         'latitude' => $request->latitude,
    //         'longitude' => $request->longitude,
    //         'rensi_id' => null
    //     ]);

    //     // return response()->json(['message' => 'Absensi berhasil', 'data' => $absensi]);
    //     return ApiResponse::success($absensi, 'Absensi berhasil');
    // }

    public function absen(Request $request)
    {
        $request->validate([
            'latitude' => 'required',
            'longitude' => 'required'
        ]);

        $user = Auth::user();

        if ($user->role !== 'siswa') {
            return ApiResponse::error('Hanya siswa yang bisa absen', null, 403);
        }

        $pengaturan = Pengaturan::first();
        $jarak = $this->hitungJarak(
            $request->latitude,
            $request->longitude,
            $pengaturan->latitude,
            $pengaturan->longitude
        );

        if ($jarak > $pengaturan->radius_meter) {
            return ApiResponse::error('Di luar radius absensi', ['distance' => $jarak], 422);
        }

        $hariIni = now()->toDateString();
        $rencana = RencanaAbsensi::whereDate('tanggal', $hariIni)
            ->where('kelas_id', $user->siswa->kelas_id)
            ->first();

        if (!$rencana) {
            return ApiResponse::error('Belum ada rencana absensi untuk hari ini', null, 422);
        }

        $sudahAbsen = Absensi::where('siswa_id', $user->siswa->siswa_id)
            ->whereDate('jam_datang', $hariIni)
            ->exists();

        if ($sudahAbsen) {
            return ApiResponse::error('Kamu sudah absen hari ini', null, 422);
        }

        $absensi = Absensi::create([
            'siswa_id'         => $user->siswa->siswa_id,
            'rensi_id'         => $rencana->rensi_id,
            'jam_datang'       => now()->toTimeString(),
            'latitude_datang'  => $request->latitude,
            'longitude_datang' => $request->longitude,
            'status'           => 'hadir',
            'is_verif'         => 0,
        ]);

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

    public function izinSakit(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tanggal' => 'required|date', // contoh format: '2024-06-10'
            'keterangan' => 'required|string',
            'bukti' => 'required|file|mimes:jpg,jpeg,png,pdf|max:2048',
            'jenis_absen' => 'required',
        ], [
            'tanggal.required' => 'Tanggal izin sakit harus diisi',
            'tanggal.date' => 'Format tanggal tidak valid, gunakan YYYY-MM-DD',
            'keterangan.required' => 'Keterangan harus diisi',
            'bukti.required' => 'Bukti harus diunggah',
            'bukti.file' => 'Bukti harus berupa file',
            'bukti.mimes' => 'Bukti harus berupa file dengan format: jpg, jpeg, png, pdf',
            'bukti.max' => 'Ukuran file bukti maksimal 2MB',
            'jenis_absen.required' => 'Jenis absen harus diisi',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validasi gagal', $validator->errors(), 422);
        }

        $user = Auth::user();
        if ($user->role !== 'siswa') {
            return ApiResponse::error('Hanya siswa yang bisa mengajukan izin sakit', null, 403);
        }

        $existingAbsensi = Absensi::where('siswa_id', $user->siswa->siswa_id)
            ->where('tanggal', $request->tanggal)
            ->first();


        if ($existingAbsensi) {
            return ApiResponse::error('Anda sudah memiliki catatan absensi pada tanggal tersebut', null, 422);
        }

        $path = $request->file('bukti')->store('izin_sakit', 'public');

        $absensi = Absensi::create([
            'siswa_id' => $user->siswa->siswa_id,
            'tanggal' => $request->tanggal,
            'status' => 'pending',
            'jenis_absen' => $request->jenis_absen,
            'keterangan' => $request->keterangan,
            'bukti' => $path,
        ]);

        return ApiResponse::success($absensi, 'Izin sakit berhasil diajukan');
    }

    // lihat riwayat absensi siswa
    public function riwayat()
    {
        $user = Auth::user();

        // Ambil semua absensi siswa dengan relasi rencana_absensi dan tanggalnya
        $riwayat = Absensi::where('siswa_id', $user->siswa->siswa_id)
            ->with(['rencanaAbsensi' => function ($query) {
                $query->select('rensi_id', 'tanggal', 'status_hari', 'keterangan');
            }])
            ->orderByDesc('absensi_id')
            ->get();

        foreach ($riwayat as $item) {
            if ($item->rencanaAbsensi) {
                $item->rencanaAbsensi->tanggal = \Carbon\Carbon::parse($item->rencanaAbsensi->tanggal)->format('d-m-Y');
            }
        }

        // Tambahkan URL bukti jika ada
        foreach ($riwayat as $item) {
            if ($item->bukti) {
                $item->bukti = asset('storage/' . $item->bukti);
            }
        }

        return ApiResponse::success($riwayat, 'Riwayat absensi berhasil diambil');
    }


    public function hariIni()
    {
        $user = Auth::user();

        $riwayat = Absensi::where('siswa_id', $user->siswa->siswa_id)
            ->whereHas('rencanaAbsensi', function ($query) {
                $query->whereDate('tanggal', now()->toDateString());
            })
            ->with('rencanaAbsensi')
            ->first();

        if ($riwayat && $riwayat->bukti) {
            $riwayat->bukti = asset('storage/' . $riwayat->bukti);
        }

        return ApiResponse::success($riwayat, 'Riwayat absensi hari ini berhasil diambil');
    }
}
