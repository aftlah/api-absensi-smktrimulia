<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Absensi;
use App\Models\Pengaturan;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class AdminController extends Controller
{
    /**
     * Rekap absensi global (semua siswa)
     */
    public function rekap(Request $request)
    {
        // Filter tanggal (default: hari ini)
        $tanggal = $request->input('tanggal', Carbon::today()->toDateString());

        // Ambil absensi dengan relasi rencanaAbsensi pada tanggal tertentu
        $absensi = Absensi::with(['siswa.kelas', 'rencanaAbsensi'])
            ->whereHas('rencanaAbsensi', function ($q) use ($tanggal) {
                $q->whereDate('tanggal', $tanggal);
            })
            ->get();

        // Kelompokkan berdasarkan kelas dan hitung ringkasan status
        $rekap = [];
        foreach ($absensi as $item) {
            $kelasNama = ($item->siswa && $item->siswa->kelas)
                ? ($item->siswa->kelas->tingkat . ' ' . ($item->siswa->kelas->jurusan->nama_jurusan ?? 'UMUM') . ' ' . ($item->siswa->kelas->paralel ?? ''))
                : 'Tanpa Kelas';

            if (!isset($rekap[$kelasNama])) {
                $rekap[$kelasNama] = [
                    'kelas' => $kelasNama,
                    'hadir' => 0,
                    'terlambat' => 0,
                    'izin' => 0,
                    'sakit' => 0,
                    'alfa' => 0,
                ];
            }

            $status = $item->status ?? 'alfa';
            if (!isset($rekap[$kelasNama][$status])) {
                // Pastikan hanya status yang dikenali yang dihitung
                $status = in_array($status, ['hadir', 'terlambat', 'izin', 'sakit']) ? $status : 'alfa';
            }

            $rekap[$kelasNama][$status] += 1;
        }

        return response()->json([
            'tanggal' => $tanggal,
            'rekap' => array_values($rekap),
        ]);
    }

    /**
     * Ambil pengaturan sistem (lokasi, radius, jam, toleransi)
     */
    public function getPengaturan(Request $request)
    {
        $pengaturan = Pengaturan::first();
        if (!$pengaturan) {
            return response()->json([
                'message' => 'Pengaturan belum tersedia'], 404);
        }

        return response()->json([
            'pengaturan_id' => $pengaturan->pengaturan_id,
            'latitude' => (float) $pengaturan->latitude,
            'longitude' => (float) $pengaturan->longitude,
            'radius_meter' => (int) $pengaturan->radius_meter,
            'jam_masuk' => $pengaturan->jam_masuk,
            'jam_pulang' => $pengaturan->jam_pulang,
            'toleransi_telat' => (int) $pengaturan->toleransi_telat,
        ]);
    }

    /**
     * Update pengaturan sistem
     */
    public function updatePengaturan(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'radius_meter' => 'required|integer|min:1',
            'jam_masuk' => 'required|date_format:H:i',
            'jam_pulang' => 'required|date_format:H:i|after_or_equal:jam_masuk',
            'toleransi_telat' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validasi gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        $pengaturan = Pengaturan::first();
        if (!$pengaturan) {
            $pengaturan = new Pengaturan();
        }

        // Simpan dengan format jam HH:MM:SS
        $pengaturan->latitude = $request->input('latitude');
        $pengaturan->longitude = $request->input('longitude');
        $pengaturan->radius_meter = $request->input('radius_meter');
        $pengaturan->jam_masuk = $request->input('jam_masuk') . ':00';
        $pengaturan->jam_pulang = $request->input('jam_pulang') . ':00';
        $pengaturan->toleransi_telat = $request->input('toleransi_telat');
        $pengaturan->save();

        return response()->json([
            'message' => 'Pengaturan berhasil diperbarui',
        ]);
    }
}