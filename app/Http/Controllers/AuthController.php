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
        } elseif ($user->role === 'walas') {
            $wk = WaliKelas::where('akun_id', $user->akun_id)->first();
            $nama = $wk?->nama;
        } elseif ($user->role === 'siswa') {
            $sw = Siswa::where('akun_id', $user->akun_id)->first();
            $nama = $sw?->nama;
        }

        return ApiResponse::success([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => Auth::factory()->getTTL() * 60,
            'user' => [
                'akun_id' => $user->akun_id,
                'username' => $user->username,
                'role' => $user->role,
                'nama' => $nama,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at
            ]
        ], 'Login berhasil');
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

        return ApiResponse::success(null, 'Successfully logged out');
    }

    /**
     * Refresh token
     */
    public function refresh()
    {
        return ApiResponse::success([
            'access_token' => Auth::refresh(),
            'token_type' => 'Bearer',
            'expires_in' => Auth::factory()->getTTL() * 60,
            'user' => Auth::user()
        ], 'Token refreshed');
    }
}
