<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAssignmentRequest extends FormRequest
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
            'Title' => 'required|string|max:255',
            'Class_ID' => 'required|string',
            'Teacher_ID' => 'required|string',
            'Deadline' => 'required|date',
            'Status' => 'required|string|in:Published,Closed,Active,Draft,Archived',
            'Description' => 'nullable|string',
        ];
    }
}
