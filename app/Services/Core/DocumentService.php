<?php

namespace App\Services\Core;

use App\Interfaces\GoogleSheets\DocumentRepositoryInterface;
use App\Interfaces\GoogleSheets\ApplicationRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;

class DocumentService
{
    protected DocumentRepositoryInterface $documentRepository;
    protected ApplicationRepositoryInterface $applicationRepository;
    protected StudentRepositoryInterface $studentRepository;

    public function __construct(
        DocumentRepositoryInterface $documentRepository,
        ApplicationRepositoryInterface $applicationRepository,
        StudentRepositoryInterface $studentRepository
    ) {
        $this->documentRepository = $documentRepository;
        $this->applicationRepository = $applicationRepository;
        $this->studentRepository = $studentRepository;
    }

    /**
     * Get all documents with related data
     *
     * @return array
     */
    public function getAllDocuments(): array
    {
        $documents = $this->documentRepository->fetchAll();
        $applications = collect($this->applicationRepository->fetchAll())->keyBy('Application_ID');
        $students = collect($this->studentRepository->fetchAll())->keyBy('Student_ID');

        return array_map(function ($doc) use ($applications, $students) {
            $student = $students->get($doc['Student_ID'] ?? '');
            $application = $applications->get($doc['Application_ID'] ?? '');

            $doc['Student_Name'] = $student['Full_Name'] ?? null;
            $doc['Student_Registration_Number'] = $student['Registration_Number'] ?? null;
            
            $doc['Application_Number'] = $application['Application_Number'] ?? null;
            
            return $doc;
        }, $documents);
    }

    /**
     * @param string $id
     * @return array|null
     */
    public function getDocumentById(string $id): ?array
    {
        $doc = $this->documentRepository->findById($id);
        
        if ($doc) {
            $student = $this->studentRepository->findById($doc['Student_ID'] ?? '');
            $application = $this->applicationRepository->findById($doc['Application_ID'] ?? '');

            $doc['Student_Name'] = $student['Full_Name'] ?? null;
            $doc['Student_Registration_Number'] = $student['Registration_Number'] ?? null;
            $doc['Application_Number'] = $application['Application_Number'] ?? null;
        }

        return $doc;
    }

    /**
     * @param array $data
     * @param string $createdBy
     * @return bool
     */
    public function createDocument(array $data, string $createdBy): bool
    {
        $newId = $this->documentRepository->generateNewId();
        
        $data['Document_ID'] = $newId;
        $data['Is_Active'] = 'TRUE';
        $data['Created_At'] = now()->toDateTimeString();
        $data['Updated_At'] = now()->toDateTimeString();
        $data['Created_By'] = $createdBy;
        $data['Updated_By'] = $createdBy;

        return $this->documentRepository->create($data);
    }

    /**
     * @param string $id
     * @param array $data
     * @param string $updatedBy
     * @return bool
     */
    public function updateDocument(string $id, array $data, string $updatedBy): bool
    {
        $data['Updated_At'] = now()->toDateTimeString();
        $data['Updated_By'] = $updatedBy;

        return $this->documentRepository->update($id, $data);
    }

    /**
     * @param string $id
     * @param string $deletedBy
     * @return bool
     */
    public function deleteDocument(string $id, string $deletedBy): bool
    {
        return $this->documentRepository->delete($id, $deletedBy);
    }
}
