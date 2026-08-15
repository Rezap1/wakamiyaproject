<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateBatchPayrollRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'Payroll_Period' => 'required|string|regex:/^\d{4}-\d{2}$/',
            'Notes' => 'nullable|string'
        ];
    }
}
