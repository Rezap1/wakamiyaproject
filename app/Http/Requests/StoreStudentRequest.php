<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'Student_Number' => [
                'required',
                'string',
                'max:50',
                function ($attribute, $value, $fail) {
                    $studentService = app(\App\Services\Core\StudentService::class);
                    $student = $studentService->getAllStudents()->firstWhere('Student_Number', $value);
                    if ($student) {
                        $fail('Nomor Induk Siswa sudah digunakan oleh siswa lain.');
                    }
                }
            ],
            'Registration_Date' => 'required|date',
            'Full_Name' => 'required|string|max:150',
            'Gender' => 'required|in:Laki-laki,Perempuan',
            'Birth_Place' => 'nullable|string|max:100',
            'Birth_Date' => 'nullable|date',
            'National_ID' => [
                'nullable',
                'string',
                'max:50',
                function ($attribute, $value, $fail) {
                    if (!empty($value)) {
                        $studentService = app(\App\Services\Core\StudentService::class);
                        $student = $studentService->getAllStudents()->firstWhere('National_ID', $value);
                        if ($student) {
                            $fail('NIK (KTP) sudah terdaftar atas nama siswa lain.');
                        }
                    }
                }
            ],
            'Phone_Number' => 'nullable|string|max:30',
            'Email' => 'nullable|email|max:100',
            'Address' => 'nullable|string',
            'Education' => 'required|string|max:100',
            'Program_ID' => 'required|string',
            'Batch_ID' => 'required|string',
            'Class_ID' => 'required|string',
            'Enrollment_Status' => 'required|string|max:50',
            'Graduation_Status' => 'nullable|string|max:50',
            'Is_Active' => 'nullable|in:TRUE,FALSE',
            'Notes' => 'nullable|string'
        ];
    }
    
    public function messages()
    {
        return [
            'Program_ID.required' => 'Program wajib dipilih.',
            'Batch_ID.required' => 'Angkatan (Batch) wajib dipilih.',
            'Class_ID.required' => 'Kelas wajib dipilih.',
            'Gender.required' => 'Jenis kelamin wajib dipilih.',
            'Education.required' => 'Pendidikan terakhir wajib diisi.',
            'Registration_Date.required' => 'Tanggal registrasi wajib diisi.',
            'Email.email' => 'Format email tidak valid.'
        ];
    }
}
