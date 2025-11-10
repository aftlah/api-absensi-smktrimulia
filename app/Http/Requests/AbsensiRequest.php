<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Helpers\ApiResponse;

class AbsensiRequest extends FormRequest
{
    public function authorize(): bool
    {
        // ganti ke authorization logic jika perlu
        return true;
    }

    public function rules(): array
    {
        return [
            'latitude'  => 'required|numeric',
            'longitude' => 'required|numeric',
        ];
    }

    public function messages(): array
    {
        return [
            'latitude.required'  => 'Latitude wajib diisi.',
            'longitude.required' => 'Longitude wajib diisi.',
            'latitude.numeric'   => 'Latitude harus berupa angka.',
            'longitude.numeric'  => 'Longitude harus berupa angka.',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            ApiResponse::error('Validasi gagal', $validator->errors(), 422)
        );
    }
}
