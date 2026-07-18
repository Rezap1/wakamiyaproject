<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'Document_Number' => 'nullable|string|max:100',
            'Application_ID' => 'nullable|string',
            'Student_ID' => 'required|string',
            'Document_Type' => 'required|string',
            'Document_Name' => 'required|string',
            'File_Name' => 'nullable|string',
            'File_URL' => 'nullable|url',
            'Issue_Date' => 'nullable|date',
            'Expiry_Date' => 'nullable|date|after_or_equal:Issue_Date',
            'Document_Status' => 'required|string',
            'Verified_By' => 'nullable|string',
            'Verification_Date' => 'nullable|date',
            'Remarks' => 'nullable|string',
            'Is_Active' => 'nullable|in:TRUE,FALSE',
            'Notes' => 'nullable|string',
        ];
    }
}
