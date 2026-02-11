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
use Illuminate\Support\Facades\DB;

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
        $absensi = Absensi::with(['siswa.riwayatKelas.kelas.jurusan', 'rencanaAbsensi'])
            ->whereHas('rencanaAbsensi', function ($q) use ($tanggal) {
                $q->whereDate('tanggal', $tanggal);
            })
            ->get();

        // Kelompokkan berdasarkan kelas dan hitung ringkasan status
        $rekap = [];
        foreach ($absensi as $item) {
            $riwayatAktif = $item->siswa
                ? $item->siswa->riwayatKelas->firstWhere('status', 'aktif') ?? $item->siswa->riwayatKelas->sortByDesc('created_at')->first()
                : null;

            $kelas = $riwayatAktif ? $riwayatAktif->kelas : null;

            $kelasNama = ($item->siswa && $kelas)
                ? ($kelas->tingkat . ' ' . ($kelas->jurusan->nama_jurusan ?? 'UMUM') . ' ' . ($kelas->paralel ?? ''))
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
            return ApiResponse::error('Nama jurusan sudah ada', $validator->errors(), 422);
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

        $walasId = $request->input('walas_id');
        $walasSudahPunyaKelas = Kelas::where('walas_id', $walasId)->exists();

        if ($walasSudahPunyaKelas) {
            return ApiResponse::error('Wali kelas ini sudah mengampu kelas lain dan hanya boleh memegang satu kelas.', null, 400);
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

        $walasIdBaru = $request->input('walas_id');
        $walasDipakaiKelasLain = Kelas::where('walas_id', $walasIdBaru)
            ->where('kelas_id', '!=', $kls->kelas_id)
            ->exists();

        if ($walasDipakaiKelasLain) {
            return ApiResponse::error('Wali kelas ini sudah mengampu kelas lain dan hanya boleh memegang satu kelas.', null, 400);
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
        $walas = WaliKelas::select('walas_id', 'username', 'nama')->get();
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
        $siswaId = $request->query('siswa_id');
        $q = trim((string)$request->query('q', ''));

        // Ensure all active students have riwayat_kelas records
        $this->ensureRiwayatKelasRecords();

        $query = RiwayatKelas::with(['siswa', 'kelas.jurusan'])
            ->when(!empty($kelasId), function ($qb) use ($kelasId) {
                $qb->where('kelas_id', $kelasId);
            })
            ->when(!empty($siswaId), function ($qb) use ($siswaId) {
                $qb->where('siswa_id', $siswaId);
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

    public function updateRiwayatKelas(Request $request, $id)
    {
        $riwayat = RiwayatKelas::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:aktif,naik kelas,tidak naik kelas,lulus,keluar,pindah',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validasi gagal', $validator->errors(), 422);
        }

        $riwayat->status = $request->input('status');
        $riwayat->save();

        return ApiResponse::success($riwayat, 'Status riwayat kelas berhasil diperbarui');
    }

    /**
     * Ensure all active students have riwayat_kelas records
     */
    private function ensureRiwayatKelasRecords()
    {
        return;
    }

    /**
     * PROMOTE CLASS - Naik Tingkat Kelas
     */
    public function promoteClass(Request $request, $kelasId)
    {
        try {
            // Validate request
            $validator = Validator::make($request->all(), [
                'next_tingkat' => 'required|string|in:XI,XII'
            ]);

            if ($validator->fails()) {
                return ApiResponse::error('Validation failed', $validator->errors(), 422);
            }

            $nextTingkat = $request->input('next_tingkat');

            // Find source class
            $sourceClass = Kelas::with(['jurusan', 'siswa'])->find($kelasId);
            if (!$sourceClass) {
                return ApiResponse::error('Kelas tidak ditemukan', null, 404);
            }

            // Validate current tingkat
            if (!in_array($sourceClass->tingkat, ['X', 'XI'])) {
                return ApiResponse::error('Hanya kelas X dan XI yang dapat naik tingkat', null, 400);
            }

            // Validate next tingkat logic
            if (($sourceClass->tingkat === 'X' && $nextTingkat !== 'XI') ||
                ($sourceClass->tingkat === 'XI' && $nextTingkat !== 'XII')
            ) {
                return ApiResponse::error('Tingkat tujuan tidak valid', null, 400);
            }

            // Check if students exist in the class
            $students = $sourceClass->siswa;
            if ($students->isEmpty()) {
                return ApiResponse::error('Tidak ada siswa dalam kelas ini', null, 400);
            }

            // Check if source class has a wali kelas assigned
            if (!$sourceClass->walas_id) {
                return ApiResponse::error('Kelas asal belum memiliki wali kelas. Silakan assign wali kelas terlebih dahulu.', null, 400);
            }

            // Find or create destination class
            $destinationClass = Kelas::where([
                'tingkat' => $nextTingkat,
                'jurusan_id' => $sourceClass->jurusan_id,
                'paralel' => $sourceClass->paralel
            ])->first();

            if (!$destinationClass) {
                // Create new class if it doesn't exist
                // Copy walas_id from source class (same teacher continues with promoted students)
                $destinationClass = Kelas::create([
                    'tingkat' => $nextTingkat,
                    'jurusan_id' => $sourceClass->jurusan_id,
                    'paralel' => $sourceClass->paralel,
                    'walas_id' => $sourceClass->walas_id
                ]);
            }

            // Start database transaction
            DB::beginTransaction();

            try {
                RiwayatKelas::where('kelas_id', $sourceClass->kelas_id)
                    ->where('status', 'aktif')
                    ->update(['status' => 'naik kelas']);

                foreach ($students as $student) {
                    RiwayatKelas::create([
                        'siswa_id' => $student->siswa_id,
                        'kelas_id' => $destinationClass->kelas_id,
                        'status' => 'aktif'
                    ]);
                }

                DB::commit();

                // Load updated data for response
                $sourceClass->load(['jurusan']);
                $destinationClass->load(['jurusan']);

                return ApiResponse::success([
                    'source_class' => [
                        'kelas_id' => $sourceClass->kelas_id,
                        'tingkat' => $sourceClass->tingkat,
                        'paralel' => $sourceClass->paralel,
                        'jurusan' => $sourceClass->jurusan->nama_jurusan ?? null,
                        'students_moved' => $students->count()
                    ],
                    'destination_class' => [
                        'kelas_id' => $destinationClass->kelas_id,
                        'tingkat' => $destinationClass->tingkat,
                        'paralel' => $destinationClass->paralel,
                        'jurusan' => $destinationClass->jurusan->nama_jurusan ?? null,
                        'walas_assigned' => $destinationClass->walas_id ? true : false
                    ]
                ], "Berhasil menaikkan tingkat {$students->count()} siswa dari kelas {$sourceClass->tingkat} ke {$destinationClass->tingkat}. Riwayat kelas lama dipertahankan dengan status 'naik kelas'.");
            } catch (\Exception $e) {
                DB::rollback();
                throw $e;
            }
        } catch (\Exception $e) {
            return ApiResponse::error('Terjadi kesalahan saat memproses kenaikan tingkat: ' . $e->getMessage(), null, 500);
        }
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
            'username' => 'required|string|max:50|unique:wali_kelas,username',
            'nama' => 'required|string|max:255',
        ]);
        if ($validator->fails()) {
            return ApiResponse::error('Username tersebut sudah terdaftar sebagai wali kelas', $validator->errors(), 422);
        }

        $username = $request->input('username');
        $nama = $request->input('nama');

        // Generate random password untuk keamanan
        $randomPassword = $this->generateRandomPassword();
        
        // Buat atau gunakan akun dengan username, password random, role walas
        $akun = Akun::firstOrCreate(
            ['username' => $username],
            ['password' => Hash::make($randomPassword), 'role' => 'walas']
        );
        if ($akun->role !== 'walas') {
            $akun->role = 'walas';
            $akun->save();
        }

        $walas = WaliKelas::create([
            'username' => $username,
            'nama' => $nama,
            'akun_id' => $akun->akun_id,
        ]);

        $walas->load('akun');
        return ApiResponse::success([
            'walas' => $walas,
            'password' => $randomPassword, // Return password untuk admin
        ], 'Wali kelas berhasil ditambahkan. Password: ' . $randomPassword, 201);
    }

    public function updateWaliKelas(Request $request, $walas)
    {
        $wk = WaliKelas::findOrFail($walas);
        $validator = Validator::make($request->all(), [
            'username' => 'required|string|max:50|unique:wali_kelas,username,' . $wk->walas_id . ',walas_id',
            'nama' => 'required|string|max:255',
        ]);
        if ($validator->fails()) {
            return ApiResponse::error('Validasi gagal', $validator->errors(), 422);
        }

        $wk->username = $request->input('username');
        $wk->nama = $request->input('nama');
        $wk->save();

        // Sinkronkan username akun dengan username baru
        if ($wk->akun_id) {
            $akun = Akun::find($wk->akun_id);
            if ($akun) {
                $akun->username = $wk->username;
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
        $kelasTerhubung = Kelas::where('walas_id', $wk->walas_id)->exists();

        if ($kelasTerhubung) {
            return ApiResponse::error('Gagal menghapus: wali kelas masih terhubung dengan kelas.', null, 400);
        }

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
                'nis_duplikat' => $hasilImport['nis_duplikat'] ?? [],
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
        $siswa = Siswa::with([
            'akun',
            'riwayatKelas' => function ($q) {
                $q->latest();
            },
            'riwayatKelas.kelas.jurusan'
        ])->get()->map(function ($item) {
            $status = 'aktif';
            if ($item->riwayatKelas->isNotEmpty()) {
                $currentRiwayat = $item->riwayatKelas->firstWhere('status', 'aktif')
                    ?? $item->riwayatKelas->first();

                $status = $currentRiwayat ? $currentRiwayat->status : $status;
            }

            $kelas = $item->kelas;

            return [
                'siswa_id' => $item->siswa_id,
                'nis'      => $item->nis,
                'nama'     => $item->nama,
                'jenkel'   => $item->jenkel,
                'status'   => $status,

                'kelas'    => $kelas ? [
                    'kelas_id' => $kelas->kelas_id,
                    'tingkat'  => $kelas->tingkat,
                    'paralel'  => $kelas->paralel,
                    'jurusan'  => $kelas->jurusan ? [
                        'jurusan_id' => $kelas->jurusan->jurusan_id,
                        'nama_jurusan' => $kelas->jurusan->nama_jurusan,
                    ] : null,
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
        $siswa = Siswa::with(['akun', 'riwayatKelas.kelas'])->findOrFail($request->input('siswa_id'));

        $siswa->fill($request->only(['nis', 'nama', 'jenkel']))->save();

        $activeRiwayat = $siswa->riwayatKelas->firstWhere('status', 'aktif')
            ?? $siswa->riwayatKelas->sortByDesc('created_at')->first();
        $oldKelasId = $activeRiwayat ? $activeRiwayat->kelas_id : null;

        // If kelas_id changed, update riwayat_kelas
        if ($request->has('kelas_id') && $oldKelasId != $request->input('kelas_id')) {
            RiwayatKelas::where('siswa_id', $siswa->siswa_id)
                ->where('status', 'aktif')
                ->update(['status' => 'naik kelas']);

            RiwayatKelas::create([
                'siswa_id' => $siswa->siswa_id,
                'kelas_id' => $request->input('kelas_id'),
                'status' => 'aktif'
            ]);
        }

        // Update status manual jika ada
        if ($request->has('status')) {
            $targetKelasId = $request->input('kelas_id', $oldKelasId);

            if ($targetKelasId) {
                RiwayatKelas::updateOrCreate(
                    [
                        'siswa_id' => $siswa->siswa_id,
                        'kelas_id' => $targetKelasId
                    ],
                    ['status' => $request->input('status')]
                );
            }
        }

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

        if ($request->has('kelas')) {
            $kelas = $siswa->kelas;
            if ($kelas) {
                $kelas->fill($request->input('kelas'))->save();
            }
        }

        $siswa->load(['akun', 'riwayatKelas.kelas']);

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

        // Generate random password untuk keamanan
        $randomPassword = $this->generateRandomPassword();
        
        $username = $request->input('nis');
        $akun = Akun::create([
            'username' => $username,
            'password' => bcrypt($randomPassword),
            'role' => 'siswa',
        ]);

        $siswa = Siswa::create([
            'nis' => $request->input('nis'),
            'nama' => $request->input('nama'),
            'jenkel' => $request->input('jenkel'),
            'akun_id' => $akun->akun_id,
        ]);

        // Create riwayat kelas with status "aktif" for new student
        RiwayatKelas::create([
            'siswa_id' => $siswa->siswa_id,
            'kelas_id' => $request->input('kelas_id'),
            'status' => 'aktif'
        ]);

        $siswa->load(['akun', 'riwayatKelas.kelas']);

        return ApiResponse::success([
            'siswa' => $siswa,
            'password' => $randomPassword, // Return password untuk admin
        ], 'Siswa berhasil ditambahkan. Password: ' . $randomPassword);
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
            'username' => 'required|string|max:50|unique:guru_piket,username',
            'nama' => 'required|string|max:255',
        ]);
        if ($validator->fails()) {
            return ApiResponse::error('Username tersebut sudah terdaftar sebagai guru piket', $validator->errors(), 422);
        }

        $username = $request->input('username');
        $nama = $request->input('nama');

        // Generate random password untuk keamanan
        $randomPassword = $this->generateRandomPassword();
        
        // Buat atau gunakan akun dengan username, password random, role gurket
        $akun = Akun::firstOrCreate(
            ['username' => $username],
            ['password' => Hash::make($randomPassword), 'role' => 'gurket']
        );
        if ($akun->role !== 'gurket') {
            $akun->role = 'gurket';
            $akun->save();
        }

        $gp = GuruPiket::create([
            'username' => $username,
            'nama' => $nama,
            'akun_id' => $akun->akun_id,
        ]);

        $gp->load('akun');
        return ApiResponse::success([
            'gurket' => $gp,
            'password' => $randomPassword, // Return password untuk admin
        ], 'Guru piket berhasil ditambahkan. Password: ' . $randomPassword, 201);
    }

    public function updateGuruPiket(Request $request, $gurket)
    {
        $gp = GuruPiket::findOrFail($gurket);
        $validator = Validator::make($request->all(), [
            'username' => 'required|string|max:50|unique:guru_piket,username,' . $gp->gurket_id . ',gurket_id',
            'nama' => 'required|string|max:255',
        ]);
        if ($validator->fails()) {
            return ApiResponse::error('Validasi gagal', $validator->errors(), 422);
        }

        $gp->username = $request->input('username');
        $gp->nama = $request->input('nama');
        $gp->save();

        // Sinkronkan username akun dengan username baru
        if ($gp->akun_id) {
            $akun = Akun::find($gp->akun_id);
            if ($akun) {
                $akun->username = $gp->username;
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
        $jadwalTerhubung = JadwalPiket::where('gurket_id', $gp->gurket_id)->exists();

        if ($jadwalTerhubung) {
            return ApiResponse::error('Gagal menghapus guru piket karena masih terhubung dengan jadwal piket.', null, 400);
        }

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

    /**
     * Bulk create jadwal piket untuk efisiensi
     */
    public function bulkCreateJadwalPiket(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'jadwal' => 'required|array',
            'jadwal.*.tanggal' => 'required|date',
            'jadwal.*.gurket_id' => 'required|exists:guru_piket,gurket_id',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validasi gagal', $validator->errors(), 422);
        }

        try {
            DB::beginTransaction();

            $jadwalData = $request->input('jadwal');
            $createdCount = 0;
            $skippedCount = 0;
            $errors = [];

            // Get existing dates untuk skip duplicate
            $existingDates = JadwalPiket::pluck('tanggal')->toArray();

            foreach ($jadwalData as $item) {
                // Skip jika tanggal sudah ada
                if (in_array($item['tanggal'], $existingDates)) {
                    $skippedCount++;
                    continue;
                }

                try {
                    JadwalPiket::create([
                        'tanggal' => $item['tanggal'],
                        'gurket_id' => $item['gurket_id'],
                    ]);
                    $createdCount++;
                    $existingDates[] = $item['tanggal']; // Add to existing untuk prevent duplicate dalam batch
                } catch (\Exception $e) {
                    $skippedCount++;
                    $errors[] = "Gagal membuat jadwal untuk {$item['tanggal']}: " . $e->getMessage();
                }
            }

            DB::commit();

            return ApiResponse::success([
                'created' => $createdCount,
                'skipped' => $skippedCount,
                'errors' => $errors,
            ], "Berhasil membuat {$createdCount} jadwal piket. {$skippedCount} dilewati.", 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Gagal membuat jadwal piket: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Delete all jadwal piket data
     */
    public function deleteAllJadwalPiket()
    {
        try {
            DB::beginTransaction();
            
            // Hapus semua jadwal piket
            JadwalPiket::query()->delete();
            
            DB::commit();
            return ApiResponse::success(null, 'Semua jadwal piket berhasil dihapus');
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Gagal menghapus semua jadwal piket: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Generate random password untuk keamanan
     */
    private function generateRandomPassword($length = 12)
    {
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
        $password = '';
        
        // Pastikan ada minimal 1 huruf kecil, 1 huruf besar, 1 angka, 1 simbol
        $password .= chr(rand(97, 122)); // huruf kecil
        $password .= chr(rand(65, 90));  // huruf besar
        $password .= chr(rand(48, 57));  // angka
        $password .= '!@#$%^&*'[rand(0, 7)]; // simbol
        
        // Isi sisa karakter secara random
        for ($i = 4; $i < $length; $i++) {
            $password .= $characters[rand(0, strlen($characters) - 1)];
        }
        
        // Acak urutan karakter
        return str_shuffle($password);
    }

    /**
     * Delete siswa data
     */
    public function deleteDataSiswa($siswaId)
    {
        try {
            $siswa = Siswa::findOrFail($siswaId);
            
            // Hapus riwayat kelas terkait
            RiwayatKelas::where('siswa_id', $siswa->siswa_id)->delete();
            
            // Hapus absensi terkait
            Absensi::where('siswa_id', $siswa->siswa_id)->delete();
            
            // Hapus akun terkait jika ada
            if ($siswa->akun_id) {
                Akun::where('akun_id', $siswa->akun_id)->delete();
            }
            
            // Hapus siswa
            $siswa->delete();
            
            return ApiResponse::success(null, 'Siswa berhasil dihapus');
        } catch (\Exception $e) {
            return ApiResponse::error('Gagal menghapus siswa: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Delete all siswa data
     */
    public function deleteAllSiswa()
    {
        try {
            DB::beginTransaction();
            
            // Hapus dalam urutan yang benar untuk menghindari foreign key constraint
            // 1. Hapus semua absensi terlebih dahulu
            Absensi::query()->delete();
            
            // 2. Hapus semua riwayat kelas
            RiwayatKelas::query()->delete();
            
            // 3. Hapus semua akun siswa
            Akun::where('role', 'siswa')->delete();
            
            // 4. Hapus semua siswa
            Siswa::query()->delete();
            
            DB::commit();
            
            return ApiResponse::success(null, 'Semua data siswa berhasil dihapus');
        } catch (\Exception $e) {
            DB::rollback();
            return ApiResponse::error('Gagal menghapus semua siswa: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Delete all jurusan data
     */
    public function deleteAllJurusan()
    {
        try {
            DB::beginTransaction();
            
            // Hapus semua kelas yang terkait dengan jurusan
            Kelas::query()->delete();
            
            // Hapus semua riwayat kelas
            RiwayatKelas::query()->delete();
            
            // Hapus semua jurusan
            Jurusan::query()->delete();
            
            DB::commit();
            
            return ApiResponse::success(null, 'Semua data jurusan berhasil dihapus');
        } catch (\Exception $e) {
            DB::rollback();
            return ApiResponse::error('Gagal menghapus semua jurusan: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Delete all kelas data
     */
    public function deleteAllKelas()
    {
        try {
            DB::beginTransaction();
            
            // Hapus semua riwayat kelas
            RiwayatKelas::query()->delete();
            
            // Hapus semua kelas
            Kelas::query()->delete();
            
            DB::commit();
            
            return ApiResponse::success(null, 'Semua data kelas berhasil dihapus');
        } catch (\Exception $e) {
            DB::rollback();
            return ApiResponse::error('Gagal menghapus semua kelas: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Delete all wali kelas data
     */
    public function deleteAllWaliKelas()
    {
        try {
            DB::beginTransaction();
            
            // Update kelas yang memiliki wali kelas menjadi null
            Kelas::query()->update(['walas_id' => null]);
            
            // Hapus semua akun wali kelas
            $walasIds = WaliKelas::pluck('akun_id');
            Akun::whereIn('akun_id', $walasIds)->delete();
            
            // Hapus semua wali kelas
            WaliKelas::query()->delete();
            
            DB::commit();
            
            return ApiResponse::success(null, 'Semua data wali kelas berhasil dihapus');
        } catch (\Exception $e) {
            DB::rollback();
            return ApiResponse::error('Gagal menghapus semua wali kelas: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Delete all guru piket data
     */
    public function deleteAllGuruPiket()
    {
        try {
            DB::beginTransaction();
            
            // Hapus semua jadwal piket
            JadwalPiket::query()->delete();
            
            // Hapus semua akun guru piket
            $gurketIds = GuruPiket::pluck('akun_id');
            Akun::whereIn('akun_id', $gurketIds)->delete();
            
            // Hapus semua guru piket
            GuruPiket::query()->delete();
            
            DB::commit();
            
            return ApiResponse::success(null, 'Semua data guru piket berhasil dihapus');
        } catch (\Exception $e) {
            DB::rollback();
            return ApiResponse::error('Gagal menghapus semua guru piket: ' . $e->getMessage(), null, 500);
        }
    }
}
