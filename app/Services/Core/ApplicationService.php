<?php

namespace App\Services\Core;

use App\Interfaces\GoogleSheets\ApplicationRepositoryInterface;
use App\Interfaces\GoogleSheets\MatchingRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Interfaces\GoogleSheets\JobOrderRepositoryInterface;

class ApplicationService
{
    protected ApplicationRepositoryInterface $applicationRepository;
    protected MatchingRepositoryInterface $matchingRepository;
    protected StudentRepositoryInterface $studentRepository;
    protected JobOrderRepositoryInterface $jobOrderRepository;

    public function __construct(
        ApplicationRepositoryInterface $applicationRepository,
        MatchingRepositoryInterface $matchingRepository,
        StudentRepositoryInterface $studentRepository,
        JobOrderRepositoryInterface $jobOrderRepository
    ) {
        $this->applicationRepository = $applicationRepository;
        $this->matchingRepository = $matchingRepository;
        $this->studentRepository = $studentRepository;
        $this->jobOrderRepository = $jobOrderRepository;
    }

    /**
     * Get all applications with related data
     *
     * @return array
     */
    public function getAllApplications(): array
    {
        $applications = $this->applicationRepository->fetchAll();
        $matchings = collect($this->matchingRepository->fetchAll())->keyBy('Matching_ID');
        $students = collect($this->studentRepository->fetchAll())->keyBy('Student_ID');
        $jobOrders = collect($this->jobOrderRepository->fetchAll())->keyBy('Job_Order_ID');

        return array_map(function ($app) use ($matchings, $students, $jobOrders) {
            $matching = $matchings->get($app['Matching_ID'] ?? '');
            $student = $students->get($app['Student_ID'] ?? '');
            $jo = $jobOrders->get($app['Job_Order_ID'] ?? '');

            $app['Matching_Number'] = $matching['Matching_Number'] ?? null;
            $app['Matching_Status'] = $matching['Matching_Status'] ?? null;
            
            $app['Student_Name'] = $student['Full_Name'] ?? null;
            $app['Student_Registration_Number'] = $student['Registration_Number'] ?? null;
            
            $app['Job_Order_Number'] = $jo['Job_Order_Number'] ?? null;
            $app['Job_Title'] = $jo['Job_Title'] ?? null;
            $app['Company_Name'] = $jo['Company_Name'] ?? null;
            
            return $app;
        }, $applications);
    }

    /**
     * @param string $id
     * @return array|null
     */
    public function getApplicationById(string $id): ?array
    {
        $app = $this->applicationRepository->findById($id);
        
        if ($app) {
            $matching = $this->matchingRepository->findById($app['Matching_ID'] ?? '');
            $student = $this->studentRepository->findById($app['Student_ID'] ?? '');
            $jo = $this->jobOrderRepository->findById($app['Job_Order_ID'] ?? '');

            $app['Matching_Number'] = $matching['Matching_Number'] ?? null;
            $app['Matching_Status'] = $matching['Matching_Status'] ?? null;
            
            $app['Student_Name'] = $student['Full_Name'] ?? null;
            $app['Student_Registration_Number'] = $student['Registration_Number'] ?? null;

            $app['Job_Order_Number'] = $jo['Job_Order_Number'] ?? null;
            $app['Job_Title'] = $jo['Job_Title'] ?? null;
            $app['Company_Name'] = $jo['Company_Name'] ?? null;
        }

        return $app;
    }

    /**
     * @param array $data
     * @param string $createdBy
     * @return bool
     */
    public function createApplication(array $data, string $createdBy): bool
    {
        $newId = $this->applicationRepository->generateNewId();
        
        $data['Application_ID'] = $newId;
        $data['Is_Active'] = 'TRUE';
        $data['Created_At'] = now()->toDateTimeString();
        $data['Updated_At'] = now()->toDateTimeString();
        $data['Created_By'] = $createdBy;
        $data['Updated_By'] = $createdBy;

        return $this->applicationRepository->create($data);
    }

    /**
     * @param string $id
     * @param array $data
     * @param string $updatedBy
     * @return bool
     */
    public function updateApplication(string $id, array $data, string $updatedBy): bool
    {
        $data['Updated_At'] = now()->toDateTimeString();
        $data['Updated_By'] = $updatedBy;

        return $this->applicationRepository->update($id, $data);
    }

    /**
     * @param string $id
     * @param string $deletedBy
     * @return bool
     */
    public function deleteApplication(string $id, string $deletedBy): bool
    {
        return $this->applicationRepository->softDelete($id, $deletedBy);
    }
}
