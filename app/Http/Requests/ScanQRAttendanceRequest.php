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
            'device_info' => 'nullable|string|max:255'
        ];
    }
}
