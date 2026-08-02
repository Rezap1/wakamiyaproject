<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreAttendanceRequest extends FormRequest
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

            'Class_ID' => 'required|string',
            'Attendance_Date' => 'required|date',
            'students' => 'required|array',
            'students.*.Student_ID' => 'required|string',
            'students.*.Status' => 'required|in:Hadir,Sakit,Izin,Alpha',
        
            //
        ];
    }
}
