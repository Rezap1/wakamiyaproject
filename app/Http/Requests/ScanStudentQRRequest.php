<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ScanStudentQRRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token' => 'required|string',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'device_info' => 'nullable|string|max:255'
        ];
    }

    public function messages(): array
    {
        return [
            'token.required' => 'QR Code token wajib diisi.',
            'latitude.required' => 'Koordinat latitude lokasi perangkat wajib diberikan.',
            'latitude.between' => 'Koordinat latitude tidak valid.',
            'longitude.required' => 'Koordinat longitude lokasi perangkat wajib diberikan.',
            'longitude.between' => 'Koordinat longitude tidak valid.'
        ];
    }
}
