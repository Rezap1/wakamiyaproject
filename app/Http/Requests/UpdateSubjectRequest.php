<?php

namespace App\Http\Requests;

use App\Services\Core\ProgramService;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSubjectRequest extends FormRequest
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
     * @return array<string, array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'Subject_Code' => 'required|string|max:50',
            'Subject_Name' => 'required|string|max:150',
            'Program_ID' => ['required', 'string', $this->activeProgramRule()],
            'Credit' => 'nullable|numeric|min:0',
            'Duration' => 'nullable|numeric|min:0',
            'Description' => 'nullable|string|max:1000',
            'Is_Active' => 'required|in:TRUE,FALSE',
            'Notes' => 'nullable|string|max:1000',
        ];
    }

    private function activeProgramRule(): \Closure
    {
        return function ($attribute, $value, $fail) {
            $program = app(ProgramService::class)->getProgramById(trim((string) $value));
            if (!$program) {
                $fail('Program tidak ditemukan.');
                return;
            }

            if (strtoupper(trim((string) ($program['Is_Active'] ?? 'TRUE'))) === 'FALSE') {
                $fail('Program tidak aktif.');
            }
        };
    }
}
