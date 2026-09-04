<?php

namespace App\Http\Requests;

use App\Services\Core\ProgramService;
use Illuminate\Foundation\Http\FormRequest;

class StoreSubjectRequest extends FormRequest
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
            'Is_Active' => 'nullable|in:TRUE,FALSE',
            'Notes' => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'Subject_Code.required' => 'Kode materi wajib diisi.',
            'Subject_Code.max' => 'Kode materi maksimal 50 karakter.',
            'Subject_Name.required' => 'Nama materi wajib diisi.',
            'Subject_Name.max' => 'Nama materi maksimal 150 karakter.',
            'Program_ID.required' => 'Program wajib dipilih.',
            'Credit.numeric' => 'SKS harus berupa angka yang valid.',
            'Credit.min' => 'SKS tidak boleh bernilai negatif.',
            'Duration.numeric' => 'Durasi harus berupa angka yang valid.',
            'Duration.min' => 'Durasi harus lebih dari 0 menit.',
            'Description.max' => 'Deskripsi maksimal 1000 karakter.',
            'Is_Active.in' => 'Status materi tidak valid.',
            'Notes.max' => 'Catatan maksimal 1000 karakter.',
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
