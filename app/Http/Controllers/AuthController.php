<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function Login(Request $request)
    {
        $credentials = $request->only('username', 'password');

        if (!$token = Auth::attempt($credentials)) {
            // return response()->json(['error' => 'Username atau password salah'], 401);
            return ApiResponse::error('Username atau password salah', null, 401);
        }

        return response()->json([
            'responseStatus' => true,
            'responseMessage' => 'Login berhasil',
            'responseHeader' => [
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => Auth::factory()->getTTL() * 60
            ],
            'responseData' => [
                'akun_id' => Auth::user()->akun_id,
                'username' => Auth::user()->username,
                'role' => Auth::user()->role,
                'created_at' => Auth::user()->created_at,
                'updated_at' => Auth::user()->updated_at
            ]
        ]);
    }

    /**
     * Ambil user yang sedang login
     */
    public function me()
    {
        // return response()->json(Auth::user());
        return ApiResponse::success(Auth::user());
    }

    /**
     * Logout user
     */
    public function logout()
    {
        Auth::logout();
        
        // return response()->json(['message' => 'Successfully logged out']);
        return ApiResponse::success(null, 'Successfully logged out');
    }

    /**
     * Refresh token
     */
    public function refresh()
    {
        return ApiResponse::success([
            'access_token' => Auth::refresh(),
            'token_type' => 'bearer',
            'expires_in' => Auth::factory()->getTTL() * 60,
            'user' => Auth::user()
        ]);
    }
}