<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'Invoice_ID' => 'required|string',
            'Amount_Paid' => 'required|numeric|gt:0',
            'Payment_Date' => 'nullable|date',
            'Payment_Method' => 'required|string',
            'Account_ID' => 'nullable|string',
            'Proof_File' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'Sender_Name' => 'nullable|string|max:255',
            'Notes' => 'nullable|string'
        ];
    }
}
