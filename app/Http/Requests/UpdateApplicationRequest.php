<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'Application_Number' => 'nullable|string|max:100',
            'Matching_ID' => 'nullable|string',
            'Student_ID' => 'required|string',
            'Job_Order_ID' => 'required|string',
            'Application_Date' => 'required|date',
            'Application_Status' => 'required|string',
            'Application_Fee' => 'nullable|numeric|min:0',
            'Payment_Status' => 'nullable|string|in:PENDING,PAID,PARTIAL,FAILED,REFUNDED',
            'Remarks' => 'nullable|string',
            'Is_Active' => 'nullable|in:TRUE,FALSE',
            'Notes' => 'nullable|string',
        ];
    }
}
