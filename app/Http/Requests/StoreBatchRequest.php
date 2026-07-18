<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'Batch_Code' => [
                'required',
                'string',
                'max:50',
                function ($attribute, $value, $fail) {
                    $batchService = app(\App\Services\Core\BatchService::class);
                    $batch = $batchService->getAllBatches()->firstWhere('Batch_Code', $value);
                    if ($batch) {
                        $fail('Kode Batch sudah digunakan oleh angkatan lain.');
                    }
                }
            ],
            'Batch_Name' => [
                'required',
                'string',
                'max:150',
                function ($attribute, $value, $fail) {
                    $batchService = app(\App\Services\Core\BatchService::class);
                    $batch = $batchService->getAllBatches()->firstWhere('Batch_Name', $value);
                    if ($batch) {
                        $fail('Nama Batch sudah digunakan oleh angkatan lain.');
                    }
                }
            ],
            'Program_ID' => 'required|string',
            'Start_Date' => 'required|date',
            'End_Date' => 'required|date|after_or_equal:Start_Date',
            'Batch_Status' => 'nullable|string|max:50',
            'Description' => 'nullable|string',
            'Is_Active' => 'nullable|in:TRUE,FALSE',
            'Notes' => 'nullable|string'
        ];
    }
    
    public function messages()
    {
        return [
            'End_Date.after_or_equal' => 'Tanggal Selesai harus sama atau setelah Tanggal Mulai.',
            'Program_ID.required' => 'Program wajib dipilih.',
        ];
    }
}
