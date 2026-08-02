<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTeacherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'User_ID' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    $userService = app(\App\Services\Core\UserService::class);
                    $user = $userService->getUserById($value);
                    if (!$user) {
                        $fail('Data User tidak ditemukan.');
                        return;
                    }

                    if (($user['Is_Active'] ?? 'TRUE') === 'FALSE') {
                        $fail('Data User tidak aktif.');
                        return;
                    }

                    $teacherService = app(\App\Services\Core\TeacherService::class);
                    $allTeachers = collect($teacherService->getAllTeachers());
                    $existing = $allTeachers->firstWhere('User_ID', $value);
                    if ($existing && $existing['Teacher_ID'] !== $this->route('id')) {
                        $fail('User ini sudah terdaftar sebagai Guru/Tenaga Pendidik lainnya.');
                    }
                }
            ],
            'Specialization' => 'required|string|max:150',
            'Hire_Date' => 'required|date',
            'Teaching_Status' => 'required|string|max:50',
            'Is_Active' => 'required|in:TRUE,FALSE',
            'Notes' => 'nullable|string'
        ];
    }
}
