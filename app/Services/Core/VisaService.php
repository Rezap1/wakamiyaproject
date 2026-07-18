<?php

namespace App\Services\Core;

use App\Interfaces\GoogleSheets\VisaRepositoryInterface;
use App\Interfaces\GoogleSheets\CoeRepositoryInterface;
use App\Interfaces\GoogleSheets\ApplicationRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;

class VisaService
{
    protected VisaRepositoryInterface $visaRepository;
    protected CoeRepositoryInterface $coeRepository;
    protected ApplicationRepositoryInterface $applicationRepository;
    protected StudentRepositoryInterface $studentRepository;

    public function __construct(
        VisaRepositoryInterface $visaRepository,
        CoeRepositoryInterface $coeRepository,
        ApplicationRepositoryInterface $applicationRepository,
        StudentRepositoryInterface $studentRepository
    ) {
        $this->visaRepository = $visaRepository;
        $this->coeRepository = $coeRepository;
        $this->applicationRepository = $applicationRepository;
        $this->studentRepository = $studentRepository;
    }

    /**
     * Get all VISA records with related data
     *
     * @return array
     */
    public function getAllVisas(): array
    {
        $visas = $this->visaRepository->fetchAll();
        $coes = collect($this->coeRepository->fetchAll())->keyBy('COE_ID');
        $applications = collect($this->applicationRepository->fetchAll())->keyBy('Application_ID');
        $students = collect($this->studentRepository->fetchAll())->keyBy('Student_ID');

        return array_map(function ($visa) use ($coes, $applications, $students) {
            $student = $students->get($visa['Student_ID'] ?? '');
            $application = $applications->get($visa['Application_ID'] ?? '');
            $coe = $coes->get($visa['COE_ID'] ?? '');

            $visa['Student_Name'] = $student['Full_Name'] ?? null;
            $visa['Student_Registration_Number'] = $student['Registration_Number'] ?? null;
            $visa['Application_Number'] = $application['Application_Number'] ?? null;
            $visa['COE_Number'] = $coe['COE_Number'] ?? null;
            
            return $visa;
        }, $visas);
    }

    /**
     * @param string $id
     * @return array|null
     */
    public function getVisaById(string $id): ?array
    {
        $visa = $this->visaRepository->findById($id);
        
        if ($visa) {
            $student = $this->studentRepository->findById($visa['Student_ID'] ?? '');
            $application = $this->applicationRepository->findById($visa['Application_ID'] ?? '');
            $coe = $this->coeRepository->findById($visa['COE_ID'] ?? '');

            $visa['Student_Name'] = $student['Full_Name'] ?? null;
            $visa['Student_Registration_Number'] = $student['Registration_Number'] ?? null;
            $visa['Application_Number'] = $application['Application_Number'] ?? null;
            $visa['COE_Number'] = $coe['COE_Number'] ?? null;
        }

        return $visa;
    }

    /**
     * @param array $data
     * @param string $createdBy
     * @return bool
     */
    public function createVisa(array $data, string $createdBy): bool
    {
        $newId = $this->visaRepository->generateNewId();
        
        $data['Visa_ID'] = $newId;
        $data['Is_Active'] = 'TRUE';
        $data['Created_At'] = now()->toDateTimeString();
        $data['Updated_At'] = now()->toDateTimeString();
        $data['Created_By'] = $createdBy;
        $data['Updated_By'] = $createdBy;

        return $this->visaRepository->create($data);
    }

    /**
     * @param string $id
     * @param array $data
     * @param string $updatedBy
     * @return bool
     */
    public function updateVisa(string $id, array $data, string $updatedBy): bool
    {
        $data['Updated_At'] = now()->toDateTimeString();
        $data['Updated_By'] = $updatedBy;

        return $this->visaRepository->update($id, $data);
    }

    /**
     * @param string $id
     * @param string $deletedBy
     * @return bool
     */
    public function deleteVisa(string $id, string $deletedBy): bool
    {
        return $this->visaRepository->softDelete($id, $deletedBy);
    }
}
