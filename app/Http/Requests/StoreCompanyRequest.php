<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'Company_Code' => [
                'required',
                'string',
                'max:50',
                function ($attribute, $value, $fail) {
                    $companyService = app(\App\Services\Core\CompanyService::class);
                    $company = $companyService->getAllCompanies()->firstWhere('Company_Code', $value);
                    if ($company) {
                        $fail('Kode Perusahaan sudah terdaftar.');
                    }
                }
            ],
            'Company_Name' => [
                'required',
                'string',
                'max:150',
                function ($attribute, $value, $fail) {
                    $companyService = app(\App\Services\Core\CompanyService::class);
                    $company = $companyService->getAllCompanies()->firstWhere('Company_Name', $value);
                    if ($company) {
                        $fail('Nama Perusahaan sudah terdaftar.');
                    }
                }
            ],
            'Legal_Name' => 'required|string|max:150',
            'NPWP' => [
                'nullable',
                'string',
                'max:50',
                function ($attribute, $value, $fail) {
                    if (!empty($value)) {
                        $companyService = app(\App\Services\Core\CompanyService::class);
                        $company = $companyService->getAllCompanies()->firstWhere('NPWP', $value);
                        if ($company) {
                            $fail('NPWP sudah terdaftar pada perusahaan lain.');
                        }
                    }
                }
            ],
            'Business_License_Number' => 'nullable|string|max:100',
            'Address' => 'nullable|string',
            'City' => 'nullable|string|max:100',
            'Province' => 'nullable|string|max:100',
            'Postal_Code' => 'nullable|string|max:20',
            'Country' => 'required|string|max:100',
            'Phone_Number' => 'nullable|string|max:50',
            'Email' => 'nullable|email|max:150',
            'Website' => 'nullable|url|max:255',
            'Director_Name' => 'nullable|string|max:150',
            'Company_Logo' => 'nullable|image|mimes:jpg,jpeg,png,svg|max:2048',
            'Company_Stamp' => 'nullable|image|mimes:jpg,jpeg,png,svg|max:2048',
            'Is_Active' => 'nullable|in:TRUE,FALSE',
            'Notes' => 'nullable|string'
        ];
    }
    
    public function messages()
    {
        return [
            'Company_Code.required' => 'Kode Perusahaan wajib diisi.',
            'Company_Name.required' => 'Nama Perusahaan wajib diisi.',
            'Legal_Name.required' => 'Nama Legal (PT/CV) wajib diisi.',
            'Country.required' => 'Negara wajib diisi.',
            'Email.email' => 'Format alamat email tidak valid.',
            'Website.url' => 'Format URL website tidak valid (gunakan http:// atau https://).',
            'Company_Logo.image' => 'Logo harus berupa file gambar.',
            'Company_Logo.mimes' => 'Format logo yang diizinkan hanya JPG, JPEG, PNG, dan SVG.',
            'Company_Logo.max' => 'Ukuran logo maksimal adalah 2MB.',
            'Company_Stamp.image' => 'Stempel harus berupa file gambar.',
            'Company_Stamp.mimes' => 'Format stempel yang diizinkan hanya JPG, JPEG, PNG, dan SVG.',
            'Company_Stamp.max' => 'Ukuran stempel maksimal adalah 2MB.'
        ];
    }
}
