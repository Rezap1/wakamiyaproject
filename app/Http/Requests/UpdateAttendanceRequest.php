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
            'Student_ID' => 'nullable|string',
            'Employee_ID' => 'nullable|string',
            'Attendance_Date' => 'nullable|date',
            'Status' => 'nullable|string',
            'Check_In_Time' => ['nullable', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'Check_Out_Time' => ['nullable', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'Notes' => 'nullable|string|max:1000',
        ];
    }
}
