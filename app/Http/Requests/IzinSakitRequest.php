<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Helpers\ApiResponse; // pastikan namespace sesuai

class IzinSakitRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Bisa ganti sesuai kebutuhan (policy, role, dsb)
        return true;
    }

    public function rules(): array
    {
        return [
            'tanggal'      => 'required|date',
            'keterangan'   => 'required|string',
            'bukti'        => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
            'jenis_absen'  => 'required',
        ];
    }

    public function messages(): array
    {
        return [
            'tanggal.required' => 'Tanggal izin sakit harus diisi',
            'tanggal.date' => 'Format tanggal tidak valid, gunakan YYYY-MM-DD',
            'keterangan.required' => 'Keterangan harus diisi',
            // 'bukti.required' => 'Bukti harus diunggah',
            'bukti.file' => 'Bukti harus berupa file',
            'bukti.mimes' => 'Bukti harus berupa file dengan format: jpg, jpeg, png, atau pdf',
            'bukti.max' => 'Ukuran file bukti maksimal 2MB',
            'jenis_absen.required' => 'Jenis absen harus diisi',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            ApiResponse::error('Validasi gagal', $validator->errors(), 422)
        );
    }
}
