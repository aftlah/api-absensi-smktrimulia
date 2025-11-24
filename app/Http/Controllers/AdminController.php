<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Absensi;
use App\Models\Jurusan;
use App\Models\Pengaturan;
use App\Models\Kelas;
use App\Models\WaliKelas;
use App\Models\Akun;
use Illuminate\Validation\Rule;
use App\Models\GuruPiket;
use Illuminate\Support\Facades\Hash;
use App\Models\JadwalPiket;
use Illuminate\Support\Facades\Validator;
use App\Models\Admin as AdminModel;
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


    // jurusan
    public function getJurusan(Request $request)
    {
        $jurusan = Jurusan::all();
        return response()->json([
            'jurusan' => $jurusan,
        ]);
    }

    public function createJurusan(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nama_jurusan' => 'required|string|max:255|unique:jurusan,nama_jurusan',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validasi gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        $jurusan = Jurusan::create([
            'nama_jurusan' => $request->input('nama_jurusan'),
        ]);

        return response()->json([
            'message' => 'Jurusan berhasil ditambahkan',
            'jurusan' => $jurusan,
        ], 201);
    }

    public function updateJurusan(Request $request, $jurusan)
    {
        $jur = Jurusan::findOrFail($jurusan);
        $validator = Validator::make($request->all(), [
            'nama_jurusan' => 'required|string|max:255|unique:jurusan,nama_jurusan,' . $jur->jurusan_id . ',jurusan_id',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validasi gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        $jur->nama_jurusan = $request->input('nama_jurusan');
        $jur->save();

        return response()->json([
            'message' => 'Jurusan berhasil diperbarui',
            'jurusan' => $jur,
        ]);
    }

    public function deleteJurusan($jurusan)
    {
        $jur = Jurusan::findOrFail($jurusan);
        $jur->delete();
        return response()->json([
            'message' => 'Jurusan berhasil dihapus',
        ]);
    }

    /**
     * KELAS CRUD
     */
    public function getKelas(Request $request)
    {
        $kelas = Kelas::with(['jurusan', 'walas'])->get();
        return response()->json([
            'kelas' => $kelas,
        ]);
    }

    public function createKelas(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tingkat' => 'required|in:X,XI,XII',
            'paralel' => 'nullable|string|size:1',
            'thn_ajaran' => ['required','string','size:9','regex:/^\d{4}\/\d{4}$/'],
            'jurusan_id' => 'required|exists:jurusan,jurusan_id',
            'walas_id' => 'required|exists:wali_kelas,walas_id',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validasi gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        $kelas = Kelas::create([
            'tingkat' => $request->input('tingkat'),
            'paralel' => $request->input('paralel'),
            'thn_ajaran' => $request->input('thn_ajaran'),
            'jurusan_id' => $request->input('jurusan_id'),
            'walas_id' => $request->input('walas_id'),
        ]);

        $kelas->load(['jurusan','walas']);
        return response()->json([
            'message' => 'Kelas berhasil ditambahkan',
            'kelas' => $kelas,
        ], 201);
    }

    public function updateKelas(Request $request, $kelas)
    {
        $kls = Kelas::findOrFail($kelas);
        $validator = Validator::make($request->all(), [
            'tingkat' => 'required|in:X,XI,XII',
            'paralel' => 'nullable|string|size:1',
            'thn_ajaran' => ['required','string','size:9','regex:/^\d{4}\/\d{4}$/'],
            'jurusan_id' => 'required|exists:jurusan,jurusan_id',
            'walas_id' => 'required|exists:wali_kelas,walas_id',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validasi gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        $kls->tingkat = $request->input('tingkat');
        $kls->paralel = $request->input('paralel');
        $kls->thn_ajaran = $request->input('thn_ajaran');
        $kls->jurusan_id = $request->input('jurusan_id');
        $kls->walas_id = $request->input('walas_id');
        $kls->save();

        $kls->load(['jurusan','walas']);
        return response()->json([
            'message' => 'Kelas berhasil diperbarui',
            'kelas' => $kls,
        ]);
    }

    public function deleteKelas($kelas)
    {
        $kls = Kelas::findOrFail($kelas);
        $kls->delete();
        return response()->json([
            'message' => 'Kelas berhasil dihapus',
        ]);
    }

    public function getWalas(Request $request)
    {
        $walas = WaliKelas::select('walas_id','nip','nama')->get();
        return response()->json([
            'walas' => $walas,
        ]);
    }

    /**
     * WALIKELAS CRUD
     */
    public function getWaliKelas(Request $request)
    {
        $walas = WaliKelas::with('akun')->get();
        return response()->json([
            'walas' => $walas,
        ]);
    }

    public function createWaliKelas(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nip' => 'required|string|max:18|unique:wali_kelas,nip',
            'nama' => 'required|string|max:255',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validasi gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        $nip = $request->input('nip');
        $nama = $request->input('nama');

        // Buat atau gunakan akun dengan username = NIP, password default TRI12345, role walas
        $akun = Akun::firstOrCreate(
            ['username' => $nip],
            ['password' => Hash::make('TRI12345'), 'role' => 'walas']
        );
        if ($akun->role !== 'walas') {
            $akun->role = 'walas';
            $akun->save();
        }

        $walas = WaliKelas::create([
            'nip' => $nip,
            'nama' => $nama,
            'akun_id' => $akun->akun_id,
        ]);

        $walas->load('akun');
        return response()->json([
            'message' => 'Wali kelas berhasil ditambahkan',
            'walas' => $walas,
        ], 201);
    }

    public function updateWaliKelas(Request $request, $walas)
    {
        $wk = WaliKelas::findOrFail($walas);
        $validator = Validator::make($request->all(), [
            'nip' => 'required|string|max:18|unique:wali_kelas,nip,' . $wk->walas_id . ',walas_id',
            'nama' => 'required|string|max:255',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validasi gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        $wk->nip = $request->input('nip');
        $wk->nama = $request->input('nama');
        $wk->save();

        // Sinkronkan username akun dengan NIP baru
        if ($wk->akun_id) {
            $akun = Akun::find($wk->akun_id);
            if ($akun) {
                $akun->username = $wk->nip;
                $akun->role = 'walas';
                $akun->save();
            }
        }

        $wk->load('akun');
        return response()->json([
            'message' => 'Wali kelas berhasil diperbarui',
            'walas' => $wk,
        ]);
    }

    public function deleteWaliKelas($walas)
    {
        $wk = WaliKelas::findOrFail($walas);
        $wk->delete();
        return response()->json([
            'message' => 'Wali kelas berhasil dihapus',
        ]);
    }

    public function getAkunWalas(Request $request)
    {
        $akun = Akun::where('role', 'walas')->select('akun_id','username','role')->get();
        return response()->json([
            'akun' => $akun,
        ]);
    }

    /**
     * GURU PIKET CRUD
     */
    public function getGuruPiket(Request $request)
    {
        $gurket = GuruPiket::with('akun')->get();
        return response()->json([
            'gurket' => $gurket,
        ]);
    }

    public function createGuruPiket(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nip' => 'required|string|max:18|unique:guru_piket,nip',
            'nama' => 'required|string|max:255',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validasi gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        $nip = $request->input('nip');
        $nama = $request->input('nama');

        // Buat atau gunakan akun dengan username = NIP, password default TRI12345, role gurket
        $akun = Akun::firstOrCreate(
            ['username' => $nip],
            ['password' => Hash::make('TRI12345'), 'role' => 'gurket']
        );
        if ($akun->role !== 'gurket') {
            $akun->role = 'gurket';
            $akun->save();
        }

        $gp = GuruPiket::create([
            'nip' => $nip,
            'nama' => $nama,
            'akun_id' => $akun->akun_id,
        ]);

        $gp->load('akun');
        return response()->json([
            'message' => 'Guru piket berhasil ditambahkan',
            'gurket' => $gp,
        ], 201);
    }

    public function updateGuruPiket(Request $request, $gurket)
    {
        $gp = GuruPiket::findOrFail($gurket);
        $validator = Validator::make($request->all(), [
            'nip' => 'required|string|max:18|unique:guru_piket,nip,' . $gp->gurket_id . ',gurket_id',
            'nama' => 'required|string|max:255',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validasi gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        $gp->nip = $request->input('nip');
        $gp->nama = $request->input('nama');
        $gp->save();

        // Sinkronkan username akun dengan NIP baru
        if ($gp->akun_id) {
            $akun = Akun::find($gp->akun_id);
            if ($akun) {
                $akun->username = $gp->nip;
                $akun->role = 'gurket';
                $akun->save();
            }
        }

        $gp->load('akun');
        return response()->json([
            'message' => 'Guru piket berhasil diperbarui',
            'gurket' => $gp,
        ]);
    }

    public function deleteGuruPiket($gurket)
    {
        $gp = GuruPiket::findOrFail($gurket);
        $gp->delete();
        return response()->json([
            'message' => 'Guru piket berhasil dihapus',
        ]);
    }

    public function getAkunGuruPiket(Request $request)
    {
        $akun = Akun::where('role', 'gurket')->select('akun_id','username','role')->get();
        return response()->json([
            'akun' => $akun,
        ]);
    }

    public function updateAdminProfile(Request $request)
    {
        $user = \Illuminate\Support\Facades\Auth::user();
        $validator = Validator::make($request->all(), [
            'nama' => 'required|string|max:255',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validasi gagal',
                'errors' => $validator->errors(),
            ], 422);
        }
        $admin = AdminModel::where('akun_id', $user->akun_id)->firstOrFail();
        $admin->nama = $request->input('nama');
        $admin->save();
        return response()->json([
            'message' => 'Profil admin berhasil diperbarui',
            'admin' => $admin,
        ]);
    }

    /**
     * JADWAL PIKET CRUD
     */
    public function getJadwalPiket(Request $request)
    {
        $jadwal = JadwalPiket::with('guruPiket')->orderBy('tanggal', 'desc')->get();
        return response()->json([
            'jadwal' => $jadwal,
        ]);
    }

    public function createJadwalPiket(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tanggal' => 'required|date|unique:jadwal_piket,tanggal',
            'gurket_id' => 'required|exists:guru_piket,gurket_id',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validasi gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        $jp = JadwalPiket::create([
            'tanggal' => $request->input('tanggal'),
            'gurket_id' => $request->input('gurket_id'),
        ]);

        $jp->load('guruPiket');
        return response()->json([
            'message' => 'Jadwal piket berhasil ditambahkan',
            'jadwal' => $jp,
        ], 201);
    }

    public function updateJadwalPiket(Request $request, $jadwal)
    {
        $jp = JadwalPiket::findOrFail($jadwal);
        $validator = Validator::make($request->all(), [
            'tanggal' => 'required|date|unique:jadwal_piket,tanggal,' . $jp->jad_piket_id . ',jad_piket_id',
            'gurket_id' => 'required|exists:guru_piket,gurket_id',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validasi gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        $jp->tanggal = $request->input('tanggal');
        $jp->gurket_id = $request->input('gurket_id');
        $jp->save();

        $jp->load('guruPiket');
        return response()->json([
            'message' => 'Jadwal piket berhasil diperbarui',
            'jadwal' => $jp,
        ]);
    }

    public function deleteJadwalPiket($jadwal)
    {
        $jp = JadwalPiket::findOrFail($jadwal);
        $jp->delete();
        return response()->json([
            'message' => 'Jadwal piket berhasil dihapus',
        ]);
    }
}