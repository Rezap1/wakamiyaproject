<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePositionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'Position_Name' => [
                'required',
                'string',
                'max:100',
                function ($attribute, $value, $fail) {
                    $service = app(\App\Services\Core\PositionService::class);
                    if ($service->getPositionByName($value)) {
                        $fail('Nama Posisi sudah digunakan.');
                    }
                },
            ],
            'Position_Code' => [
                'required',
                'string',
                'max:20',
                function ($attribute, $value, $fail) {
                    $service = app(\App\Services\Core\PositionService::class);
                    if ($service->getPositionByCode($value)) {
                        $fail('Kode Posisi sudah digunakan.');
                    }
                },
            ],
            'Department_ID' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    $deptService = app(\App\Services\Core\DepartmentService::class);
                    $department = $deptService->getDepartmentById($value);
                    if (!$department) {
                        $fail('Departemen tidak ditemukan.');
                    } elseif (($department['Is_Active'] ?? 'TRUE') === 'FALSE') {
                        $fail('Departemen tidak aktif, silakan pilih departemen yang aktif.');
                    }
                }
            ],
            'Position_Level' => 'required|string|max:50',
            'Is_Active' => 'nullable|in:TRUE,FALSE',
            'Notes' => 'nullable|string',
        ];
    }
}
