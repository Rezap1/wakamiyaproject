<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'Status' => 'required|in:Verified,Need Revision,Rejected',
            'Notes' => 'nullable|string|max:500',
            'Account_ID' => 'nullable|string'
        ];
    }
}
