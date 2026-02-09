<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\Admin;
use App\Models\GuruPiket;
use App\Models\WaliKelas;
use App\Models\Siswa;
use App\Models\Akun;
use App\Models\JadwalPiket;


class AuthController extends Controller
{
    public function Login(Request $request)
    {
        $credentials = $request->only('username', 'password');

        if (!$token = Auth::attempt($credentials)) {
            return ApiResponse::error('Username atau password salah', null, 401);
        }

        $user = Auth::user();
        $nama = null;
        
        if ($user->role === 'admin') {
            $adm = Admin::where('akun_id', $user->akun_id)->first();
            $nama = $adm?->nama;
        } elseif ($user->role === 'gurket') {
            $gp = GuruPiket::where('akun_id', $user->akun_id)->first();
            $nama = $gp?->nama;
            
            // Validasi jadwal piket untuk hari ini
            if ($gp) {
                $today = date('Y-m-d');
                $jadwalHariIni = JadwalPiket::where('gurket_id', $gp->gurket_id)
                    ->where('tanggal', $today)
                    ->first();
                
                if (!$jadwalHariIni) {
                    // Cari siapa yang jadi jadwal piket hari ini
                    $jadwalPiketHariIni = JadwalPiket::with('guruPiket')
                        ->where('tanggal', $today)
                        ->first();
                    
                    $pesanPiketHariIni = '';
                    if ($jadwalPiketHariIni && $jadwalPiketHariIni->guruPiket) {
                        $pesanPiketHariIni = " Guru piket hari ini adalah {$jadwalPiketHariIni->guruPiket->nama}.";
                    } else {
                        $pesanPiketHariIni = " Tidak ada guru piket yang terjadwal untuk hari ini.";
                    }
                    
                    // Cari jadwal terdekat untuk guru piket ini
                    $jadwalTerdekat = JadwalPiket::where('gurket_id', $gp->gurket_id)
                        ->where('tanggal', '>=', $today)
                        ->orderBy('tanggal', 'asc')
                        ->first();
                    
                    $pesanJadwalTerdekat = '';
                    if ($jadwalTerdekat) {
                        $tanggalTerdekat = date('d-m-Y', strtotime($jadwalTerdekat->tanggal));
                        $hariTerdekat = $this->getNamaHari($jadwalTerdekat->tanggal);
                        $pesanJadwalTerdekat = " Jadwal piket Anda berikutnya adalah pada {$hariTerdekat}, {$tanggalTerdekat}.";
                    } else {
                        $pesanJadwalTerdekat = " Anda belum memiliki jadwal piket yang terdaftar. Silakan hubungi admin untuk mengatur jadwal piket Anda.";
                    }
                    
                    $hariIni = $this->getNamaHari($today);
                    $tanggalIni = date('d-m-Y');
                    
                    return ApiResponse::error(
                        "Akses Ditolak - Bukan Jadwal Piket Anda",
                        [
                            'detail' => "Anda tidak terjadwal sebagai guru piket pada {$hariIni}, {$tanggalIni}.{$pesanPiketHariIni}{$pesanJadwalTerdekat}",
                            'hari_ini' => $hariIni,
                            'tanggal_ini' => $tanggalIni,
                            'guru_piket_hari_ini' => $jadwalPiketHariIni ? [
                                'nama' => $jadwalPiketHariIni->guruPiket->nama,
                                'username' => $jadwalPiketHariIni->guruPiket->username
                            ] : null,
                            'jadwal_terdekat' => $jadwalTerdekat ? [
                                'tanggal' => $jadwalTerdekat->tanggal,
                                'hari' => $this->getNamaHari($jadwalTerdekat->tanggal),
                                'tanggal_formatted' => date('d-m-Y', strtotime($jadwalTerdekat->tanggal))
                            ] : null
                        ],
                        403
                    );
                }
            }
        } elseif ($user->role === 'walas') {
            $wk = WaliKelas::where('akun_id', $user->akun_id)->first();
            $nama = $wk?->nama;
        } elseif ($user->role === 'siswa') {
            $sw = Siswa::where('akun_id', $user->akun_id)->first();
            $nama = $sw?->nama;
        }

        // Set httpOnly cookie untuk keamanan
        $cookie = cookie(
            'auth_token',
            $token,
            Auth::factory()->getTTL(), // TTL dalam menit
            '/', // path
            null, // domain
            true, // secure (HTTPS only)
            true, // httpOnly
            false, // raw
            'strict' // sameSite
        );

        return ApiResponse::success([
            'user' => [
                'akun_id' => $user->akun_id,
                'username' => $user->username,
                'role' => $user->role,
                'nama' => $nama,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at
            ]
        ], 'Login berhasil')->withCookie($cookie);
    }

    /**
     * Helper function to get Indonesian day name
     */
    private function getNamaHari($tanggal)
    {
        $hariInggris = date('l', strtotime($tanggal));
        $hariIndonesia = [
            'Sunday' => 'Minggu',
            'Monday' => 'Senin',
            'Tuesday' => 'Selasa',
            'Wednesday' => 'Rabu',
            'Thursday' => 'Kamis',
            'Friday' => 'Jumat',
            'Saturday' => 'Sabtu'
        ];
        
        return $hariIndonesia[$hariInggris] ?? $hariInggris;
    }

    /**
     * Ambil user yang sedang login
     */
    public function me()
    {
        return ApiResponse::success(Auth::user(), 'User berhasil diambil');
    }

    public function profil()
    {
        $user = Auth::user();
        $nama = null;
        if ($user->role === 'admin') {
            $adm = Admin::where('akun_id', $user->akun_id)->first();
            $nama = $adm?->nama;
        } elseif ($user->role === 'gurket') {
            $gp = GuruPiket::where('akun_id', $user->akun_id)->first();
            $nama = $gp?->nama;
        } elseif ($user->role === 'walas') {
            $wk = WaliKelas::where('akun_id', $user->akun_id)->first();
            $nama = $wk?->nama;
        } elseif ($user->role === 'siswa') {
            $sw = Siswa::where('akun_id', $user->akun_id)->first();
            $nama = $sw?->nama;
        }

        return ApiResponse::success([
            'akun_id' => $user->akun_id,
            'username' => $user->username,
            'role' => $user->role,
            'nama' => $nama,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ], 'Profil berhasil diambil');
    }

    public function resetPassword(Request $request)
    {
        $user = Auth::user();
        $validated = $request->validate([
            'old_password' => 'required|string',
            'new_password' => 'required|string|min:6',
        ]);

        $akun = Akun::where('akun_id', $user->akun_id)->first();
        if (!$akun || !Hash::check($validated['old_password'], $akun->password)) {
            return ApiResponse::error('Password lama tidak sesuai', null, 422);
        }

        $akun->password = Hash::make($validated['new_password']);
        $akun->save();

        return ApiResponse::success(null, 'Password berhasil diubah');
    }

    /**
     * Logout user
     */
    public function logout()
    {
        Auth::logout();

        // Hapus cookie dengan set expired
        $cookie = cookie(
            'auth_token',
            '',
            -1, // expired
            '/',
            null,
            true,
            true,
            false,
            'strict'
        );

        return ApiResponse::success(null, 'Berhasil logout')->withCookie($cookie);
    }

    /**
     * Refresh token
     */
    public function refresh()
    {
        $newToken = Auth::refresh();
        
        // Set cookie baru dengan token yang di-refresh
        $cookie = cookie(
            'auth_token',
            $newToken,
            Auth::factory()->getTTL(),
            '/',
            null,
            true,
            true,
            false,
            'strict'
        );

        return ApiResponse::success([
            'user' => Auth::user()
        ], 'Token refreshed')->withCookie($cookie);
    }
}
