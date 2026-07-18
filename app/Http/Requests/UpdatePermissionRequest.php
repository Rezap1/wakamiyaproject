<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'Role_ID' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    $service = app(\App\Services\Core\PermissionService::class);
                    $role = $service->getAllRoles()->firstWhere('Role_ID', $value);
                    if (!$role) {
                        $fail('Role tidak valid atau tidak ditemukan.');
                    }
                }
            ],
            'Module_ID' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    $service = app(\App\Services\Core\PermissionService::class);
                    $module = $service->getAllModules()->firstWhere('Module_ID', $value);
                    if (!$module) {
                        $fail('Modul tidak valid atau tidak ditemukan.');
                    }
                }
            ],
            'Can_View' => 'nullable|boolean',
            'Can_Create' => 'nullable|boolean',
            'Can_Edit' => 'nullable|boolean',
            'Can_Delete' => 'nullable|boolean',
            'Can_Print' => 'nullable|boolean',
            'Can_Export_PDF' => 'nullable|boolean',
            'Is_Active' => 'required|in:TRUE,FALSE',
            'Notes' => 'nullable|string'
        ];
    }
    
    public function messages()
    {
        return [
            'Role_ID.required' => 'Role wajib dipilih.',
            'Module_ID.required' => 'Modul wajib dipilih.',
        ];
    }
    
    protected function prepareForValidation()
    {
        // Konversi checkbox ke boolean
        $this->merge([
            'Can_View' => $this->has('Can_View'),
            'Can_Create' => $this->has('Can_Create'),
            'Can_Edit' => $this->has('Can_Edit'),
            'Can_Delete' => $this->has('Can_Delete'),
            'Can_Print' => $this->has('Can_Print'),
            'Can_Export_PDF' => $this->has('Can_Export_PDF'),
        ]);
    }
}
