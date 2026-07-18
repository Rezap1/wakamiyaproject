<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMatchingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'Matching_Number' => 'nullable|string|max:100',
            'Student_ID' => 'required|string',
            'Job_Order_ID' => 'required|string',
            'Interview_ID' => 'nullable|string',
            'Matching_Date' => 'required|date',
            'Matching_Status' => 'required|string',
            'Company_Approval_Date' => 'nullable|date',
            'Student_Approval_Date' => 'nullable|date',
            'Remarks' => 'nullable|string',
            'Is_Active' => 'nullable|in:TRUE,FALSE',
            'Notes' => 'nullable|string',
        ];
    }
}
