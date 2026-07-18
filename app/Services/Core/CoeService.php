<?php

namespace App\Services\Core;

use App\Interfaces\GoogleSheets\CoeRepositoryInterface;
use App\Interfaces\GoogleSheets\ApplicationRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Interfaces\GoogleSheets\CompanyRepositoryInterface;

class CoeService
{
    protected CoeRepositoryInterface $coeRepository;
    protected ApplicationRepositoryInterface $applicationRepository;
    protected StudentRepositoryInterface $studentRepository;
    protected CompanyRepositoryInterface $companyRepository;

    public function __construct(
        CoeRepositoryInterface $coeRepository,
        ApplicationRepositoryInterface $applicationRepository,
        StudentRepositoryInterface $studentRepository,
        CompanyRepositoryInterface $companyRepository
    ) {
        $this->coeRepository = $coeRepository;
        $this->applicationRepository = $applicationRepository;
        $this->studentRepository = $studentRepository;
        $this->companyRepository = $companyRepository;
    }

    /**
     * Get all COE records with related data
     *
     * @return array
     */
    public function getAllCoes(): array
    {
        $coes = $this->coeRepository->fetchAll();
        $applications = collect($this->applicationRepository->fetchAll())->keyBy('Application_ID');
        $students = collect($this->studentRepository->fetchAll())->keyBy('Student_ID');
        $companies = collect($this->companyRepository->fetchAll())->keyBy('Company_ID');

        return array_map(function ($coe) use ($applications, $students, $companies) {
            $student = $students->get($coe['Student_ID'] ?? '');
            $application = $applications->get($coe['Application_ID'] ?? '');
            $company = $companies->get($coe['Company_ID'] ?? '');

            $coe['Student_Name'] = $student['Full_Name'] ?? null;
            $coe['Student_Registration_Number'] = $student['Registration_Number'] ?? null;
            $coe['Application_Number'] = $application['Application_Number'] ?? null;
            $coe['Company_Name'] = $company['Company_Name'] ?? null;
            
            return $coe;
        }, $coes);
    }

    /**
     * @param string $id
     * @return array|null
     */
    public function getCoeById(string $id): ?array
    {
        $coe = $this->coeRepository->findById($id);
        
        if ($coe) {
            $student = $this->studentRepository->findById($coe['Student_ID'] ?? '');
            $application = $this->applicationRepository->findById($coe['Application_ID'] ?? '');
            $company = $this->companyRepository->findById($coe['Company_ID'] ?? '');

            $coe['Student_Name'] = $student['Full_Name'] ?? null;
            $coe['Student_Registration_Number'] = $student['Registration_Number'] ?? null;
            $coe['Application_Number'] = $application['Application_Number'] ?? null;
            $coe['Company_Name'] = $company['Company_Name'] ?? null;
        }

        return $coe;
    }

    /**
     * @param array $data
     * @param string $createdBy
     * @return bool
     */
    public function createCoe(array $data, string $createdBy): bool
    {
        $newId = $this->coeRepository->generateNewId();
        
        $data['COE_ID'] = $newId;
        $data['Is_Active'] = 'TRUE';
        $data['Created_At'] = now()->toDateTimeString();
        $data['Updated_At'] = now()->toDateTimeString();
        $data['Created_By'] = $createdBy;
        $data['Updated_By'] = $createdBy;

        return $this->coeRepository->create($data);
    }

    /**
     * @param string $id
     * @param array $data
     * @param string $updatedBy
     * @return bool
     */
    public function updateCoe(string $id, array $data, string $updatedBy): bool
    {
        $data['Updated_At'] = now()->toDateTimeString();
        $data['Updated_By'] = $updatedBy;

        return $this->coeRepository->update($id, $data);
    }

    /**
     * @param string $id
     * @param string $deletedBy
     * @return bool
     */
    public function deleteCoe(string $id, string $deletedBy): bool
    {
        return $this->coeRepository->softDelete($id, $deletedBy);
    }
}
