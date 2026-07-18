<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
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
                        $service = app(\App\Services\Core\EmployeeService::class);
                        if ($service->getEmployeeByNationalId($value)) {
                            $fail('Nomor Identitas (KTP) sudah terdaftar.');
                        }
                    }
                }
            ],
            'Phone_Number' => 'required|string|max:20',
            'Email' => [
                'required',
                'email',
                'max:100',
                function ($attribute, $value, $fail) {
                    $service = app(\App\Services\Core\EmployeeService::class);
                    if ($service->getEmployeeByEmail($value)) {
                        $fail('Alamat Email sudah terdaftar.');
                    }
                }
            ],
            'Address' => 'nullable|string',
            'Department_ID' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    $deptService = app(\App\Services\Core\DepartmentService::class);
                    $department = $deptService->getDepartmentById($value);
                    if (!$department) {
                        $fail('Departemen tidak ditemukan.');
                    } elseif (($department['Is_Active'] ?? 'TRUE') === 'FALSE') {
                        $fail('Departemen tidak aktif.');
                    }
                }
            ],
            'Position_ID' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    $posService = app(\App\Services\Core\PositionService::class);
                    $position = $posService->getPositionById($value);
                    if (!$position) {
                        $fail('Posisi tidak ditemukan.');
                    } elseif (($position['Is_Active'] ?? 'TRUE') === 'FALSE') {
                        $fail('Posisi tidak aktif.');
                    }
                }
            ],
            'Join_Date' => 'required|date',
            'Employment_Status' => 'required|string|max:50',
            'Tax_Number' => 'nullable|string|max:50',
            'Bank_Name' => 'nullable|string|max:100',
            'Bank_Account_Number' => 'nullable|string|max:50',
            'Account_Holder_Name' => 'nullable|string|max:150',
            'Is_Active' => 'nullable|in:TRUE,FALSE',
            'Notes' => 'nullable|string'
        ];
    }
}
