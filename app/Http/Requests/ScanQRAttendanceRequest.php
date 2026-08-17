<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ScanQRAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token' => 'required|string',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'device_info' => 'nullable|string|max:255'
        ];
    }
}
