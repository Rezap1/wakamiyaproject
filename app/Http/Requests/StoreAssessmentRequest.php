<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAssessmentRequest extends FormRequest
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
     * @return array<string, array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'Name' => 'required|string|max:255',
            'Category' => 'required|string|max:100',
            'Program_ID' => 'nullable|string|max:50',
            'Class_ID' => 'nullable|string|max:50',
            'Teacher_ID' => 'nullable|string|max:50',
            'Exam_Date' => 'nullable|date',
            'Status' => ['nullable', 'string', Rule::in(['Draft', 'Published', 'Closed', 'Archived'])],
            'Description' => 'nullable|string|max:1000',
        ];
    }
}
