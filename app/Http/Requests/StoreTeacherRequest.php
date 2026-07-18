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
            'Employee_ID' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    // Check if Employee exists
                    $employeeService = app(\App\Services\Core\EmployeeService::class);
                    $employee = $employeeService->getEmployeeById($value);
                    if (!$employee) {
                        $fail('Data Pegawai tidak ditemukan.');
                        return;
                    }

                    // Check if Employee is active
                    if (($employee['Is_Active'] ?? 'TRUE') === 'FALSE') {
                        $fail('Data Pegawai tidak aktif.');
                        return;
                    }

                    // Check if Employee is already a Teacher
                    $teacherService = app(\App\Services\Core\TeacherService::class);
                    if ($teacherService->getTeacherByEmployeeId($value)) {
                        $fail('Pegawai ini sudah terdaftar sebagai Guru/Tenaga Pendidik.');
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
