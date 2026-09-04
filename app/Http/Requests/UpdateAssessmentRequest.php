<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAssessmentRequest extends FormRequest
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
            'Assessment_Name' => 'required|string|max:255',
            'Category' => 'required|string|max:100',
            'Status' => ['required', 'string', Rule::in(['Draft', 'Published', 'Closed', 'Archived'])],
            'Description' => 'nullable|string|max:1000',
        ];
    }
}
