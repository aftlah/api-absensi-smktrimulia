<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Requests\AbsensiRequest;
use App\Http\Requests\IzinSakitRequest;
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

    public function absen(AbsensiRequest $request)
    {

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
            ->whereHas('rencanaAbsensi', function ($q) use ($hariIni) {
                $q->whereDate('tanggal', $hariIni);
            })
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
    public function absenPulang(AbsensiRequest $request)
    {
        $user = Auth::user();
        if ($user->role !== 'siswa') {
            return ApiResponse::error('Hanya siswa yang bisa absen', null, 403);
        }

        $currentTime = now();
        $jamPulang = $currentTime->copy()->setTime(15, 0, 0);

        if ($currentTime->lt($jamPulang)) {
            return ApiResponse::error('Absensi pulang hanya bisa dilakukan setelah jam 15:00', null, 422);
        }

        // Ambil pengaturan lokasi & radius
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

        // Cari rencana absensi hari ini
        $rencana = RencanaAbsensi::whereDate('tanggal', now()->toDateString())
            ->whereHas('kelas', function ($q) use ($user) {
                $q->where('kelas_id', $user->siswa->kelas_id);
            })
            ->first();

        if (!$rencana) {
            return ApiResponse::error('Belum ada rencana absensi untuk hari ini', null, 422);
        }

        $absensi = Absensi::where('siswa_id', $user->siswa->siswa_id)
            ->whereHas('rencanaAbsensi', function ($q) {
                $q->whereDate('tanggal', now()->toDateString());
            })
            ->first();

        if ($absensi) {
            // update jam pulang jika sudah absen pulang
            $absensi->update([
                'jam_pulang' => now()->toTimeString(),
                'latitude_pulang' => $request->latitude,
                'longitude_pulang' => $request->longitude,
            ]);

            return ApiResponse::success($absensi->load('rencanaAbsensi'), 'Absensi pulang berhasil');
        }

        $newAbsensi = Absensi::create([
            'siswa_id' => $user->siswa->siswa_id,
            'rensi_id' => $rencana->rensi_id,
            'jam_datang' => null,
            'jam_pulang' => now()->toTimeString(),
            'latitude_pulang' => $request->latitude,
            'longitude_pulang' => $request->longitude,
            'status' => 'hadir',
            'is_verif' => false,
        ]);

        return ApiResponse::success($newAbsensi->load('rencanaAbsensi'), 'Absensi pulang berhasil (otomatis dibuat)');
    }


    public function izinSakit(IzinSakitRequest $request)
    {
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
