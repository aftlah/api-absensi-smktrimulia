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
    // $lat1, $lan1 = koordinat dari siswa
    // $lat2, $lon2 = koordinat dari sekolah
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

        $siswa = $user->siswa;
        $kelas = $siswa ? $siswa->kelas : null;
        if (!$kelas) {
            return ApiResponse::error('Kelas siswa belum diatur', null, 422);
        }

        if ($user->role !== 'siswa') {
            return ApiResponse::error('Hanya siswa yang bisa absen', null, 403);
        }

        $pengaturan = Pengaturan::first();
        if (!$pengaturan) {
            return ApiResponse::error('Pengaturan sekolah belum tersedia', null, 422);
        }

        // Validasi waktu jam masuk
        $currentTime = now();
        $jamMasukToday = \Carbon\Carbon::parse($pengaturan->jam_masuk)->setDate(
            $currentTime->year,
            $currentTime->month,
            $currentTime->day
        );

        // if ($currentTime->lt($jamMasukToday)) {
        //     return ApiResponse::error('Absensi datang hanya bisa dilakukan setelah jam ' . $jamMasukToday->format('H:i'), null, 422);
        // }

        // hasil meter dari jarak antara siswa dan sekolah
        $jarak = $this->hitungJarak(
            $request->latitude,
            $request->longitude,
            $pengaturan->latitude,
            $pengaturan->longitude
        );


        if ($jarak > $pengaturan->radius_meter) {
            return ApiResponse::error('Di luar radius absensi', [
                'distance' => round($jarak, 1),
                'radius' => (int) $pengaturan->radius_meter,
            ], 422);
        }

        $hariIni = now()->toDateString();
        $rencana = RencanaAbsensi::whereDate('tanggal', $hariIni)
            ->where('kelas_id', $kelas->kelas_id)
            ->first();

        // dd($rencana);

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

        // Tentukan status (terlambat jika melebihi toleransi_telat dari jam_masuk)
        $status = 'hadir';
        $toleransiMenit = (int) ($pengaturan->toleransi_telat ?? 0);
        $batasTerlambat = (clone $jamMasukToday)->addMinutes($toleransiMenit);
        if ($currentTime->gt($batasTerlambat)) {
            $status = 'terlambat';
        }

        $absensi = Absensi::create([
            'siswa_id'         => $user->siswa->siswa_id,
            'rensi_id'         => $rencana->rensi_id,
            'jam_datang'       => now()->toTimeString(),
            'latitude_datang'  => $request->latitude,
            'longitude_datang' => $request->longitude,
            'status'           => $status,
            'is_verif'         => 0,
        ]);

        return ApiResponse::success($absensi, 'Absensi berhasil');
    }


    // siswa absen pulang 
    public function absenPulang(AbsensiRequest $request)
    {
        $user = Auth::user();
        $siswa = $user->siswa;
        $kelas = $siswa ? $siswa->kelas : null;
        if (!$kelas) {
            return ApiResponse::error('Kelas siswa belum diatur', null, 422);
        }
        if ($user->role !== 'siswa') {
            return ApiResponse::error('Hanya siswa yang bisa absen', null, 403);
        }

        $pengaturan = Pengaturan::first();
        if (!$pengaturan) {
            return ApiResponse::error('Pengaturan sekolah belum tersedia', null, 422);
        }

        $currentTime = now();
        $jamPulangToday = \Carbon\Carbon::parse($pengaturan->jam_pulang)->setDate(
            $currentTime->year,
            $currentTime->month,
            $currentTime->day
        );

        if ($currentTime->lt($jamPulangToday)) {
            return ApiResponse::error('Absensi pulang hanya bisa dilakukan setelah jam ' . $jamPulangToday->format('H:i'), null, 422);
        }

        // Ambil pengaturan lokasi & radius 
        $jarak = $this->hitungJarak(
            $request->latitude,
            $request->longitude,
            $pengaturan->latitude,
            $pengaturan->longitude
        );

        if ($jarak > $pengaturan->radius_meter) {
            return ApiResponse::error('Di luar radius absensi', [
                'distance' => round($jarak, 1),
                'radius' => (int) $pengaturan->radius_meter,
            ], 422);
        }

        // Cari rencana absensi hari ini
        $rencana = RencanaAbsensi::whereDate('tanggal', now()->toDateString())
            ->where('kelas_id', $kelas->kelas_id)
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

        $siswa = $user->siswa;
        $kelas = $siswa ? $siswa->kelas : null;
        if (!$kelas) {
            return ApiResponse::error('Kelas siswa belum diatur', null, 422);
        }

        if ($user->role !== 'siswa') {
            return ApiResponse::error('Hanya siswa yang bisa mengajukan izin sakit', null, 403);
        }

        // Cari rencana absensi pada tanggal yang diminta
        $rencana = RencanaAbsensi::whereDate('tanggal', $request->tanggal)
            ->where('kelas_id', $kelas->kelas_id)
            ->first();

        if (!$rencana) {
            return ApiResponse::error('Tidak ada rencana absensi untuk tanggal tersebut', null, 422);
        }

        // Cek apakah sudah ada absensi untuk rencana ini
        $existingAbsensi = Absensi::where('siswa_id', $user->siswa->siswa_id)
            ->where('rensi_id', $rencana->rensi_id)
            ->first();

        if ($existingAbsensi) {
            return ApiResponse::error('Anda sudah memiliki catatan absensi pada tanggal tersebut', null, 422);
        }

        $path = null;
        if ($request->hasFile('bukti') && $request->file('bukti')->isValid()) {
            $path = $request->file('bukti')->store('izin_sakit', 'public');
        }

        $absensi = Absensi::create([
            'siswa_id'     => $user->siswa->siswa_id,
            'status'       => $request->jenis_absen,
            'rensi_id'     => $rencana->rensi_id,
            'keterangan'   => $request->keterangan,
            'bukti'        => $path,
        ]);

        $message = match ($request->jenis_absen) {
            'izin' => 'Izin berhasil diajukan',
            'sakit' => 'Sakit berhasil diajukan',
        };

        return ApiResponse::success($absensi, $message);
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
