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
            'Homeroom_Teacher_ID' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    $teacherService = app(\App\Services\Core\TeacherService::class);
                    if (!$teacherService->getTeacherById($value)) {
                        $fail('Guru tidak ditemukan.');
                    }
                }
            ],
            'Capacity' => 'required|numeric|min:1',
            'Current_Student' => 'nullable|numeric|min:0|lte:Capacity',
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
