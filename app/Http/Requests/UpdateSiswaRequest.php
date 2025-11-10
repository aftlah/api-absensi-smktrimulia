<?php

namespace App\Http\Requests;

use App\Helpers\ApiResponse;
use Illuminate\Foundation\Http\FormRequest;
use App\Models\Siswa;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateSiswaRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Bisa disesuaikan jika kamu ingin pakai Gate / Policy
        return true;
    }

    public function rules(): array
    {
        $siswaId = $this->input('siswa_id');
        $akunId = null;

        // Cegah error jika siswa_id tidak ditemukan
        if ($siswaId && Siswa::find($siswaId)) {
            $akunId = Siswa::find($siswaId)->akun_id;
        }

        return [
            // --- Siswa ---
            'siswa_id' => 'required|exists:siswa,siswa_id',
            'nis' => 'nullable|string|unique:siswa,nis,' . $siswaId . ',siswa_id',
            'nama' => 'nullable|string',
            'jenkel' => 'nullable|string|in:L,P',
            'kelas_id' => 'nullable|exists:kelas,kelas_id',

            // --- Akun ---
            'username' => 'nullable|string|unique:akun,username,' . $akunId . ',akun_id',
            'password' => 'nullable|string|min:6',
            'role' => 'nullable|string|in:siswa,gurket,walas,admin',

            // --- Data kelas opsional ---
            'kelas.tingkat' => 'nullable|string',
            'kelas.paralel' => 'nullable|string',
            'kelas.thn_ajaran' => 'nullable|string',
            'kelas.jurusan_id' => 'nullable|exists:jurusan,jurusan_id',
            'kelas.walas_id' => 'nullable|exists:wali_kelas,walas_id',
        ];
    }

    public function messages(): array
    {
        return [
            'siswa_id.required' => 'ID siswa harus diisi.',
            'siswa_id.exists' => 'Data siswa tidak ditemukan.',

            'nis.unique' => 'NIS sudah digunakan.',
            'username.unique' => 'Username sudah digunakan.',
            'password.min' => 'Password minimal 6 karakter.',

            'jenkel.in' => 'Jenis kelamin hanya boleh L atau P.',
            'role.in' => 'Role tidak valid.',

            'kelas_id.exists' => 'Kelas tidak ditemukan.',
            'kelas.jurusan_id.exists' => 'Jurusan tidak ditemukan.',
            'kelas.walas_id.exists' => 'Wali kelas tidak ditemukan.',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            ApiResponse::error('Validasi gagal', $validator->errors(), 422)
        );
    }
}
