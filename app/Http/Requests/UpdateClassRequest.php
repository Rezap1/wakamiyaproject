<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateClassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'Class_Code' => [
                'required',
                'string',
                'max:50',
                function ($attribute, $value, $fail) {
                    $classService = app(\App\Services\Core\ClassService::class);
                    $class = $classService->getAllClasses()->firstWhere('Class_Code', $value);
                    if ($class && $class['Class_ID'] !== $this->route('id')) {
                        $fail('Kode Kelas sudah digunakan oleh kelas lain.');
                    }
                }
            ],
            'Class_Name' => 'required|string|max:150',
            'Program_ID' => 'required|string',
            'Batch_ID' => 'required|string',
            'Homeroom_Teacher_ID' => 'required|string',
            'Capacity' => 'required|numeric|min:1',
            'Current_Student' => 'nullable|numeric|min:0|lte:Capacity',
            'Class_Status' => 'required|string|max:50',
            'Description' => 'nullable|string',
            'Is_Active' => 'required|in:TRUE,FALSE',
            'Notes' => 'nullable|string'
        ];
    }
    
    public function messages()
    {
        return [
            'Program_ID.required' => 'Program wajib dipilih.',
            'Batch_ID.required' => 'Angkatan (Batch) wajib dipilih.',
            'Homeroom_Teacher_ID.required' => 'Wali Kelas wajib dipilih.',
            'Current_Student.lte' => 'Jumlah siswa tidak boleh melebihi kapasitas kelas.',
            'Capacity.min' => 'Kapasitas kelas minimal 1.',
        ];
    }
}
