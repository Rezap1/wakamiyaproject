<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Support\Academic\AssignmentStatus;

class UpdateAssignmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'Status' => AssignmentStatus::normalize(AssignmentStatus::extractFrom($this->all())),
        ]);
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
            'Status' => ['required', 'string', Rule::in(AssignmentStatus::values())],
            'Description' => 'nullable|string',
        ];
    }
}
