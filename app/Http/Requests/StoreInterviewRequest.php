<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInterviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'Interview_Number' => 'nullable|string|max:100',
            'Job_Order_ID' => 'required|string',
            'Student_ID' => 'required|string',
            'Interview_Date' => 'required|date',
            'Interview_Time' => 'required|string',
            'Interview_Method' => 'required|string',
            'Interviewer' => 'nullable|string|max:150',
            'Interview_Result' => 'required|string',
            'Result_Date' => 'nullable|date',
            'Remarks' => 'nullable|string',
            'Interview_Status' => 'required|string',
            'Is_Active' => 'nullable|in:TRUE,FALSE',
            'Notes' => 'nullable|string',
        ];
    }
}
