<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProgramRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'Program_Code' => [
                'required',
                'string',
                'max:50',
                function ($attribute, $value, $fail) {
                    $programService = app(\App\Services\Core\ProgramService::class);
                    $program = $programService->getAllPrograms()->firstWhere('Program_Code', $value);
                    if ($program && $program['Program_ID'] !== $this->route('id')) {
                        $fail('Kode Program sudah digunakan oleh program lain.');
                    }
                }
            ],
            'Program_Name' => [
                'required',
                'string',
                'max:150',
                function ($attribute, $value, $fail) {
                    $programService = app(\App\Services\Core\ProgramService::class);
                    $program = $programService->getAllPrograms()->firstWhere('Program_Name', $value);
                    if ($program && $program['Program_ID'] !== $this->route('id')) {
                        $fail('Nama Program sudah digunakan oleh program lain.');
                    }
                }
            ],
            'Program_Category' => 'required|string|max:100',
            'Description' => 'nullable|string',
            'Is_Active' => 'required|in:TRUE,FALSE',
            'Notes' => 'nullable|string'
        ];
    }
}
