<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTeacherRequest extends FormRequest
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
                    // Check if user is already a teacher. Assuming we can check by User_ID.
                    // Wait, we need a method to get teacher by User_ID.
                    $allTeachers = collect($teacherService->getAllTeachers());
                    if ($allTeachers->contains('User_ID', $value)) {
                        $fail('User ini sudah terdaftar sebagai Guru/Tenaga Pendidik.');
                    }
                }
            ],
            'Specialization' => 'required|string|max:150',
            'Hire_Date' => 'required|date',
            'Teaching_Status' => 'required|string|max:50',
            'Is_Active' => 'nullable|in:TRUE,FALSE',
            'Notes' => 'nullable|string'
        ];
    }
}
