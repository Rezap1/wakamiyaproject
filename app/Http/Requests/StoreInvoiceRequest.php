<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
    {
        \Illuminate\Support\Facades\Log::error('Invoice Validation Failed: ' . json_encode($validator->errors()->toArray()));
        parent::failedValidation($validator);
    }

    public function rules(): array
    {
        return [
            'Invoice_Type' => 'required|in:STUDENT,COMPANY',
            'Student_ID' => 'required_if:Invoice_Type,STUDENT|nullable|string',
            'Company_ID' => 'required_if:Invoice_Type,COMPANY|nullable|string',
            'Category' => 'required|string|max:100',
            'Due_Date' => 'required|date',
            'Notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.description' => 'required|string|max:255',
            'items.*.qty' => 'required|numeric|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.tax' => 'nullable|numeric|min:0',
        ];
    }
}
