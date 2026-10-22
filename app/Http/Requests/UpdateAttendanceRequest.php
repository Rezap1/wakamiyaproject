<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAttendanceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'Attendance_Date' => 'nullable|date',
            'Status' => 'nullable|in:Hadir,Terlambat,Sakit,Izin,Alpha,PRESENT,LATE,SICK,PERMITTED,ABSENT',
            'Check_In_Time' => ['nullable', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'Check_Out_Time' => ['nullable', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'Notes' => 'required|string|min:5|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'Notes.required' => 'Alasan koreksi absensi wajib diisi.',
            'Notes.min' => 'Alasan koreksi absensi minimal 5 karakter.',
        ];
    }
}
