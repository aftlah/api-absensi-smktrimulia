<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Absensi;
use App\Models\Jurusan;
use App\Models\Pengaturan;
use App\Models\Kelas;
use App\Models\WaliKelas;
use App\Models\Akun;
use App\Models\GuruPiket;
use Illuminate\Support\Facades\Hash;
use App\Models\JadwalPiket;
use Illuminate\Support\Facades\Validator;
use App\Models\Admin as AdminModel;
use Carbon\Carbon;
use App\Models\Siswa;
use App\Helpers\ApiResponse;
use App\Helpers\ImportHelper;
use App\Http\Requests\UpdateSiswaRequest;
use App\Models\RiwayatKelas;
use Illuminate\Support\Facades\Auth;

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

        return ApiResponse::success([
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
            return ApiResponse::error('Pengaturan belum tersedia', null, 404);
        }

        return ApiResponse::success([
            'pengaturan_id' => $pengaturan->pengaturan_id,
            'latitude' => (float) $pengaturan->latitude,
            'longitude' => (float) $pengaturan->longitude,
            'radius_meter' => (int) $pengaturan->radius_meter,
            'jam_masuk' => $pengaturan->jam_masuk,
            'jam_pulang' => $pengaturan->jam_pulang,
            'toleransi_telat' => (int) $pengaturan->toleransi_telat,
        ], 'Pengaturan absensi berhasil diambil');
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
            return ApiResponse::error('Validasi gagal', $validator->errors(), 422);
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

        return ApiResponse::success(null, 'Pengaturan berhasil diperbarui');
    }


    // jurusan
    public function getJurusan(Request $request)
    {
        $jurusan = Jurusan::all();
        return ApiResponse::success([
            'jurusan' => $jurusan,
        ], 'Jurusan berhasil diambil');
    }

    public function createJurusan(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nama_jurusan' => 'required|string|max:255|unique:jurusan,nama_jurusan',
        ]);
        if ($validator->fails()) {
            return ApiResponse::error('Validasi gagal', $validator->errors(), 422);
        }

        $jurusan = Jurusan::create([
            'nama_jurusan' => $request->input('nama_jurusan'),
        ]);

        return ApiResponse::success([
            'jurusan' => $jurusan,
        ], 'Jurusan berhasil ditambahkan', 201);
    }

    public function updateJurusan(Request $request, $jurusan)
    {
        $jur = Jurusan::findOrFail($jurusan);
        $validator = Validator::make($request->all(), [
            'nama_jurusan' => 'required|string|max:255|unique:jurusan,nama_jurusan,' . $jur->jurusan_id . ',jurusan_id',
        ]);
        if ($validator->fails()) {
            return ApiResponse::error('Validasi gagal', $validator->errors(), 422);
        }

        $jur->nama_jurusan = $request->input('nama_jurusan');
        $jur->save();

        return ApiResponse::success([
            'jurusan' => $jur,
        ], 'Jurusan berhasil diperbarui');
    }

    public function deleteJurusan($jurusan)
    {
        $jur = Jurusan::findOrFail($jurusan);
        $jur->delete();
        return ApiResponse::success(null, 'Jurusan berhasil dihapus');
    }

    /**
     * KELAS CRUD
     */
    public function getKelas(Request $request)
    {
        $kelas = Kelas::with(['jurusan', 'walas'])->get();
        return ApiResponse::success([
            'kelas' => $kelas,
        ], 'Kelas berhasil diambil');
    }

    public function createKelas(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tingkat' => 'required|in:X,XI,XII',
            'paralel' => 'nullable|string|size:1',
            'jurusan_id' => 'required|exists:jurusan,jurusan_id',
            'walas_id' => 'required|exists:wali_kelas,walas_id',
        ]);
        if ($validator->fails()) {
            return ApiResponse::error('Validasi gagal', $validator->errors(), 422);
        }

        $kelas = Kelas::create([
            'tingkat' => $request->input('tingkat'),
            'paralel' => $request->input('paralel'),
            'jurusan_id' => $request->input('jurusan_id'),
            'walas_id' => $request->input('walas_id'),
        ]);

        $kelas->load(['jurusan', 'walas']);
        return ApiResponse::success([
            'kelas' => $kelas,
        ], 'Kelas berhasil ditambahkan', 201);
    }

    public function updateKelas(Request $request, $kelas)
    {
        $kls = Kelas::findOrFail($kelas);
        $validator = Validator::make($request->all(), [
            'tingkat' => 'required|in:X,XI,XII',
            'paralel' => 'nullable|string|size:1',
            'jurusan_id' => 'required|exists:jurusan,jurusan_id',
            'walas_id' => 'required|exists:wali_kelas,walas_id',
        ]);
        if ($validator->fails()) {
            return ApiResponse::error('Validasi gagal', $validator->errors(), 422);
        }

        $kls->tingkat = $request->input('tingkat');
        $kls->paralel = $request->input('paralel');
        $kls->jurusan_id = $request->input('jurusan_id');
        $kls->walas_id = $request->input('walas_id');
        $kls->save();

        $kls->load(['jurusan', 'walas']);
        return ApiResponse::success([
            'kelas' => $kls,
        ], 'Kelas berhasil diperbarui');
    }

    public function deleteKelas($kelas)
    {
        $kls = Kelas::findOrFail($kelas);
        $kls->delete();
        return ApiResponse::success(null, 'Kelas berhasil dihapus');
    }

    public function getWalas(Request $request)
    {
        $walas = WaliKelas::select('walas_id', 'nip', 'nama')->get();
        return ApiResponse::success([
            'walas' => $walas,
        ]);
    }

    /**
     * RIWAYAT KELAS SISWA
     */
    public function getRiwayatKelas(Request $request)
    {
        $kelasId = $request->query('kelas_id');
        $q = trim((string)$request->query('q', ''));

        $query = RiwayatKelas::with(['siswa.kelas.jurusan', 'kelas.jurusan'])
            ->when(!empty($kelasId), function ($qb) use ($kelasId) {
                $qb->where('kelas_id', $kelasId);
            })
            ->when(!empty($q), function ($qb) use ($q) {
                $qb->whereHas('siswa', function ($q2) use ($q) {
                    $q2->where('nis', 'like', "%$q%")
                        ->orWhere('nama', 'like', "%$q%");
                });
            })
            ->orderByDesc('created_at');

        $riwayat = $query->get()->map(function ($item) {
            $siswa = $item->siswa;
            $kelas = $item->kelas;
            $jur = $kelas?->jurusan;
            return [
                'riwayat_kelas_id' => $item->riwayat_kelas_id,
                'status' => $item->status,
                'created_at' => $item->created_at,
                'siswa' => $siswa ? [
                    'siswa_id' => $siswa->siswa_id,
                    'nis' => $siswa->nis,
                    'nama' => $siswa->nama,
                    'jenkel' => $siswa->jenkel,
                ] : null,
                'kelas' => $kelas ? [
                    'kelas_id' => $kelas->kelas_id,
                    'tingkat' => $kelas->tingkat,
                    'paralel' => $kelas->paralel,
                    'jurusan' => $jur ? [
                        'jurusan_id' => $jur->jurusan_id,
                        'nama_jurusan' => $jur->nama_jurusan,
                    ] : null,
                ] : null,
            ];
        });

        return ApiResponse::success([
            'riwayat' => $riwayat,
        ], 'Riwayat kelas berhasil diambil');
    }

    /**
     * WALIKELAS CRUD
     */
    public function getWaliKelas(Request $request)
    {
        $walas = WaliKelas::with('akun')->get();
        return ApiResponse::success([
            'walas' => $walas,
        ], 'Wali kelas berhasil diambil');
    }

    public function createWaliKelas(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nip' => 'required|string|max:18|unique:wali_kelas,nip',
            'nama' => 'required|string|max:255',
        ]);
        if ($validator->fails()) {
            return ApiResponse::error('Validasi gagal', $validator->errors(), 422);
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
        return ApiResponse::success([
            'walas' => $walas,
        ], 'Wali kelas berhasil ditambahkan', 201);
    }

    public function updateWaliKelas(Request $request, $walas)
    {
        $wk = WaliKelas::findOrFail($walas);
        $validator = Validator::make($request->all(), [
            'nip' => 'required|string|max:18|unique:wali_kelas,nip,' . $wk->walas_id . ',walas_id',
            'nama' => 'required|string|max:255',
        ]);
        if ($validator->fails()) {
            return ApiResponse::error('Validasi gagal', $validator->errors(), 422);
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
        return ApiResponse::success([
            'walas' => $wk,
        ], 'Wali kelas berhasil diperbarui');
    }

    public function deleteWaliKelas($walas)
    {
        $wk = WaliKelas::findOrFail($walas);
        $wk->delete();
        return ApiResponse::success(null, 'Wali kelas berhasil dihapus');
    }

    

    public function importSiswa(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv|max:10240'
        ]);

        try {
            $importer = new ImportHelper();
            $hasilImport = $importer->importSiswa($request->file('file'));

            return ApiResponse::success([
                'kelas_terdaftar' => $hasilImport['kelas_terdaftar'],
                'data_siswa' => $hasilImport['data_siswa'],
            ], 'Data siswa berhasil diimpor');
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Gagal mengimpor data siswa',
                $e->getMessage()
            );
        }
    }

    public function getDataSiswa()
    {
        $siswa = Siswa::with(['kelas', 'akun'])->get()->map(function ($item) {
            return [
                'siswa_id' => $item->siswa_id,
                'nis'      => $item->nis,
                'nama'     => $item->nama,
                'jenkel'   => $item->jenkel,

                'kelas'    => $item->kelas ? [
                    'kelas_id' => $item->kelas->kelas_id,
                    'tingkat'  => $item->kelas->tingkat,
                    'jurusan'  => $item->kelas->jurusan,
                    'paralel'  => $item->kelas->paralel,
                ] : null,

                'akun'     => $item->akun ? [
                    'user_id'  => $item->akun->user_id,
                    'username' => $item->akun->username,
                    'role'     => $item->akun->role,
                ] : null,
            ];
        });

        return ApiResponse::success($siswa, 'Data siswa berhasil diambil');
    }

    public function updateDataSiswa(UpdateSiswaRequest $request)
    {
        $siswa = Siswa::with(['akun', 'kelas'])->findOrFail($request->input('siswa_id'));

        $siswa->fill($request->only(['nis', 'nama', 'jenkel', 'kelas_id']))->save();

        if ($siswa->akun) {
            if ($request->has('username')) {
                $siswa->akun->username = $request->input('username');
            }
            if ($request->has('password')) {
                $siswa->akun->password = bcrypt($request->input('password'));
            }
            if ($request->has('role')) {
                $siswa->akun->role = $request->input('role');
            }
            $siswa->akun->save();
        }

        if ($siswa->kelas && $request->has('kelas')) {
            $siswa->kelas->fill($request->input('kelas'))->save();
        }

        $siswa->load(['kelas', 'akun']);

        return ApiResponse::success([
            'siswa' => $siswa,
        ], 'Data siswa berhasil diperbarui');
    }

    public function createDataSiswa(Request $request)
    {
        $request->validate([
            'nis' => 'required|string|unique:siswa,nis',
            'nama' => 'required|string',
            'jenkel' => 'required|in:L,P',
            'kelas_id' => 'required|exists:kelas,kelas_id',
        ]);

        $username = $request->input('nis');
        $akun = Akun::create([
            'username' => $username,
            'password' => bcrypt('TRI12345'),
            'role' => 'siswa',
        ]);

        $siswa = Siswa::create([
            'nis' => $request->input('nis'),
            'nama' => $request->input('nama'),
            'jenkel' => $request->input('jenkel'),
            'kelas_id' => $request->input('kelas_id'),
            'akun_id' => $akun->akun_id,
        ]);

        $siswa->load(['kelas', 'akun']);

        return ApiResponse::success($siswa, 'Siswa berhasil ditambahkan');
    }

    /**
     * GURU PIKET CRUD
     */
    public function getGuruPiket(Request $request)
    {
        $gurket = GuruPiket::with('akun')->get();
        return ApiResponse::success([
            'gurket' => $gurket,
        ], 'Data guru piket berhasil diambil');
    }

    public function createGuruPiket(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nip' => 'required|string|max:18|unique:guru_piket,nip',
            'nama' => 'required|string|max:255',
        ]);
        if ($validator->fails()) {
            return ApiResponse::error('Validasi gagal', $validator->errors(), 422);
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
        return ApiResponse::success([
            'gurket' => $gp,
        ], 'Guru piket berhasil ditambahkan', 201);
    }

    public function updateGuruPiket(Request $request, $gurket)
    {
        $gp = GuruPiket::findOrFail($gurket);
        $validator = Validator::make($request->all(), [
            'nip' => 'required|string|max:18|unique:guru_piket,nip,' . $gp->gurket_id . ',gurket_id',
            'nama' => 'required|string|max:255',
        ]);
        if ($validator->fails()) {
            return ApiResponse::error('Validasi gagal', $validator->errors(), 422);
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
        return ApiResponse::success([
            'gurket' => $gp,
        ], 'Guru piket berhasil diperbarui');
    }

    public function deleteGuruPiket($gurket)
    {
        $gp = GuruPiket::findOrFail($gurket);
        $gp->delete();
        return ApiResponse::success(null, 'Guru piket berhasil dihapus');
    }

    // public function getAkunGuruPiket(Request $request)
    // {
    //     $akun = Akun::where('role', 'gurket')->select('akun_id', 'username', 'role')->get();
    //     return ApiResponse::success([
    //         'akun' => $akun,
    //     ]);
    // }

    public function updateAdminProfile(Request $request)
    {
        $user = Auth::user();
        $validator = Validator::make($request->all(), [
            'nama' => 'required|string|max:255',
        ]);
        if ($validator->fails()) {
            return ApiResponse::error('Validasi gagal', $validator->errors(), 422);
        }
        $admin = AdminModel::where('akun_id', $user->akun_id)->firstOrFail();
        $admin->nama = $request->input('nama');
        $admin->save();
        return ApiResponse::success([
            'admin' => $admin,
        ], 'Profil admin berhasil diperbarui');
    }

    /**
     * JADWAL PIKET CRUD
     */
    public function getJadwalPiket(Request $request)
    {
        $jadwal = JadwalPiket::with('guruPiket')->orderBy('tanggal', 'desc')->get();
        return ApiResponse::success([
            'jadwal' => $jadwal,
        ], 'Data jadwal piket berhasil diambil');
    }

    public function createJadwalPiket(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tanggal' => 'required|date|unique:jadwal_piket,tanggal',
            'gurket_id' => 'required|exists:guru_piket,gurket_id',
        ]);
        if ($validator->fails()) {
            return ApiResponse::error('Validasi gagal', $validator->errors(), 422);
        }

        $jp = JadwalPiket::create([
            'tanggal' => $request->input('tanggal'),
            'gurket_id' => $request->input('gurket_id'),
        ]);

        $jp->load('guruPiket');
        return ApiResponse::success([
            'jadwal' => $jp,
        ], 'Jadwal piket berhasil ditambahkan', 201);
    }

    public function updateJadwalPiket(Request $request, $jadwal)
    {
        $jp = JadwalPiket::findOrFail($jadwal);
        $validator = Validator::make($request->all(), [
            'tanggal' => 'required|date|unique:jadwal_piket,tanggal,' . $jp->jad_piket_id . ',jad_piket_id',
            'gurket_id' => 'required|exists:guru_piket,gurket_id',
        ]);
        if ($validator->fails()) {
            return ApiResponse::error('Validasi gagal', $validator->errors(), 422);
        }

        $jp->tanggal = $request->input('tanggal');
        $jp->gurket_id = $request->input('gurket_id');
        $jp->save();

        $jp->load('guruPiket');
        return ApiResponse::success([
            'jadwal' => $jp,
        ], 'Jadwal piket berhasil diperbarui');
    }

    public function deleteJadwalPiket($jadwal)
    {
        $jp = JadwalPiket::findOrFail($jadwal);
        $jp->delete();
        return ApiResponse::success(null, 'Jadwal piket berhasil dihapus');
    }
}
