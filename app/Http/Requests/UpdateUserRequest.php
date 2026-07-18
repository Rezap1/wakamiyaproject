<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Authorization is handled via middleware
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'Username' => 'required|string|max:50',
            'Full_Name' => 'required|string|max:100',
            'Email' => 'required|email|max:100',
            'Password' => ['nullable', 'string', Password::min(8)->letters()->mixedCase()->numbers()->symbols()],
            'Role_ID' => 'required|string',
            'Employee_ID' => 'nullable|string',
            'Is_Active' => 'required|in:TRUE,FALSE',
            'Notes' => 'nullable|string|max:500'
        ];
    }
}
