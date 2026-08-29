<?php

namespace App\Services\Core;

use App\Interfaces\GoogleSheets\CompanyRepositoryInterface;
use App\Services\Core\EnterpriseEventService;
use Exception;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

class CompanyService
{
    protected $companyRepository;
    protected $enterpriseEvent;

    public function __construct(
        CompanyRepositoryInterface $companyRepository,
        EnterpriseEventService $enterpriseEvent
    ) {
        $this->companyRepository = $companyRepository;
        $this->enterpriseEvent = $enterpriseEvent;
    }

    public function getAllCompanies()
    {
        return $this->companyRepository->fetchAll();
    }

    public function getCompanyById($id)
    {
        return $this->companyRepository->findById($id);
    }

    public function createCompany(array $data)
    {
        $existingByCode = $this->companyRepository->findByCode($data['Company_Code']);
        if ($existingByCode) {
            throw new Exception("Kode Perusahaan sudah terdaftar.");
        }

        $existingByName = $this->getAllCompanies()->firstWhere('Company_Name', $data['Company_Name']);
        if ($existingByName) {
            throw new Exception("Nama Perusahaan sudah terdaftar.");
        }

        if (!empty($data['NPWP'])) {
            $existingByNpwp = $this->getAllCompanies()->firstWhere('NPWP', $data['NPWP']);
            if ($existingByNpwp) {
                throw new Exception("NPWP sudah terdaftar pada perusahaan lain.");
            }
        }

        $newId = $this->companyRepository->generateNewId('COM', 6);

        // Handle File Uploads
        $logoPath = '';
        $stampPath = '';
        
        if (isset($data['Company_Logo']) && $data['Company_Logo'] instanceof UploadedFile) {
            $logoPath = $data['Company_Logo']->store('companies/logos', 'public');
        }

        if (isset($data['Company_Stamp']) && $data['Company_Stamp'] instanceof UploadedFile) {
            $stampPath = $data['Company_Stamp']->store('companies/stamps', 'public');
        }

        $mappedData = [
            'Company_ID' => $newId,
            'Company_Code' => $data['Company_Code'],
            'Company_Name' => $data['Company_Name'],
            'Legal_Name' => $data['Legal_Name'],
            'NPWP' => $data['NPWP'] ?? '',
            'Business_License_Number' => $data['Business_License_Number'] ?? '',
            'Address' => $data['Address'] ?? '',
            'City' => $data['City'] ?? '',
            'Province' => $data['Province'] ?? '',
            'Postal_Code' => $data['Postal_Code'] ?? '',
            'Country' => $data['Country'],
            'Phone_Number' => $data['Phone_Number'] ?? '',
            'Email' => $data['Email'] ?? '',
            'Website' => $data['Website'] ?? '',
            'Director_Name' => $data['Director_Name'] ?? '',
            'Company_Logo' => $logoPath,
            'Company_Stamp' => $stampPath,
            'Is_Active' => $data['Is_Active'] ?? 'TRUE',
            'Created_At' => now()->toDateTimeString(),
            'Updated_At' => now()->toDateTimeString(),
            'Created_By' => \App\Support\ActorIdentity::required(),
            'Updated_By' => \App\Support\ActorIdentity::required(),
            'Notes' => $data['Notes'] ?? ''
        ];

        $this->companyRepository->create($mappedData);
        
        $this->enterpriseEvent->dispatch(
            'COMPANY',
            'CREATE',
            'COMPANY',
            $newId,
            \App\Support\ActorIdentity::required(),
            ['ADMINISTRATOR'],
            [],
            $mappedData
        );

        return $mappedData;
    }
    
    public function updateCompany($id, array $data)
    {
        $company = $this->getCompanyById($id);
        if (!$company) {
            throw new Exception("Data Perusahaan tidak ditemukan.");
        }

        // Validate uniqueness if changing
        if (isset($data['Company_Code']) && $data['Company_Code'] !== $company['Company_Code']) {
            $existingByCode = $this->companyRepository->findByCode($data['Company_Code']);
            if ($existingByCode) {
                throw new Exception("Kode Perusahaan sudah terdaftar.");
            }
        }

        if (isset($data['Company_Name']) && $data['Company_Name'] !== $company['Company_Name']) {
            $existingByName = $this->getAllCompanies()->firstWhere('Company_Name', $data['Company_Name']);
            if ($existingByName) {
                throw new Exception("Nama Perusahaan sudah terdaftar.");
            }
        }

        if (!empty($data['NPWP']) && $data['NPWP'] !== $company['NPWP']) {
            $existingByNpwp = $this->getAllCompanies()->firstWhere('NPWP', $data['NPWP']);
            if ($existingByNpwp) {
                throw new Exception("NPWP sudah terdaftar pada perusahaan lain.");
            }
        }

        $mappedData = [
            'Updated_At' => now()->toDateTimeString(),
            'Updated_By' => \App\Support\ActorIdentity::required(),
        ];
        
        // Handle File Uploads
        if (isset($data['Company_Logo']) && $data['Company_Logo'] instanceof UploadedFile) {
            // Delete old file if exists
            if (!empty($company['Company_Logo']) && Storage::disk('public')->exists($company['Company_Logo'])) {
                Storage::disk('public')->delete($company['Company_Logo']);
            }
            $mappedData['Company_Logo'] = $data['Company_Logo']->store('companies/logos', 'public');
        } elseif (array_key_exists('remove_logo', $data) && $data['remove_logo'] == '1') {
            if (!empty($company['Company_Logo']) && Storage::disk('public')->exists($company['Company_Logo'])) {
                Storage::disk('public')->delete($company['Company_Logo']);
            }
            $mappedData['Company_Logo'] = '';
        }

        if (isset($data['Company_Stamp']) && $data['Company_Stamp'] instanceof UploadedFile) {
            // Delete old file if exists
            if (!empty($company['Company_Stamp']) && Storage::disk('public')->exists($company['Company_Stamp'])) {
                Storage::disk('public')->delete($company['Company_Stamp']);
            }
            $mappedData['Company_Stamp'] = $data['Company_Stamp']->store('companies/stamps', 'public');
        } elseif (array_key_exists('remove_stamp', $data) && $data['remove_stamp'] == '1') {
            if (!empty($company['Company_Stamp']) && Storage::disk('public')->exists($company['Company_Stamp'])) {
                Storage::disk('public')->delete($company['Company_Stamp']);
            }
            $mappedData['Company_Stamp'] = '';
        }

        $allowedFields = [
            'Company_Code', 'Company_Name', 'Legal_Name', 'NPWP', 'Business_License_Number', 
            'Address', 'City', 'Province', 'Postal_Code', 'Country', 'Phone_Number', 'Email', 
            'Website', 'Director_Name', 'Is_Active', 'Notes'
        ];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $mappedData[$field] = $data[$field];
            }
        }

        $res = $this->companyRepository->update($id, $mappedData);

        $this->enterpriseEvent->dispatch(
            'COMPANY',
            'UPDATE',
            'COMPANY',
            $id,
            \App\Support\ActorIdentity::required(),
            ['ADMINISTRATOR'],
            [],
            $mappedData
        );

        return $res;
    }

    public function deleteCompany($id)
    {
        $res = $this->companyRepository->delete($id);

        $this->enterpriseEvent->dispatch(
            'COMPANY',
            'DELETE',
            'COMPANY',
            $id,
            \App\Support\ActorIdentity::required(),
            ['ADMINISTRATOR'],
            [],
            []
        );

        return $res;
    }
}
