<?php

namespace App\Services\Core;

use App\Interfaces\GoogleSheets\MatchingRepositoryInterface;
use App\Interfaces\GoogleSheets\JobOrderRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Interfaces\GoogleSheets\InterviewRepositoryInterface;

class MatchingService
{
    protected MatchingRepositoryInterface $matchingRepository;
    protected JobOrderRepositoryInterface $jobOrderRepository;
    protected StudentRepositoryInterface $studentRepository;
    protected InterviewRepositoryInterface $interviewRepository;

    public function __construct(
        MatchingRepositoryInterface $matchingRepository,
        JobOrderRepositoryInterface $jobOrderRepository,
        StudentRepositoryInterface $studentRepository,
        InterviewRepositoryInterface $interviewRepository
    ) {
        $this->matchingRepository = $matchingRepository;
        $this->jobOrderRepository = $jobOrderRepository;
        $this->studentRepository = $studentRepository;
        $this->interviewRepository = $interviewRepository;
    }

    /**
     * Get all matchings with related Job Order, Student, and Interview data
     *
     * @return array
     */
    public function getAllMatchings(): array
    {
        $matchings = $this->matchingRepository->fetchAll();
        $jobOrders = collect($this->jobOrderRepository->fetchAll())->keyBy('Job_Order_ID');
        $students = collect($this->studentRepository->fetchAll())->keyBy('Student_ID');
        $interviews = collect($this->interviewRepository->fetchAll())->keyBy('Interview_ID');

        return array_map(function ($matching) use ($jobOrders, $students, $interviews) {
            $jo = $jobOrders->get($matching['Job_Order_ID']);
            $student = $students->get($matching['Student_ID']);
            $interview = $interviews->get($matching['Interview_ID']);

            $matching['Job_Order_Number'] = $jo['Job_Order_Number'] ?? null;
            $matching['Job_Title'] = $jo['Job_Title'] ?? null;
            $matching['Company_ID'] = $jo['Company_ID'] ?? null;
            $matching['Company_Name'] = $jo['Company_Name'] ?? null;
            
            $matching['Student_Name'] = $student['Full_Name'] ?? null;
            $matching['Student_Registration_Number'] = $student['Registration_Number'] ?? null;
            
            $matching['Interview_Number'] = $interview['Interview_Number'] ?? null;
            $matching['Interview_Date'] = $interview['Interview_Date'] ?? null;
            
            return $matching;
        }, $matchings);
    }

    /**
     * @param string $id
     * @return array|null
     */
    public function getMatchingById(string $id): ?array
    {
        $matching = $this->matchingRepository->findById($id);
        
        if ($matching) {
            $jo = $this->jobOrderRepository->findById($matching['Job_Order_ID'] ?? '');
            $student = $this->studentRepository->findById($matching['Student_ID'] ?? '');
            $interview = $this->interviewRepository->findById($matching['Interview_ID'] ?? '');

            $matching['Job_Order_Number'] = $jo['Job_Order_Number'] ?? null;
            $matching['Job_Title'] = $jo['Job_Title'] ?? null;
            $matching['Company_Name'] = $jo['Company_Name'] ?? null;
            
            $matching['Student_Name'] = $student['Full_Name'] ?? null;
            $matching['Student_Registration_Number'] = $student['Registration_Number'] ?? null;

            $matching['Interview_Number'] = $interview['Interview_Number'] ?? null;
            $matching['Interview_Date'] = $interview['Interview_Date'] ?? null;
        }

        return $matching;
    }

    /**
     * @param array $data
     * @param string $createdBy
     * @return bool
     */
    public function createMatching(array $data, string $createdBy): bool
    {
        $newId = $this->matchingRepository->generateNewId();
        
        $data['Matching_ID'] = $newId;
        $data['Is_Active'] = 'TRUE';
        $data['Created_At'] = now()->toDateTimeString();
        $data['Updated_At'] = now()->toDateTimeString();
        $data['Created_By'] = $createdBy;
        $data['Updated_By'] = $createdBy;

        return $this->matchingRepository->create($data);
    }

    /**
     * @param string $id
     * @param array $data
     * @param string $updatedBy
     * @return bool
     */
    public function updateMatching(string $id, array $data, string $updatedBy): bool
    {
        $data['Updated_At'] = now()->toDateTimeString();
        $data['Updated_By'] = $updatedBy;

        return $this->matchingRepository->update($id, $data);
    }

    /**
     * @param string $id
     * @param string $deletedBy
     * @return bool
     */
    public function deleteMatching(string $id, string $deletedBy): bool
    {
        return $this->matchingRepository->softDelete($id, $deletedBy);
    }
}
