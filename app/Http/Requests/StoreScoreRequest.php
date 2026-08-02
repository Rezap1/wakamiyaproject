<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreScoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [

            'Assessment_ID' => [
                'required',
                function ($attribute, $value, $fail) {
                    $service = app(\App\Services\Academic\AssessmentService::class);
                    $parent = $service->getById($value);
                    if (!$parent) {
                        $fail('Parent data Assessment_ID tidak ditemukan.');
                    }
                }
            ],
        
            //
        ];
    }
}
