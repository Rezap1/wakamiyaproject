<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreQRSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'Title' => 'required|string|max:255',
            'Start_Time' => 'required|date_format:H:i',
            'End_Time' => 'required|date_format:H:i|after:Start_Time',
            'Grace_Period' => 'nullable|integer|min:0|max:120',
            'Notes' => 'nullable|string'
        ];
    }
}
