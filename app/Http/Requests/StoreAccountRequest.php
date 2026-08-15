<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'Account_Code' => [
                'required',
                'string',
                'max:50',
                function ($attribute, $value, $fail) {
                    $accountRepo = app(\App\Interfaces\GoogleSheets\AccountRepositoryInterface::class);
                    $allAccounts = collect($accountRepo->fetchAll())->where('Is_Active', '!=', 'FALSE');
                    if ($allAccounts->contains('Account_Code', trim($value))) {
                        $fail("Kode Akun '{$value}' sudah digunakan. Harap gunakan kode akun lain yang unik.");
                    }
                }
            ],
            'Account_Name' => 'required|string|max:255',
            'Account_Category' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    $valid = ['ASSET', 'LIABILITY', 'EQUITY', 'REVENUE', 'EXPENSE'];
                    if (!in_array(strtoupper(trim($value)), $valid)) {
                        $fail('Kategori akun tidak valid. Harus salah satu dari: ASSET, LIABILITY, EQUITY, REVENUE, EXPENSE.');
                    }
                }
            ],
            'Parent_Account_ID' => 'nullable|string|max:100',
            'Description' => 'nullable|string'
        ];
    }
}
