<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreVisaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'Visa_Number' => 'required|string|max:100',
            'COE_ID' => 'nullable|string',
            'Application_ID' => 'nullable|string',
            'Student_ID' => 'required|string',
            'Passport_Number' => 'required|string|max:100',
            'Visa_Type' => 'required|string|max:100',
            'Embassy' => 'required|string|max:200',
            'Submission_Date' => 'nullable|date',
            'Approval_Date' => 'nullable|date|after_or_equal:Submission_Date',
            'Issue_Date' => 'nullable|date|after_or_equal:Approval_Date',
            'Expiry_Date' => 'nullable|date|after_or_equal:Issue_Date',
            'Visa_Status' => 'required|string',
            'Remarks' => 'nullable|string',
            'Is_Active' => 'nullable|in:TRUE,FALSE',
            'Notes' => 'nullable|string',
        ];
    }
}
