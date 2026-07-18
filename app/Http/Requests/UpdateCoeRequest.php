<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCoeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'COE_Number' => 'required|string|max:100',
            'Application_ID' => 'nullable|string',
            'Student_ID' => 'required|string',
            'Company_ID' => 'required|string',
            'Application_Date' => 'nullable|date',
            'Submission_Date' => 'nullable|date',
            'Approval_Date' => 'nullable|date|after_or_equal:Submission_Date',
            'COE_Expiry_Date' => 'nullable|date|after_or_equal:Approval_Date',
            'COE_Status' => 'required|string',
            'Immigration_Office' => 'nullable|string',
            'Remarks' => 'nullable|string',
            'Is_Active' => 'nullable|in:TRUE,FALSE',
            'Notes' => 'nullable|string',
        ];
    }
}
