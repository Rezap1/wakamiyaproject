<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
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
            'items' => 'nullable|array',
            'items.*.description' => 'required_with:items|string|max:255',
            'items.*.qty' => 'required_with:items|numeric|min:1',
            'items.*.unit_price' => 'required_with:items|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.tax' => 'nullable|numeric|min:0',
        ];
    }
}
