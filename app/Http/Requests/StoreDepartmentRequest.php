<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDepartmentRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'Department_Name' => [
                'required',
                'string',
                'max:100',
                function ($attribute, $value, $fail) {
                    $service = app(\App\Services\Core\DepartmentService::class);
                    if ($service->getDepartmentByName($value)) {
                        $fail('Nama Departemen sudah digunakan.');
                    }
                },
            ],
            'Department_Code' => [
                'required',
                'string',
                'max:20',
                function ($attribute, $value, $fail) {
                    $service = app(\App\Services\Core\DepartmentService::class);
                    if ($service->getDepartmentByCode($value)) {
                        $fail('Kode Departemen sudah digunakan.');
                    }
                },
            ],
            'Manager_Employee_ID' => 'nullable|string|max:50',
            'Is_Active' => 'nullable|in:TRUE,FALSE',
            'Notes' => 'nullable|string',
        ];
    }
}
