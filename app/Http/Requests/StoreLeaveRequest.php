<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLeaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'Leave_Type' => 'required|in:CUTI_TAHUNAN,SAKIT,IZIN_RESMI,CUTI_MELAHIRKAN',
            'Start_Date' => 'required|date',
            'End_Date' => 'required|date|after_or_equal:Start_Date',
            'Reason' => 'required|string|max:500'
        ];
    }
}
