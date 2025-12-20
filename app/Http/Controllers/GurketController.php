<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Helpers\ImportHelper;
use App\Http\Requests\RencanaAbsensiRequest;
use App\Http\Requests\UpdateSiswaRequest;
use Illuminate\Http\Request;
use App\Models\Absensi;
use App\Models\Kelas;
use App\Models\RencanaAbsensi;
use App\Models\Siswa;
use App\Models\Akun;
use App\Models\WaliKelas;
use App\Models\AktivitasTerbaru;
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
        ], 'Laporan absensi berhasil diambil');
    }




    public function getSiswaIzinSakit()
    {
        $siswaIzinSakit = Absensi::with('siswa')
            ->whereIn('status', ['izin', 'sakit'])
            ->get();

        return ApiResponse::success([
            'siswa_izin_sakit' => $siswaIzinSakit,
        ], 'Data absensi izin sakit berhasil diambil');
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
            'absensi' => $absensi,
        ], 'Status absensi berhasil diperbarui');
    }

    public function updateAbsensiStatus(Request $request)
    {
        try {
            $request->validate([
                'absensi_id' => 'required|exists:absensi,absensi_id',
                'status' => 'required|in:hadir,terlambat,izin,sakit,alfa',
                'keterangan' => 'nullable|string',
            ]);

            $absensi = Absensi::with('siswa')->findOrFail($request->input('absensi_id'));
            $oldStatus = $absensi->status;
            
            $absensi->status = $request->input('status');
            if ($request->filled('keterangan')) {
                $absensi->keterangan = $request->input('keterangan');
            }
            $absensi->save();

            // Log aktivitas (optional - won't fail if error)
            try {
                $user = Auth::user();
                $siswa = $absensi->siswa;
                
                if ($siswa && $user) {
                    AktivitasTerbaru::create([
                        'akun_id' => $user->akun_id,
                        'tabel' => 'absensi',
                        'aksi' => 'updated',
                        'deskripsi' => "Status absensi siswa {$siswa->nama} (NIS: {$siswa->nis}) diubah dari '{$oldStatus}' menjadi '{$absensi->status}'",
                        'user' => $user->nama ?? $user->username ?? 'Unknown',
                        'role' => $user->role ?? 'unknown'
                    ]);
                }
            } catch (\Exception $e) {
                // Log error but don't fail the main operation
                \Log::error('Failed to log activity: ' . $e->getMessage());
            }

            return ApiResponse::success([
                'absensi' => $absensi->load('siswa'),
            ], 'Status absensi berhasil diperbarui');
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ApiResponse::error('Validation failed', $e->errors(), 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::error('Absensi not found', null, 404);
        } catch (\Exception $e) {
            \Log::error('Error updating absensi status: ' . $e->getMessage());
            return ApiResponse::error('Failed to update status', null, 500);
        }
    }
    public function getAbsensiSiswaHariIni()
    {
        $hariIni = Carbon::today()->toDateString();

        $absensi = Absensi::with([
            'siswa.kelas',
            'rencanaAbsensi'
        ])
            ->whereHas('rencanaAbsensi', function ($query) use ($hariIni) {
                $query->whereDate('tanggal', $hariIni);
            })
            ->get();

        return ApiResponse::success([
            'absensi' => $absensi,
        ], 'Absensi siswa hari ini berhasil diambil');
    }


    public function showAbsensiSiswa(Request $request)
    {
        $user = Auth::user();
        $kelasId = $request->query('kelas_id');
        $tanggal = $request->query('tanggal');
        $status = $request->query('status');

        $tanggal = $tanggal
            ? Carbon::parse($tanggal)->toDateString()
            : Carbon::today()->toDateString();

        // Query absensi dengan relasi siswa, kelas, dan rencana_absensi
        $query = Absensi::with(['siswa.kelas', 'rencanaAbsensi'])
            ->whereHas('rencanaAbsensi', function ($q) use ($tanggal) {
                $q->whereDate('tanggal', $tanggal);
            });

        // Bila role walas, force filter by kelas yang diampu
        if ($user && $user->role === 'walas') {
            $walas = WaliKelas::with('kelas')->where('akun_id', $user->akun_id)->first();
            if ($walas && $walas->kelas) {
                $kelasId = $walas->kelas->kelas_id; // override agar tidak bisa akses kelas lain
                $query->whereHas('siswa.kelas', function ($q) use ($kelasId) {
                    $q->where('kelas_id', $kelasId);
                });
            }
        } elseif (!empty($kelasId)) {
            // Untuk gurket: boleh filter kelas bebas
            $query->whereHas('siswa.kelas', function ($q) use ($kelasId) {
                $q->where('kelas_id', $kelasId);
            });
        }

        // Filter status bila disediakan
        if (!empty($status)) {
            $allowed = ['hadir', 'terlambat', 'izin', 'sakit', 'alfa'];
            if (in_array(strtolower($status), $allowed)) {
                $query->where('status', strtolower($status));
            }
        }

        // Ambil data dan format hasilnya
        $absensi = $query->get()->map(function ($item) {
            return [
                // --- Data siswa ---
                'siswa_id'   => $item->siswa->siswa_id ?? null,
                'nis'        => $item->siswa->nis ?? null,
                'nama'       => $item->siswa->nama ?? null,
                'jenkel'     => $item->siswa->jenkel ?? null,

                // --- Data kelas ---
                'kelas_id'   => $item->siswa->kelas->kelas_id ?? null,
                'tingkat'    => $item->siswa->kelas->tingkat ?? null,
                'jurusan'    => $item->siswa->kelas->jurusan ?? null,
                'paralel'    => $item->siswa->kelas->paralel ?? null,

                // --- Data absensi ---
                'absensi_id' => $item->absensi_id,
                'jenis_absen' => $item->jenis_absen ?? null,
                'tanggal'    => $item->rencanaAbsensi->tanggal ?? null,
                'jam_datang' => $item->jam_datang,
                'jam_pulang' => $item->jam_pulang,
                'status'     => $item->status,
                'keterangan' => $item->keterangan ?? null,
                'latitude_datang' => $item->latitude_datang ?? null,
                'longitude_datang' => $item->longitude_datang ?? null,
                'latitude_pulang' => $item->latitude_pulang ?? null,
                'longitude_pulang' => $item->longitude_pulang ?? null,
                'bukti'      => $item->bukti,
                'created_at' => $item->created_at,
                'updated_at' => $item->updated_at,
            ];
        });

        return ApiResponse::success([
            'filter' => [
                'kelas_id' => $kelasId ?? 'Semua',
                'tanggal' => $tanggal,
                'status' => $status ?? 'Semua',
            ],
            'absensi' => $absensi,
        ], 'Data absensi berhasil diambil');
    }


    public function getRencanaAbsensi()
    {
        $rencanaAbsensi = RencanaAbsensi::with(['kelas.jurusan'])
            ->whereDate('tanggal', '>=', Carbon::today()->toDateString())
            ->get()
            ->map(function ($item) {
                return [
                    // 'rencana_id' => $item->rencana_id,
                    'rencana_id' => $item->rensi_id,
                    'tanggal' => $item->tanggal,
                    'thn_ajaran' => $item->thn_ajaran,
                    'status_hari' => $item->status_hari,
                    'keterangan' => $item->keterangan,
                    'kelas' => $item->kelas ? [
                        'kelas_id' => $item->kelas->kelas_id,
                        'tingkat' => $item->kelas->tingkat,
                        'paralel' => $item->kelas->paralel,
                        'jurusan' => $item->kelas->jurusan ? [
                            'jurusan_id' => $item->kelas->jurusan->jurusan_id,
                            'nama_jurusan' => $item->kelas->jurusan->nama_jurusan,
                        ] : null,
                    ] : null,
                ];
            });

        return ApiResponse::success($rencanaAbsensi, 'Data rencana absensi berhasil diambil');
    }

    public function tambahRencanaAbsensi(RencanaAbsensiRequest $request)
    {
        $mode = $request->input('mode', 'single');
        $tanggalMulai = Carbon::parse($request->input('tanggal'));
        $keterangan = $request->input('keterangan');

        if ($mode === 'week') {
            $kelasAll = Kelas::select('kelas_id')->get();
            for ($i = 0; $i < 30; $i++) {
                $tanggal = $tanggalMulai->copy()->addDays($i);
                $dayOfWeek = $tanggal->dayOfWeek;
                $statusHari = ($dayOfWeek === Carbon::SATURDAY || $dayOfWeek === Carbon::SUNDAY)
                    ? 'libur'
                    : 'normal';
                $month = (int)$tanggal->format('n');
                $year = (int)$tanggal->format('Y');
                $startYear = $month >= 7 ? $year : ($year - 1);
                $endYear = $startYear + 1;
                $thnAjar = sprintf('%d%d', $startYear, $endYear);
                foreach ($kelasAll as $kelas) {
                    RencanaAbsensi::updateOrCreate(
                        [
                            'kelas_id' => $kelas->kelas_id,
                            'tanggal' => $tanggal->toDateString(),
                        ],
                        [
                            'status_hari' => $statusHari,
                            'keterangan' => $keterangan,
                            'thn_ajaran' => $thnAjar,
                        ]
                    );
                }
            }

            return ApiResponse::success(null, 'Rencana absensi 30 hari untuk semua kelas berhasil dibuat');
        }

        $kelasAll = Kelas::select('kelas_id')->get();
        foreach ($kelasAll as $kelas) {
            $month = (int)$tanggalMulai->format('n');
            $year = (int)$tanggalMulai->format('Y');
            $startYear = $month >= 7 ? $year : ($year - 1);
            $endYear = $startYear + 1;
            $thnAjar = sprintf('%d%d', $startYear, $endYear);
            RencanaAbsensi::updateOrCreate(
                [
                    'kelas_id' => $kelas->kelas_id,
                    'tanggal' => $tanggalMulai->toDateString(),
                ],
                [
                    'status_hari' => 'normal',
                    'keterangan' => $keterangan,
                    'thn_ajaran' => $thnAjar,
                ]
            );
        }

        return ApiResponse::success(null, 'Rencana absensi harian untuk semua kelas berhasil dibuat');
    }

    public function updateRencanaStatusHari(Request $request)
    {
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'tanggal' => 'required|date',
            'status_hari' => 'required|in:normal,libur,acara khusus',
            'keterangan' => 'nullable|string',
        ]);
        if ($validator->fails()) {
            return ApiResponse::error('Validasi gagal', $validator->errors(), 422);
        }

        $tanggal = Carbon::parse($request->input('tanggal'))->toDateString();
        $status = $request->input('status_hari');
        $ket = $request->input('keterangan');

        $update = ['status_hari' => $status];
        if ($request->has('keterangan')) {
            $update['keterangan'] = $ket;
        }
        RencanaAbsensi::whereDate('tanggal', $tanggal)->update($update);

        return ApiResponse::success(null, 'Status hari rencana absensi berhasil diperbarui');
    }

    /**
     * Info wali kelas: kelas yang diampu oleh akun walas saat ini
     */
    public function walasInfo()
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'walas') {
            return ApiResponse::error('Akses khusus wali kelas', 403);
        }

        $walas = WaliKelas::with('kelas.jurusan')->where('akun_id', $user->akun_id)->first();
        if (!$walas || !$walas->kelas) {
            return ApiResponse::error('Wali kelas tidak memiliki kelas yang diampu', 404);
        }

        $kelas = $walas->kelas;
        $label = sprintf(
            'Kelas %s %s %s',
            $kelas->tingkat ?? '-',
            $kelas->jurusan->nama_jurusan ?? '-',
            $kelas->paralel ?? '-'
        );

        return ApiResponse::success([
            'walas_id' => $walas->walas_id,
            'kelas_id' => $kelas->kelas_id,
            'tingkat' => $kelas->tingkat,
            'paralel' => $kelas->paralel,
            'jurusan' => $kelas->jurusan ? [
                'jurusan_id' => $kelas->jurusan->jurusan_id,
                'nama_jurusan' => $kelas->jurusan->nama_jurusan,
            ] : null,
            'kelas_label' => $label,
        ], 'Info wali kelas berhasil diambil');
    }

    public function updateProfil(Request $request)
    {
        $user = Auth::user();
        if (!$user || ($user->role !== 'gurket' && $user->role !== 'walas')) {
            return ApiResponse::error('Role tidak diizinkan', 403);
        }

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'nama' => 'required|string|max:255',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validasi gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        if ($user->role === 'gurket') {
            $model = \App\Models\GuruPiket::where('akun_id', $user->akun_id)->firstOrFail();
        } else {
            $model = \App\Models\WaliKelas::where('akun_id', $user->akun_id)->firstOrFail();
        }

        $model->nama = $request->input('nama');
        $model->save();

        return ApiResponse::success($model, 'Profil berhasil diperbarui');
    }
}
