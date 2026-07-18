<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Interfaces\GoogleSheets\ModuleRepositoryInterface;

class UpdateModuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'Module_Code' => ['required', 'string', 'max:50', function ($attribute, $value, $fail) {
                $repository = app(ModuleRepositoryInterface::class);
                $existing = $repository->findByCode($value);
                if ($existing && $existing['Module_ID'] !== $this->route('module')) {
                    $fail('Kode Modul sudah digunakan.');
                }
            }],
            'Module_Name' => ['required', 'string', 'max:255', function ($attribute, $value, $fail) {
                $repository = app(ModuleRepositoryInterface::class);
                $existing = $repository->findByName($value);
                if ($existing && $existing['Module_ID'] !== $this->route('module')) {
                    $fail('Nama Modul sudah digunakan.');
                }
            }],
            'Module_Group' => 'required|string|max:100',
            'Module_Order' => 'required|numeric|min:0',
            'Is_Active' => 'nullable|in:TRUE,FALSE',
            'Notes' => 'nullable|string'
        ];
    }

    public function messages(): array
    {
        return [
            'Module_Code.required' => 'Kode Modul wajib diisi.',
            'Module_Code.max' => 'Kode Modul maksimal 50 karakter.',
            'Module_Name.required' => 'Nama Modul wajib diisi.',
            'Module_Name.max' => 'Nama Modul maksimal 255 karakter.',
            'Module_Group.required' => 'Grup Modul wajib diisi.',
            'Module_Order.required' => 'Urutan Modul wajib diisi.',
            'Module_Order.numeric' => 'Urutan Modul harus berupa angka.',
            'Is_Active.in' => 'Format Status tidak valid.'
        ];
    }
}
