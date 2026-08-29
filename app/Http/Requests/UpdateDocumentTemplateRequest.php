<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateDocumentTemplateRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'Template_Name' => ['required', 'string', 'max:150'],
            'Template_Code' => ['required', 'string', 'max:80'],
            'Document_Type' => ['required', 'string', 'max:100'],
            'Description' => ['nullable', 'string', 'max:1000'],
            'Template_Content' => ['nullable', 'string'],
            'Status' => ['nullable', 'in:Active,Inactive'],
        ];
    }
}
