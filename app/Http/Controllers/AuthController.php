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
        // dd($credentials);

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

        return response()->json([
            'responseStatus' => true,
            'responseMessage' => 'Login berhasil',
            'responseHeader' => [
                'access_token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => Auth::factory()->getTTL() * 60
            ],
            'responseData' => [
                'akun_id' => $user->akun_id,
                'username' => $user->username,
                'role' => $user->role,
                'nama' => $nama,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at
            ]
        ]);
    }

    /**
     * Ambil user yang sedang login
     */
    public function me()
    {
        // return response()->json(Auth::user());
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

        return response()->json([
            'responseStatus' => true,
            'responseMessage' => 'Profil berhasil diambil',
            'responseData' => [
                'akun_id' => $user->akun_id,
                'username' => $user->username,
                'role' => $user->role,
                'nama' => $nama,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ],
        ]);
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
            return response()->json([
                'responseStatus' => false,
                'responseMessage' => 'Password lama tidak sesuai',
            ], 422);
        }

        $akun->password = Hash::make($validated['new_password']);
        $akun->save();

        return response()->json([
            'responseStatus' => true,
            'responseMessage' => 'Password berhasil diubah',
        ]);
    }

    /**
     * Logout user
     */
    public function logout()
    {
        Auth::logout();

        // return response()->json(['message' => 'Successfully logged out']);
        // return ApiResponse::success(null, 'Successfully logged out');

        return response()->json([
            'responseStatus' => true,
            'responseMessage' => 'Successfully logged out',
        ]);
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
