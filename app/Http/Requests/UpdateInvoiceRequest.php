<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'Student_ID' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    $studentService = app(\App\Services\Core\StudentService::class);
                    $student = $studentService->getStudentById($value);
                    if (!$student) {
                        $fail('Siswa tidak ditemukan.');
                    }
                }
            ],
            'Amount' => 'required|numeric|min:0',
            'Category' => 'required|string|max:100',
            'Due_Date' => 'required|date',
            'Notes' => 'nullable|string'
        ];
    }
}
