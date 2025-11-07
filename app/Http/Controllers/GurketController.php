<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Helpers\ImportHelper;
use Illuminate\Http\Request;
use App\Models\Absensi;
use App\Models\Kelas;
use App\Models\Siswa;
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

    // public function getAbsensiSiswaHariIni()
    // {

    //     $absensi = Absensi::with('siswa.kelas')
    //         ->whereDate('tanggal', Carbon::today()->toDateString())
    //         ->get();
    //     return ApiResponse::success([
    //         'absensi' => $absensi,
    //     ], 'Absensi siswa berhasil diambil');
    // }

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

        $kelasId = $request->query('kelas_id');
        $tanggal = $request->query('tanggal');

        $tanggal = $tanggal ? Carbon::parse($tanggal)->toDateString() : Carbon::today()->toDateString();

        // Query absensi dengan relasi siswa dan kelas
        $query = Absensi::with('siswa.kelas')
            ->whereDate('tanggal', $tanggal);

        // Jika ada filter kelas, tambahkan kondisi
        if (!empty($kelasId)) {
            $query->whereHas('siswa.kelas', function ($q) use ($kelasId) {
                $q->where('kelas_id', $kelasId);
            });
        }

        // Ambil data dan format ulang hasilnya
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
                'jenis_absen' => $item->jenis_absen,
                'tanggal'    => $item->tanggal,
                'jam_datang' => $item->jam_datang,
                'jam_pulang' => $item->jam_pulang,
                'status'     => $item->status,
                'latitude'   => $item->latitude,
                'longitude'  => $item->longitude,
                'bukti'      => $item->bukti,
                'created_at' => $item->created_at,
                'updated_at' => $item->updated_at,
            ];
        });

        return ApiResponse::success([
            'filter' => [
                'kelas_id' => $kelasId ?? 'Semua',
                'tanggal' => $tanggal,
            ],
            'absensi' => $absensi,
        ], 'Data absensi berhasil diambil');
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



    public function update(Request $request)
    {
        $request->validate([
            'siswa_id' => 'required|exists:siswa,siswa_id',

            // Siswa fields
            'nis' => 'nullable|string|unique:siswa,nis,' . $request->input('siswa_id') . ',siswa_id',
            'nama' => 'nullable|string',
            'jenkel' => 'nullable|string|in:L,P',
            'kelas_id' => 'nullable|exists:kelas,kelas_id',

            // Akun fields
            'username' => 'nullable|string|unique:akun,username,' . Siswa::find($request->input('siswa_id'))->akun_id . ',akun_id',
            'password' => 'nullable|string|min:6',
            'role' => 'nullable|string|in:siswa,gurket,walas,admin',

            // Optional: update kelas yang sudah ada
            'kelas.tingkat' => 'nullable|string',
            'kelas.paralel' => 'nullable|string',
            'kelas.thn_ajaran' => 'nullable|string',
            'kelas.jurusan_id' => 'nullable|exists:jurusan,jurusan_id',
            'kelas.walas_id' => 'nullable|exists:wali_kelas,walas_id',
        ]);

        $siswa = Siswa::with(['akun', 'kelas'])->findOrFail($request->input('siswa_id'));

        // Update Siswa
        $siswa->fill($request->only(['nis', 'nama', 'jenkel', 'kelas_id']));
        $siswa->save();

        // Update Akun
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

        // Update Kelas (jika data kelas sudah ada dan ingin diperbarui)
        if ($siswa->kelas && $request->has('kelas')) {
            $kelasData = $request->input('kelas');
            $siswa->kelas->fill($kelasData);
            $siswa->kelas->save();
        }

        // Reload dengan relasi terbaru
        $siswa->load(['kelas', 'akun']);

        return ApiResponse::success([
            'siswa' => $siswa,
        ], 'Data siswa berhasil diperbarui');
    }
}
