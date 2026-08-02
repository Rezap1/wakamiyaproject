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
            'User_ID' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    $userService = app(\App\Services\Core\UserService::class);
                    if (!$userService->getUserById($value)) {
                        $fail('User tidak ditemukan.');
                    }
                }
            ],
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

            'Address' => 'nullable|string',
            'Education' => 'required|string|max:100',
            'Program_ID' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    $programService = app(\App\Services\Core\ProgramService::class);
                    if (!$programService->getProgramById($value)) {
                        $fail('Program tidak ditemukan.');
                    }
                }
            ],
            'Batch_ID' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    $batchService = app(\App\Services\Core\BatchService::class);
                    if (!$batchService->getBatchById($value)) {
                        $fail('Batch tidak ditemukan.');
                    }
                }
            ],
            'Class_ID' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    $classService = app(\App\Services\Core\ClassService::class);
                    if (!$classService->getClassById($value)) {
                        $fail('Class tidak ditemukan.');
                    }
                }
            ],
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
            'Registration_Date.required' => 'Tanggal registrasi wajib diisi.',
            'User_ID.required' => 'Akun Pengguna (User) wajib dipilih.'
        ];
    }
}
