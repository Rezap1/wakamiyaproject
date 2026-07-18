<?php

namespace App\Services\Core;

use App\Interfaces\GoogleSheets\InterviewRepositoryInterface;
use App\Interfaces\GoogleSheets\JobOrderRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;

class InterviewService
{
    protected InterviewRepositoryInterface $interviewRepository;
    protected JobOrderRepositoryInterface $jobOrderRepository;
    protected StudentRepositoryInterface $studentRepository;

    public function __construct(
        InterviewRepositoryInterface $interviewRepository,
        JobOrderRepositoryInterface $jobOrderRepository,
        StudentRepositoryInterface $studentRepository
    ) {
        $this->interviewRepository = $interviewRepository;
        $this->jobOrderRepository = $jobOrderRepository;
        $this->studentRepository = $studentRepository;
    }

    /**
     * Get all interviews with related Job Order and Student data
     *
     * @return array
     */
    public function getAllInterviews(): array
    {
        $interviews = $this->interviewRepository->fetchAll();
        $jobOrders = collect($this->jobOrderRepository->fetchAll())->keyBy('Job_Order_ID');
        $students = collect($this->studentRepository->fetchAll())->keyBy('Student_ID');

        return array_map(function ($interview) use ($jobOrders, $students) {
            $jo = $jobOrders->get($interview['Job_Order_ID']);
            $student = $students->get($interview['Student_ID']);

            $interview['Job_Order_Number'] = $jo['Job_Order_Number'] ?? null;
            $interview['Job_Title'] = $jo['Job_Title'] ?? null;
            $interview['Company_ID'] = $jo['Company_ID'] ?? null;
            
            $interview['Student_Name'] = $student['Full_Name'] ?? null;
            $interview['Student_Registration_Number'] = $student['Registration_Number'] ?? null;
            
            return $interview;
        }, $interviews);
    }

    /**
     * @param string $id
     * @return array|null
     */
    public function getInterviewById(string $id): ?array
    {
        $interview = $this->interviewRepository->findById($id);
        
        if ($interview) {
            $jo = $this->jobOrderRepository->findById($interview['Job_Order_ID'] ?? '');
            $student = $this->studentRepository->findById($interview['Student_ID'] ?? '');

            $interview['Job_Order_Number'] = $jo['Job_Order_Number'] ?? null;
            $interview['Job_Title'] = $jo['Job_Title'] ?? null;
            
            $interview['Student_Name'] = $student['Full_Name'] ?? null;
            $interview['Student_Registration_Number'] = $student['Registration_Number'] ?? null;
        }

        return $interview;
    }

    /**
     * @param array $data
     * @param string $createdBy
     * @return bool
     */
    public function createInterview(array $data, string $createdBy): bool
    {
        $newId = $this->interviewRepository->generateNewId();
        
        $data['Interview_ID'] = $newId;
        $data['Is_Active'] = 'TRUE';
        $data['Created_At'] = now()->toDateTimeString();
        $data['Updated_At'] = now()->toDateTimeString();
        $data['Created_By'] = $createdBy;
        $data['Updated_By'] = $createdBy;

        return $this->interviewRepository->create($data);
    }

    /**
     * @param string $id
     * @param array $data
     * @param string $updatedBy
     * @return bool
     */
    public function updateInterview(string $id, array $data, string $updatedBy): bool
    {
        $data['Updated_At'] = now()->toDateTimeString();
        $data['Updated_By'] = $updatedBy;

        return $this->interviewRepository->update($id, $data);
    }

    /**
     * @param string $id
     * @param string $deletedBy
     * @return bool
     */
    public function deleteInterview(string $id, string $deletedBy): bool
    {
        return $this->interviewRepository->softDelete($id, $deletedBy);
    }
}
